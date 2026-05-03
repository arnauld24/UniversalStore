<?php

namespace core;
use PDOException;
use external_server\Connect;

require_once __DIR__ . '/../config/config.php';
// Prefer the secure bootstrap which registers Composer autoload (vendor/autoload.php)
// and provides session + CSRF helpers. This avoids fragile relative includes.
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
require_once CORE_PATH . '/UserTracker.php';

// --- PHP Session Configuration and Start ---
// These settings provide enhanced security for the PHP session cookie.
// Only set cookie params if session is not already active.
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30, // 30 days
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        // Set 'secure' only if the connection is HTTPS. Crucial for security.
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, // Prevents client-side JavaScript access
        'samesite' => 'Lax'
    ]);

    // Start the session. This MUST be called before any output.
    if(session_start() == PHP_SESSION_NONE){
    session_start();
    }
}

// Define the security constant if it hasn't been defined yet.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
    header("Location: $redirect_url");
    die('403 Access Denied.'); 
}

// setting constants for rate limiting , login attemps and account lockout
const GOOGLE_LOGIN_MAX_IP_ATTEMPT_TOTAL = 5; 
const GOOGLE_LOGIN_IP_ATTEMPTS_TIME_WINDOW_MINUTES = 15; 
//Excessive Signup prevention
const GOOGLE_MAX_SIGNUP_ATTEMPTS_TOTAL = 5; 
const GOOGLE_SIGNUP_ATTEMPTS_TIME_WINDOW_MINUTES = 15;

