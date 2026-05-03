<?php

namespace core;
use external_server\Connect;
use core\utility;
use PDOException;
use Exception;
use Throwable;
// Define the security constant if it hasn't been defined yet.

ob_start();
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
// Define constants for rate limiting
const MAX_SIGNUP_ATTEMPTS_TOTAL = 5;
const SIGNUP_ATTEMPTS_TIME_WINDOW_MINUTES = 10;

try { 
    $utility_class = new utility();
    // secure session + CSRF helpers and configuration..
    require_once SERVER_PATH . '/secure_session_csrf_cookie.php'; 
    // Utility helpers for JSON errors and code generation
    require_once CORE_PATH . '/utility.php';
 // Deny direct access if it's not a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); // Method Not Allowed
        error_log("Invalid request method in signup handler: " . $_SERVER['REQUEST_METHOD']);
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
        error_log('Invalid JSON data received in signup handler.');
        $utility_class->jsonError('Invalid JSON data.', 406);
    }

    // Check form data, validate and sanitize
    if (!isset($data['email'], $data['signuppassword'], $data['fname'], $data['lname'])) {
        error_log('Missing required fields in signup handler.');
        $utility_class->jsonError('Missing required fields.', 400);
    }

    // Initialize helper objects with localized error handling so we can
    // return structured JSON errors instead of allowing a fatal.
    try {
        $generate = new utility();
    } catch (Throwable $e) {
        error_log("Signup handler: failed to initialize code generator: " . $e->getMessage());
        $utility_class->jsonError('Server error initializing signup process.', 500);
    }

    try {
        $connect = new Connect();
    } catch (Throwable $e) {
        error_log("Signup handler: database connection initialization failed: " . $e->getMessage());
        $utility_class->jsonError('Database connection error. Please try again later.', 500);
    }
    // Generate user ID and session token
    $user_id = $generate->generateUserid(32);
    $session = $generate->generateCode(32);

    $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($data['signuppassword']);
    $fname = trim(strip_tags($data['fname'] ?? ''));
    $lname = trim(strip_tags($data['lname'] ?? ''));
    $default_avatar = '../../assets/imgs/user.png';
    $user_account_creation_date = date('Y-m-d H:i:s'); // current date and time
    $last_login = date('Y-m-d H:i:s'); // current date and time
    $logged_in = boolval(true); // set to true on login
    $user_ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; // get user IP address
    $login_attempts = 0;           
    $is_verified =boolval(false);                  

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
   
