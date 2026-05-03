<?php

namespace core;
use external_server\Connect;
use core\utility;
use PDO;
use Throwable;
use PDOException;

ob_start();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
// Define constants for rate limiting , login attemps and account lockout
const MAX_LOGIN_ATTEMPTS = 5; // Maximum allowed login attempts
const MAX_IP_ATTEMPTS_TOTAL = 15; 
const IP_ATTEMPTS_TIME_WINDOW_MINUTES = 5; 
const LOCKOUT_DURATION_MINUTES = 30; // Time period to unlock account
try { 
   
    // Ensure secure bootstrap (registers Composer autoload + session/CSRF helpers)
    require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
    $utility_class = new utility();
// Deny direct access if it's not a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        error_log("Invalid request method in login handler: " . $_SERVER['REQUEST_METHOD']);
        $utility_class->jsonError('An unexpected error occurred. Please refresh the page.', 405);
    }

    if (!validateAjaxCsrfToken()) {
        http_response_code(403);
        error_log("CSRF token is invalid.");
        $utility_class->jsonError('Session expired, please refresh the page and try again', 403);
    }
   
    $data = json_decode(file_get_contents("php://input"), true);
    // Check if JSON decoding was successful and data is present
    if ($data === null) {
        error_log("Invalid JSON data received in login handler.");
        $utility_class->jsonError('Invalid JSON data received.', 406);
    }

    // Check form data, validate and sanitize
    if (!isset($data['email'], $data['loginpassword'])) {
        $utility_class->jsonError('Missing required fields.', 400);
    }

    $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($data['loginpassword']);
    $last_login = date('Y-m-d H:i:s'); // current date and time
    $logged_in = boolval(true); // set to true on login
    $user_ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; // get user IP address
    $login_attempts = 0; // initialize login attempts
    
    $submittedTimezone = $data['timezone'] ?? 'UTC'; 
    $submittedDevicetype = $data['devicetype'] ?? 'Unknown';
    $submittedLocale = $data['locale'] ?? 'en-US';
    // Validate and sanitize timezone , devicetype and locale
    // Validate timezone
    $validTimezones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);
    if (in_array($submittedTimezone, $validTimezones, true)) {
        $timezone = $submittedTimezone;
    } else {// use default if invalid 
         $timezone = 'UTC';  }
     // Sanitize device type
    $devicetype = filter_var(trim($submittedDevicetype), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty($devicetype)) {
    $devicetype = 'Unknown';
    }

    // Validate locale format (e.g., en, en-US, fr, fr-CA)
    if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $submittedLocale)) {
        $locale = filter_var(trim($submittedLocale), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    } else {
        // use default if invalid
        $locale = 'en-US';
    }

     // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422); // Unprocessable Entity
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }
  
    try {
        $generate = new utility;
    } catch (Throwable $e) {
        error_log("Login handler: failed to initialize code generator: " . $e->getMessage());
        $utility_class->jsonError('Server error initializing login process.', 500);
    }

    // Create connection (autoloaded) with error handling
    try {
        $connect = new Connect();
    } catch (Throwable $e) {
        error_log("Login handler: database connection failed: " . $e->getMessage());
        $utility_class->jsonError('Database connection error. Please try again later.', 500);
    }

    // Implement rate limiting based on IP address
    try {
        $time_limit = date('Y-m-d H:i:s', strtotime("-".IP_ATTEMPTS_TIME_WINDOW_MINUTES." minutes"));
        $stmt = $connect->prepare("SELECT COUNT(user_id) AS ip_attempt_count FROM users WHERE user_ip_address = :user_ip AND login_attempt > 0 AND last_login > :time_limit");
        $stmt->execute([':user_ip' => $user_ip_address, ':time_limit' => $time_limit]);
        $ip_attempts_count = $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Login handler: failed to check IP rate-limits: " . $e->getMessage());
        $utility_class->jsonError('Server error. Please try again later.', 500);
    }

        if($ip_attempts_count >= MAX_IP_ATTEMPTS_TOTAL) {
            http_response_code(429); // Too Many Requests
            error_log("IP {$user_ip_address} has been temporarily blocked due to too many login attempts. {$time_limit} minutes");
            $utility_class->jsonError('Too many login attempts from this location. Please try again later.', 429);
        }

        // Check if user exists
        try {
            $stmt = $connect->prepare("SELECT * FROM users WHERE user_email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Login handler: failed to query user: " . $e->getMessage());
            $utility_class->jsonError('Server error checking user.', 500);
        }

        if ($user && ($email === $user['user_email'])) {
            sleep(rand(1, 3));
             $login_attempts = $user['login_attempt'];    
             $last_login_time = $user['last_login'];
             if ($login_attempts >= MAX_LOGIN_ATTEMPTS) {
                $lockout_duration_seconds = LOCKOUT_DURATION_MINUTES * 60;
                $lockout_end_time = strtotime($last_login_time) + $lockout_duration_seconds;
                $is_still_locked = time() < $lockout_end_time;
                if($is_still_locked){
                   // Account still lock
                    $remaining_seconds = $lockout_end_time - time();
                    $remaining_minutes = ceil($remaining_seconds / 60);
                     http_response_code(403);
                     error_log("User {$email} account is still locked. Remaining time: {$remaining_minutes} minutes." );
                     $utility_class->jsonError("Your account is locked due to too many failed attempts. Try again later", 403);
                    // $utility_class->jsonError exits
                }else {  
                    // reset login_attempts 
                 try {
                     $reset_stmt = $connect->prepare("UPDATE users SET login_attempt = 0 WHERE user_id = :user_id");
                     $reset_stmt->execute([':user_id' => $user['user_id']]);
                 } catch (PDOException $e) {
                     error_log("Login handler: failed to reset login attempts: " . $e->getMessage());
                     $utility_class->jsonError('Server error. Please try again later.', 500);
                 }
                 //set login attempt to 0 for script contuination
                 $login_attempts = 0;
                   
                }
            http_response_code(403); // Forbidden
            error_log("User {$email} account has been locked due to too many failed login attempts.");
            $utility_class->jsonError('Your account is locked due to too many failed login attempts. Try again later.', 403);
                    }
            if($user && password_verify($password, $user['user_password'])){
            // Password correct, generate new session and update DB
            $new_session = $generate->generateCode(32);
            try {
                $stmt = $connect->prepare("UPDATE users SET user_session = :session , time_zone = :timezone, device_type_last = :devicetype, locale = :locale, logged_in = :loggedin, last_login = :lastlogin, login_attempt = :login_attempt, user_ip_address = :user_ip WHERE user_id = :user_id");
                $stmt->execute([
                    ':session' => $new_session,
                    ':timezone' => $timezone,
                    ':devicetype' => $devicetype,
                    ':locale' => $locale,
                    ':loggedin' => $logged_in,
                    ':lastlogin' => $last_login,
                    'user_ip' => $user_ip_address,
                    ':login_attempt' => 0,
                    ':user_id' => $user['user_id']]);
            } catch (PDOException $e) {
                error_log("Login handler: failed to update user session: " . $e->getMessage());
                $utility_class->jsonError('Server error logging you in. Please try again later.', 500);
            }
            //regenerate session to prevent session fixation
            session_regenerate_id(true);
            // set cookies
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['session_token'] = $new_session; // Storing the DB token in session for verification
            $_SESSION['logged_in_time'] = time(); // Session fixation prevention check
            
            // Store user email in session for later use
            $_SESSION['user_email'] = $user['user_email'];
            setcookie('auth_uid', $user['user_id'], time() + 60 * 60 * 24 * 7, "/", "localhost", false, true);
            setcookie('session_key', $new_session, time() + 60 * 60 * 24 * 7, "/", "localhost", false, true);
            //set default redirection page
            $target_url = INDEX_URL;
            if(isset($_SESSION['redirect_after_oauth']) && !empty($_SESSION['redirect_after_oauth'])){
                // usee the stored URL
                $target_url = $_SESSION['redirect_after_oauth'];
                //clear redirect session immediately
                unset($_SESSION['redirect_after_oauth']);
            }
            rotateCsrfToken();
            echo json_encode([
                'success' => true,
                'message' => 'Login successful! Redirecting...',
                'redirect' => $target_url,
                'csrf_token' => htmlspecialchars(getCsrfToken())
            ]);
            exit;
        } else {
            // Password incorrect, increment login attempts
            $new_attempts = $login_attempts + 1;
                try {
                    $stmt = $connect->prepare("UPDATE users SET login_attempt = :login_attempt, last_login = :lastlogin, user_ip_address = :user_ip WHERE user_id = :user_id");
                    $stmt->execute([
                        ':login_attempt' => $new_attempts,
                        'lastlogin' => $last_login,
                        ':user_ip' => $user_ip_address,
                        ':user_id' => $user['user_id']
                    ]);
                } catch (PDOException $e) {
                    error_log("Login handler: failed to update login attempts: " . $e->getMessage());
                    rotateCsrfToken();
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Server error. Please try again later.', 'csrf_token' => htmlspecialchars(getCsrfToken())]);
                    exit;
                }
                $remaining = MAX_LOGIN_ATTEMPTS - $new_attempts;
                http_response_code(401); // Unauthorized
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
                exit;
               if($remaining > 5) {
                error_log("User {$email} has user has {$remaining}attempted login too many times.");
                echo json_encode(['success' => false, 'message' => 'Invalid email or password. Too many Attempts.']);
                exit;
            }else{
                error_log("User {$email} account has been locked due to too many failed login attempts.");
                echo json_encode(['success' => false, 'message' => 'Your account is locked due to too many failed login attempts. Please try again later.']);
                exit;
            }
         }
        } else {
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => 'Email address does not exist.']);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $utility_class->jsonError('System error occurred.', 500);
    }
    catch (Throwable $e) {
        error_log("Server error: ". $e->getTraceAsString());
        $utility_class->jsonError('Unexpected server error occurred.', 500);
    }
?>