try{
class GoogleAuthController {

    
     // Checks if the user is authenticated using $_SESSION, then falls back to cookies.
     // Validates both against the database. If valid, re-establishes the session.
     // If invalid, destroys all session data and cookies.
    // @return string|null The authenticated user_id or null if not authenticated.
    
    public static function getAuthenticatedUserId() {
        // Data to check against the DB
        $user_id = null;
        $session_token = null;

        // 1. Primary Check: Look in $_SESSION (Fastest and most secure once logged in)
        if (isset($_SESSION['user_id'], $_SESSION['session_token'])) {
            $user_id = $_SESSION['user_id'];
            $session_token = $_SESSION['session_token'];

             // Before restoring, we validate the SESSEION tokens against the database first.
        try {
            $connect = new Connect();
            // we use a temporary prepared statement to validate the session tokens
            $stmt = $connect->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND user_session = :session_token AND logged_in = 1");
            $stmt->execute([':user_id' => strval($user_id), ':session_token' => $session_token]);
            
            if ($stmt->fetch(\PDO::FETCH_ASSOC)) {
                // session tokens are VALID. Re-establish the Cookie.
                $cookie_user_id = $user_id;
                $cookie_session_token = $session_token;

                // Re-establish the cookie session variables for subsequent requests.
                // This makes the next request rely on $_COOKIE(Step 2).
                $instance = new self();
                $instance->createSessionAndCookies($cookie_user_id, $cookie_session_token);

                // Regenerate the session ID to prevent session fixation on cookie restore
                // session_regenerate_id(true);
                } else {
                // Session validation FAILED (expired/mismatched). Immediately destroy it.
                error_log("Security alert: Invalid session token detected for user_id: {$user_id}. Clearing SESSION.");
                self::destroySessionAndCookies();
                return null;
            }
            } catch (PDOException $e) {
            error_log("DB error during cookie validation: " . $e->getMessage());
            // Fail safe and treat as invalid
            self::destroySessionAndCookies();
            return null;
        }
    } 
        // 2. Secondary Check: Look in secure Cookies (For "Remember Me" persistence)
        elseif (isset($_COOKIE['auth_uid'], $_COOKIE['session_key'])) {
           $cookie_user_id = $_COOKIE['auth_uid'];
           $cookie_session_token = $_COOKIE['session_key'];

        // Before restoring, we validate the COOKIE tokens against the database first.
        try {
            $connect = new Connect();
            // we use a temporary prepared statement to validate the cookie tokens
            $stmt = $connect->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND user_session = :session_token AND logged_in = 1");
            $stmt->execute([':user_id' => strval($cookie_user_id), ':session_token' => $cookie_session_token]);
            
            if ($stmt->fetch(\PDO::FETCH_ASSOC)) {
                // Cookie tokens are VALID. Re-establish the PHP session.
                $user_id = $cookie_user_id;
                $session_token = $cookie_session_token;

                // Re-establish the PHP session variables for subsequent requests.
                // This makes the next request rely on $_SESSION (Step 1).
                $_SESSION['user_id'] = $user_id;
                $_SESSION['session_token'] = $session_token;

                // Regenerate the session ID to prevent session fixation on cookie restore
                session_regenerate_id(true);
                // Ensure persistent cookies are present and refreshed. If client-side cookies were deleted
                // after we used them to restore the session, recreate them here to extend expiry and keep
                // persistent login working.
                try {
                    $instance = new self();
                    // recreate or refresh cookies unconditionally to ensure correct values and expiry
                    $instance->createSessionAndCookies($user_id, $session_token);
                } catch (\Throwable $e) {
                    error_log("Warning: failed to refresh persistent cookies after cookie->session restore: " . $e->getMessage());
                }
                } else {
                // Cookie validation FAILED (expired/mismatched). Immediately destroy it.
                error_log("Security alert: Invalid 'Remember Me' token detected for user_id: {$cookie_user_id}. Clearing cookies.");
                self::destroySessionAndCookies();
                return null;
            }
            } catch (PDOException $e) {
            error_log("DB error during cookie validation: " . $e->getMessage());
            // Fail safe and treat as invalid
            self::destroySessionAndCookies();
            return null;
        }
    } 
        // If no data found in session or cookies, the user is not logged in.
        if (empty($user_id) || empty($session_token)) {
            return null;
        }

        // 3. Ultimate Check: Validate Session Token against the Database
        try {
            $connect = new Connect();
            $stmt = $connect->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND user_session = :session_token AND logged_in = 1");
            $stmt->execute([':user_id' => strval($user_id), ':session_token' => $session_token]);

            if ($stmt->fetch(\PDO::FETCH_ASSOC)) {
                // Session is valid
                return $user_id;
            }
        } catch (PDOException $e) {
            error_log("DB error during session validation: " . $e->getMessage());
            // Fail safe and treat as invalid
        }

        // 4. If validation fails (expired/stolen/mismatch), update logged_in to 0 and destroy all tokens
        try {
            $connect = new Connect();
            $logged_in = boolval(false);
            $stmt = $connect->prepare("UPDATE users SET logged_in = :logged_in WHERE user_id = :user_id");
            $stmt->execute([':logged_in' => $logged_in, ':user_id' => $user_id]);
        } catch (PDOException $e) {
            error_log("Error updating logged_in on invalid session: " . $e->getMessage());
        }
        error_log("Security alert: Invalid session token detected for user_id: {$user_id}. Clearing session.");
        self::destroySessionAndCookies();
        return null;
    }

    
     // Clears all session data and removes persistent cookies.
     
    public static function destroySessionAndCookies() {
        // Clear $_SESSION data
        $_SESSION = [];

        // Invalidate the PHP session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 86400 * 30,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Destroy the PHP session
        session_unset();
        session_destroy();

        // Invalidate the persistent cookies (user_id and user_session)
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie('auth_uid', '',[
          'expires' => time() - 86400 * 30,
          'path' => '/',
          'domain' => $domain,
          'secure' => $secure,
          'httponly' => true,
          'samesite' => 'Lax']);

        setcookie('session_key', '',[
          'expires' => time() - 86400 * 30,
          'path' => '/',
          'domain' => $domain,
          'secure' => $secure,
          'httponly' => true,
          'samesite' => 'Lax']);   
         }

    //  Combine session and cookie setting 
    protected function createSessionAndCookies($user_id, $session_token) {
        // The domain logic is used in setcookie and defined here to ensure consistency
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        // 1. Set values in $_SESSION 
        $_SESSION['user_id'] = $user_id;
        $_SESSION['session_token'] = $session_token; // The unique token stored in the DB

        // 2. Set the secure cookies (for persistent login across browser restarts)

        // Cookie for user_id
        setcookie('auth_uid', $user_id, [
            'expires' => time() + 60 * 60 * 24 * 30, // 30 days
            'path' => "/",
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true, // Essential for security, prevents XSS reading
            'samesite' => 'Lax'
        ]);
        
        // Cookie for the secure session token
        setcookie('session_key', $session_token, [
            'expires' => time() + 60 * 60 * 24 * 30, // 30 days
            'path' => "/",
            'domain' => $domain, 
            'secure' => $secure, // Only transmit over HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    //logged_in update status
    public function updateLoggedInStatus($authenticatedUserId){
            
        // If the user is NOT authenticated, but we have a lingering identifier (cookie or session),
        // clear the `logged_in` flag in the database for that user to ensure accurate status.
        if (empty($authenticatedUserId)) {
        // Try to find a candidate user id from cookies or session
        $candidateUserId = null;
        if (!empty($_COOKIE['auth_uid'])) {
            $candidateUserId = $_COOKIE['auth_uid'];
        } elseif (!empty($_SESSION['user_id'])) {
            $candidateUserId = $_SESSION['user_id'];
        }

        if (!empty($candidateUserId)) {
            try {
            $connect = new Connect();
            $stmt = $connect->prepare("UPDATE users SET logged_in = 0 WHERE user_id = :uid");
            $stmt->execute([':uid' => (string)$candidateUserId]);
            } catch (\Throwable $e) {
            // Log but do not break page rendering
            error_log('Failed to clear logged_in flag for user ' . htmlspecialchars($candidateUserId) . ': ' . $e->getMessage());
            }
        }
        }
    }
    // output user data
    function printData($user_id){
    $connect = new Connect();
        $stmt = $connect->prepare("SELECT user_fname, user_lname, user_avatar, user_email FROM users WHERE user_id = :user_id");
        $stmt -> execute([':user_id' => $user_id]);
        $content = "
        <table class='caontainer table'>
        <thead class='thead-light'>
        <tr>
        <th scope='col'>First name</th>
        <th scope='col'>last name</th>
        <th scope='col'>Avatar </th>
        <th scope='col'>Email</th>
        </tr>
        </thead>
            <tbody>
            ";
        
        while($row = $stmt->fetch(\PDO::FETCH_ASSOC)){
            $content .= "
            <tr>
            <td>".htmlspecialchars($row['user_fname'])."</td>
            <td>".htmlspecialchars($row['user_lname'])."</td>
            <td><img src='".htmlspecialchars($row['user_avatar'])."' alt='avatar' width='50px' height='50px' style='border-radius:50%;'></td>
            <td>".htmlspecialchars($row['user_email'])."</td>
            </tr>
            
            ";
        }
        $content .= ' </tbody></table>';
        return $content;
    }
  
    // Generate Secure codes
    function generateCode($length){
        try{
            // Generate $length bytes of cryptographically secure random data
            $random_bytes = random_bytes($length);
            // Convert the bytes to a hex string for storage (e.g., 32 bytes -> 64 characters)
            $password = bin2hex($random_bytes);
            return $password;
        }
        catch(\Exception $e){
            error_log("Error generating secure random bytes: " . $e->getMessage());
            return null;
        }
    }
    //generating user_id
    function generateUserid($length){
        try{
            $random_bytes = random_bytes($length);
            $user_id = bin2hex($random_bytes);
            return $user_id;
        }
        catch(\Exception $e){
            error_log("Error generating user_id: " . $e->getMessage());
            return null;
        }
    }
    
    //insert data in database
    function insertData($data){
        
        //verify csrf token
        $csrf_token = $data['csrf_token']; 
        if(!validateCsrfToken($csrf_token)){
            http_response_code(403);
            error_log("CSRF token is invalid.");
            header("Location: core/error_URL.php?code=403&message=". urlencode("Session expired, please refresh the page and try again"));
            exit;
        }

        $connect = new Connect;
        // 1. Validate Input Data (Ensure all required keys exist)
        $google_uid = $data['id'] ;
        $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL) ?? '';
        $fname = trim($data['givenName']) ?? 'Google User'; // first name
        $lname = trim($data['familyName']) ?? 'New'; // last name
        $avatar = trim($data['avatar']) ?? '';
        $user_account_creation_date = date('Y-m-d H:i:s'); // current date and time
        $last_login = date('Y-m-d H:i:s'); // current date and time
        $logged_in = boolval(true); // set to true on login
        $user_ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; // get user IP address
        $login_attempts = 0;
        $is_verified = boolval(true);

        $submittedTimezone = $data['tz'] ?? 'UTC'; 
        $submittedDevicetype = $data['dt'] ?? 'Unknown';
        $submittedLocale = $data['lc'] ?? 'en-US';

        // Validate and sanitize timezone , devicetype and locale
        // Validate timezone
        $validTimezones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
        if (in_array($submittedTimezone, $validTimezones, true)) {
            $timezone = $submittedTimezone;
        } else {// use default if invalid
            $timezone = 'UTC'; }
        // Sanitize device type
        $devicetype = filter_var(trim($submittedDevicetype), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($devicetype)) {
            $devicetype = 'Unknown';}
        // Validate locale format (e.g., en, en-US, fr, fr-CA)
        if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $submittedLocale)) {
            $locale = filter_var(trim($submittedLocale), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        } else {
            // use default if invalid
            $locale = 'en-US';}

        // Setting Rate Limiting IP
        $time_limit_ip = date('Y-m-d H:i:s', strtotime("-".GOOGLE_LOGIN_IP_ATTEMPTS_TIME_WINDOW_MINUTES. "minutes"));

        //count number of authentication attempt
        $stmt = $connect->prepare("SELECT COUNT(user_id) AS ip_attempt_count FROM users WHERE user_ip_address = :user_ip AND last_login >= :time_limit");
        $stmt->execute([':user_ip' => $user_ip_address, ':time_limit' => $time_limit_ip]);
        $ip_attempts_count = $stmt->fetchColumn();

        if ($ip_attempts_count >= GOOGLE_LOGIN_MAX_IP_ATTEMPT_TOTAL) {
            // Track repeated login failure due to rate limiting
            \UserTracker::trackLoginEvent('failure', ['user_email' => $email, 'reason' => 'rate_limit']);
            http_response_code(429);
            error_log("Too many authentication attempts from User {$email} with IP: " . htmlspecialchars($user_ip_address));
            header('Location: core/error_URL.php?code=429&message=' . urlencode('Too many connection attempts from this location. Please try again later.'));
            //Update Token
            rotateCsrfToken();
            exit;
        }

        if (empty($google_uid) || empty($email)) { 
            // Track failed login signup due missing data
            \UserTracker::trackLoginEvent('failure', ['user_email' => $email, 'reason' => 'missing_required_data']);
            http_response_code(400); 
            error_log("Google OAuth Handler: Essential data missing (400 Bad Request).");
            exit;
        }

        // Database Interaction and Logic
        try{
            //create new session id (This is the secure token stored in the database and cookie)
            $new_session_token = $this->generateCode(32);

            // Check for Existing User (Login Logic)
            $stmt = $connect->prepare("SELECT user_id, google_uid, user_password FROM users WHERE user_email = :email OR google_uid = :google_uid");
            $stmt->execute([':email' => $email,':google_uid' => $google_uid]);
            $info = $stmt->fetch(\PDO::FETCH_ASSOC);

            //If user exists, $info will be an array; otherwise, it's false
            if($info){

                // USER FOUND (LOGIN/ACCOUNT LINKING)
                $user_id = $info['user_id'];

                // ACCOUNT LINKING: If the account was found by EMAIL, but doesn't have a Google ID, link it now.
                if (empty($info['google_uid'])) {
                    $update_stmt = $connect->prepare("UPDATE users
                            SET google_uid = :google_uid,
                                user_avatar = :user_avatar,
                                user_fname = :user_fname,
                                user_lname = :user_lname,
                                last_login = :last_login,
                                logged_in = :logged_in,
                                user_ip_address = :user_ip,
                                login_attempt = :login_attempt,
                                device_type_last = :devicetype,
                                time_zone = :timezone,
                                locale = :locale,
                                user_session = :user_session,
                                is_verified = :is_verified
                            WHERE user_id = :user_id AND google_uid = '' "
                    );
                    $update_stmt->execute([
                        ':google_uid' => $google_uid,
                        ':user_avatar' => $avatar,
                        ':user_fname' => $fname,
                        ':user_lname' => $lname,
                        ':last_login' => $last_login,
                        ':logged_in' => $logged_in,
                        ':user_ip' => $user_ip_address,
                        ':login_attempt' => $login_attempts,
                        ':devicetype' => $devicetype,
                        ':timezone' => $timezone,
                        ':locale' => $locale,
                        ':user_session' => $new_session_token, // Update session on successful login/link
                        ':is_verified' => $is_verified,
                        ':user_id' => $user_id
                    ]);
                }else{
                    // If account was already linked by Google UID, update the info and session
                    $update_stmt = $connect->prepare("UPDATE users SET user_avatar = :user_avatar, user_fname = :user_fname, user_lname = :user_lname, user_session = :user_session, last_login = :last_login,logged_in = :logged_in, login_attempt = :login_attempt, user_ip_address = :user_ip,device_type_last = :devicetype, time_zone = :timezone, locale = :locale , is_verified = :is_verified WHERE user_id = :user_id");
                    $update_stmt->execute([
                        ':user_avatar' => $avatar,
                        ':user_fname' => $fname,
                        ':user_lname' => $lname,
                        ':last_login' => $last_login,
                        ':logged_in' => $logged_in,
                        'login_attempt' => $login_attempts,
                        ':user_ip' => $user_ip_address,
                        ':devicetype' => $devicetype,
                        ':timezone' => $timezone,
                        ':locale' => $locale,
                        ':user_session' => $new_session_token,
                        ':is_verified' => $is_verified,
                        ':user_id' => $user_id
                    ]);   
                }

                // SECURITY: Rotate the PHP Session ID for Session Fixation prevention
                session_regenerate_id(true);
                 //set default redirection url page
                    $target_url = INDEX_URL;
                    if(isset($_SESSION['redirect_after_oauth']) && !empty($_SESSION['redirect_after_oauth'])){
                        // usee the stored URL
                        $target_url = $_SESSION['redirect_after_oauth'];
                        //clear redirect session immediately
                        unset($_SESSION['redirect_after_oauth']);
                    }
                // Update CSRF token (assumed to be based on $_SESSION)
                rotateCsrfToken();
                
                // Set secure $_SESSION variables and persistent cookies
                $this->createSessionAndCookies($user_id, $new_session_token);

                // Track login success event
                \UserTracker::trackLoginEvent('success', ['user_id' => $user_id, 'auth_method' => 'google']);

                header("Location: $target_url");
                exit();
            } else {
                // IF NO USER FOUND (SIGNUP)

                // Rate Limiting: Check recent sign-up attempts from this IP
                $time_limit_signup = date('Y-m-d H:i:s', strtotime("-".GOOGLE_SIGNUP_ATTEMPTS_TIME_WINDOW_MINUTES." minutes"));
                
                $stmt = $connect->prepare("
                    SELECT COUNT(user_id) AS ip_signup_count
                    FROM users 
                    WHERE user_ip_address = :user_ip
                    AND user_account_creation_date >= :time_limit
                ");
                $stmt->execute([':user_ip' => $user_ip_address, ':time_limit' => $time_limit_signup]);
                $ip_signup_count = $stmt->fetchColumn();

                if ($ip_signup_count >= GOOGLE_MAX_SIGNUP_ATTEMPTS_TOTAL) {
                    http_response_code(429);
                    error_log("User {$email} with IP {$user_ip_address} has been temporarily blocked due to too many sign-up attempts.");
                    $redirect_url =  ERROR_URL . "?code=429&message=" . urlencode('Too many sign-up attempts from this location. Please try again later.');
                    header("Location: $redirect_url");
                    //update csrf token
                    rotateCsrfToken();
                    exit;
                }

                $user_id = $this->generateUserid(32);
                // Since this is a Google login, a dummy password is set for database integrity/future non-Google logins
                $password = $this->generateCode(32); 
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertUser = $connect->prepare("INSERT INTO users (user_id,user_fname,user_lname,user_avatar,user_email,user_password,user_session,google_uid,last_login,logged_in,user_account_creation_date,login_attempt,user_ip_address,device_type_last ,time_zone,locale, is_verified)
                VALUES (:user_id,:user_fname,:user_lname,:user_avatar,:user_email,:user_password,:user_session,:google_uid,:last_login,:logged_in,:account_creation_date,:login_attempt,:user_ip,:devicetype,:timezone,:locale,:is_verified)");
                
                $result = $insertUser-> execute([
                    ':user_id' => $user_id,
                    ':user_fname' => $data['givenName'],
                    ':user_lname' => $data['familyName'],
                    ':user_avatar' => $data['avatar'],
                    ':user_email' => $data['email'],
                    ':user_password' => $hashedPassword,
                    ':google_uid' => $google_uid,
                    ':last_login' => $last_login,
                    ':logged_in' => $logged_in,
                    'login_attempt' => $login_attempts,
                    ':account_creation_date' => $user_account_creation_date,
                    ':user_ip' => $user_ip_address,
                    ':devicetype' => $devicetype,
                    ':timezone' => $timezone,
                    ':locale' => $locale,
                    ':is_verified' => $is_verified,
                    ':user_session' => $new_session_token
                    
                ]);
                if($result){
                    // Rotate the PHP Session ID for Session Fixation prevention
                    session_regenerate_id(true);
                     //set default redirection url page
                    $target_url = INDEX_URL;
                    if(isset($_SESSION['redirect_after_oauth']) && !empty($_SESSION['redirect_after_oauth'])){
                        // usee the stored URL
                        $target_url = $_SESSION['redirect_after_oauth'];
                        //clear redirect session immediately
                        unset($_SESSION['redirect_after_oauth']);
                    }
                    // Update CSRF token 
                    rotateCsrfToken();
                    
                    // Set secure $_SESSION variables and persistent cookies
                    $this->createSessionAndCookies($user_id, $new_session_token);

                    // Track account created and login success event
                    \UserTracker::trackLoginEvent('success', ['user_id' => $user_id, 'auth_method' => 'google']);
                    \UserTracker::trackEvent('account_created', ['user_id' => $user_id], 'conversion');

                    header("Location: $target_url");
                    exit();
                }else {
                    http_response_code(500); 
                    error_log("Google Handler: Insert failed, but no exception caught.");
                    $redirect_url =  ERROR_URL . "?code=500&message=" . urlencode('Registration failed due to a system error.');
                    header("Location: $redirect_url");
                }
            }
        } catch (PDOException $e) {
            // Handle database errors (e.g., connection fail, query fail)
            http_response_code(401);
            error_log("Database Error in Google Handler: " . $e->getMessage());
            $redirect_url =  ERROR_URL . "?code=401&message=" . urlencode('Internal Server Error during registration.error_db_failure.');
            header("Location: $redirect_url");
        
        }
    }
}
} catch (PDOException $e) {
    // Handle database errors (e.g., connection fail, query fail)
    http_response_code(401);
    error_log("Database Error in Google Handler: " . $e->getMessage());
    $redirect_url =  ERROR_URL . "?code=401&message=" . urlencode('error_db_failure.');
    header("Location: $redirect_url");
    exit;
} catch(\Exception $e) {
    // DATABASE ERROR (500)
    http_response_code(500); 
    error_log("General Error in Google Handler: " . $e->getMessage());
    $redirect_url =  ERROR_URL . "?code=500&message=" . urlencode('error_general_auth_failure.Internal Server Error during registration.');
    header("Location: $redirect_url");
    exit;
}

?>