// validate email and password strength
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422); // Unprocessable Entity
        error_log("Invalid email address in signup handler: " . htmlspecialchars($email));
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }
    if (strlen($password) < 8) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }
    // Hash password for security
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Create new user
    $connect = new Connect;

     // Implement rate limiting based on IP address
        $time_limit = date('Y-m-d H:i:s', strtotime("-".SIGNUP_ATTEMPTS_TIME_WINDOW_MINUTES." minutes"));
        try {
            $stmt = $connect->prepare("SELECT COUNT(user_id) AS ip_signup_count FROM users WHERE user_ip_address = :user_ip AND user_account_creation_date > :time_limit");
            $stmt->execute([':user_ip' => $user_ip_address, ':time_limit' => $time_limit]);
            $ip_signup_count = $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Signup handler: failed to check rate-limit: " . $e->getMessage());
            $utility_class->jsonError('Server error. Please try again later.', 500);
        }

        if($ip_signup_count >= MAX_SIGNUP_ATTEMPTS_TOTAL) {
            http_response_code(429); // Too Many Requests
            error_log("IP {$user_ip_address} has been temporarily blocked due to too many login attempts. {$time_limit} minutes");
            $redirect_url =  ERROR_URL . "?code=429&message=" . urlencode('Too many login attempts from this location. Please try again later.');
            header("Location: $redirect_url");
            $utility_class->jsonError('Too many login attempts from this location. Please try again later.', 429, ['redirect' => $redirect_url]);
        }
    // Check if email exists
    try {
        $stmt = $connect->prepare("SELECT * FROM users WHERE user_email = :email");
        $stmt->execute([':email' => $email]);
        $info = $stmt->fetch(\PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Signup handler: failed to query existing user: " . $e->getMessage());
        $utility_class->jsonError('Server error checking existing user.', 500);
    }

    if (!$info) {
       sleep(rand(0, 1)); 
        // Insert new user
        try {
            $stmt = $connect->prepare("INSERT INTO users (user_id, user_fname, user_lname, user_email, user_password, user_session, user_avatar,time_zone,device_type_last,locale,user_ip_address,last_login,login_attempt,logged_in,user_account_creation_date,is_verified) VALUES (:user_id, :user_fname, :user_lname, :user_email, :user_password, :user_session, :user_avatar, :timezone, :devicetype, :locale,:user_ip, :last_login, :login_attempt, :logged_in, :account_creation_date, :is_verified)");
            $result = $stmt->execute([
            ':user_id' => $user_id,
            ':user_fname' => $fname,
            ':user_lname' => $lname,
            ':user_email' => $email,
            ':user_password' => $hashed_password,
            ':user_session' => $session,
            ':user_avatar' => $default_avatar,
            ':timezone' => $timezone,
            ':devicetype' => $devicetype,
            ':locale' => $locale,
            ':user_ip' => $user_ip_address,
            ':last_login' => $last_login,
            ':login_attempt' => $login_attempts,
            ':logged_in' => $logged_in,
            ':account_creation_date' => $user_account_creation_date,
            ':is_verified' => $is_verified
            ]);
        } catch (PDOException $e) {
            error_log("Signup handler: failed to insert new user: " . $e->getMessage());
            $utility_class->jsonError('Server error while creating user account.', 500);
        }

        if ($result) {
            // Set cookies for session
            // AUTHENTICATION: STORE IN SESSION (SERVER-SIDE)
            $_SESSION['user_id'] = $user_id;
            //  We use 'session_token' in the session for consistency with google_oauth_handler.php
            $_SESSION['session_token'] = $session;
            $_SESSION['logged_in'] = true;
            // Determine secure cookie settings
            $is_secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $cookie_lifetime = time() + 60 * 60 * 24 * 7; // 7 days expiration

            // Set the secure cookies (Secondary/Persistence)
            setcookie('auth_uid', $user_id, [
                'expires' => $cookie_lifetime, 
                'path' => "/", 
                'domain' => $domain, 
                'secure' => $is_secure, 
                'httponly' => true, 
                'samesite' => 'Lax'
            ]);
            
            setcookie('session_key', $session, [
                'expires' => $cookie_lifetime, 
                'path' => "/", 
                'domain' => $domain, 
                'secure' => $is_secure, 
                'httponly' => true, 
                'samesite' => 'Lax'
            ]);
            //set default redirection page
            $target_url = INDEX_URL;
            if(isset($_SESSION['redirect_after_oauth']) && !empty($_SESSION['redirect_after_oauth'])){
                // usee the stored URL
                $target_url = $_SESSION['redirect_after_oauth']; // still use redirect_after_oauth instead of declaring another for signup
                //clear redirect session immediately
                unset($_SESSION['redirect_after_oauth']);
            }
            // Critical Security: Prevent Session Fixation
            session_regenerate_id(true);
            rotateCsrfToken();
            echo json_encode([
                'success' => true,
                'message' => 'Registration successful! You will be redirected shortly.',
                'redirect' => $target_url,
                'csrf_token' => htmlspecialchars(getCsrfToken())
            ]);
        } else {
            http_response_code(500);
            error_log("Database insertion error in signup handler: " . implode(" | ", $stmt->errorInfo()));
            $redirect_url =  ERROR_URL . "?code=500&message=" . urlencode('A system error has occurred. We are sorry for the inconvenience.');
            $utility_class->jsonError('An unexpected error occured. Please try again.', 500, ['redirect' => $redirect_url]);
        }
    } else {
        // User already exists, DO NOT LOG THEM IN!
        http_response_code(409); // Conflict status code is appropriate
        error_log("Attempted signup with existing email: " . htmlspecialchars($email));
        $utility_class->jsonError('This email address is already registered. Please use the **Login** page.', 409);
    }
} catch (Exception $e) {
    error_log("Server error: " . $e->getMessage());
    $redirect_url =  ERROR_URL . "?code=500&message=" . urlencode('A system error has occurred. We are sorry for the inconvenience.');
    header("Location: $redirect_url");
    $utility_class->jsonError('Unknown Server error', 500, ['redirect' => $redirect_url]);
}
?>
