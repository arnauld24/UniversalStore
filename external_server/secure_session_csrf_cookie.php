<?php
// Define the security constant if it hasn't been defined yet.
if (!defined('APP_INIT')) {
       define('APP_INIT', true);
}

// Require Composer autoload — this repository now uses Composer as the single
// canonical autoloader. The old `autoloader/autoload.php` fallback has been
// removed, so ensure `vendor/autoload.php` is present (run `composer dump-autoload`).
$vendorAutoload = dirname(__DIR__, 1) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    throw new \RuntimeException('Composer autoload not found. Run "composer install" or "composer dump-autoload" in project root.');
}

require_once __DIR__ . '/../config/config.php';

// Function to check if IP is banned
function isIpBanned($ip) {
    $bannedFile = __DIR__ . '/../banned_ips.txt';
    if (!file_exists($bannedFile)) {
        return false;
    }
    $bannedIps = file($bannedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($ip, $bannedIps);
}

// Function to ban IP
function banIp($ip) {
    $bannedFile = __DIR__ . '/../banned_ips.txt';
    if (isIpBanned($ip)) {
        return; // already banned
    }
    file_put_contents($bannedFile, $ip . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Check if current IP is banned
// When running from CLI (php -f ...) skip IP/session cookie header operations which would emit warnings.
if (PHP_SAPI === 'cli' || php_sapi_name() === 'cli' || empty($_SERVER['REMOTE_ADDR'])) {
    // CLI environment - do not perform IP ban checks or attempt to change session cookie params
    // Ensure $_SESSION exists to avoid undefined variable notices in later helper functions
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }
} else {
    // Check current IP is banned
    $userIp = $_SERVER['REMOTE_ADDR'];
    if (isIpBanned($userIp)) {
        http_response_code(403);
        die("Your IP address has been banned due to security violations. Please contact support if this is an error.");
    }

    // start secure session
    if(session_status()  === PHP_SESSION_NONE){
        // Use a dynamic domain and Lax SameSite so OAuth redirects (cross-site top-level GETs)
        // will still include the session cookie. For production on HTTPS, set 'secure' => true
        $cookieDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $secureFlag =  isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $secureFlag,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}
// prevent session hijacking
/*
function normalize_ip($ip) {
    if ($ip === '::1') return '127.0.0.1';
    return $ip;
}
if(!isset($_SESSION['ip_address'])){
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];

}
else{
    if($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] ||
     $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']){
        banIp($_SESSION['ip_address']); // Ban the IP on session hijacking
        session_unset();
        session_destroy();
        die("Session hijacking attempt detected. Your IP has been banned.");
    }
}
*/

//function to rotate csrf token periodically
function rotateCsrfToken(): string{
    $newToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $newToken;
    return $newToken;
}

//CSRF token generation
if(empty($_SESSION['csrf_token'])){
    rotateCsrfToken();
}

// Get CSRF token
function getCsrfToken(){
    if(empty($_SESSION['csrf_token'])){
        rotateCsrfToken();
    }
    return $_SESSION['csrf_token'];
}
// AJAX CSRF TOKEN Generation
function getajaxcsrftoken(){
     $token_from_json = bin2hex(random_bytes(32));
     $_SESSION['ajax_csrf_token'] = $token_from_json;
     return $_SESSION['ajax_csrf_token'];
    }
// Validate csrf Token
function validateCsrfToken($token){

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);

}

//validate csrf Token by POST,GET 
 function validateGoogleCsrfToken(){
    if(!isset($_GET['state'])){
    http_response_code(400);
    header("Location: core/error_page.php?code=400&message= '" . urlencode('missing important parameters. Please try again'));
    exit();
}
    $token = $_GET['state'];
    return isset($_SESSION['oauth_state']) && hash_equals($_SESSION['oauth_state'], $token);
 }

 //Ajax token validator (from header for Simple login and signup form)
 function validateAjaxCsrfToken(){
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    return validateCsrfToken($token);
}

// Ajax token validator (fromheader for user scontext data)
function validateAjax_CsrfToken(){
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    return isset($_SESSION['ajax_csrf_token']) && hash_equals($_SESSION['ajax_csrf_token'], $token);
}

// Ajax form csrf token verification (frombody)
function verifyAjax_formCsrfToken(){
    $csrf_token = $_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? '';
    return !empty($csrf_token) && hash_equals($_SESSION['csrf_token'], $csrf_token);
}


// // Ajax_form token validator (fromheader)
function validateAjax_formCsrfToken(){
    $csrf_token = $_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if(empty($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)){
        if(is_ajax()){
            header('Content-Type: application/json');
            //rotate csrf_token for security
            rotateCsrfToken();
            echo json_encode(['success' => false , 'message' => 'CSRF token is invalid.', 'csrf_token' => htmlspecialchars(getCsrfToken())] );
                exit;
            } else {
                header('HTTP/1.1 403 Forbidden');
                die('CSRF token invalid.');
            }
        }
    }

// Limiting Add to cart request per session
 function countAddToCartRequests(){
    if(!isset($_SESSION['add_to_cart_count'])){
        $_SESSION['add_to_cart_count'] = 0;
    }
    $_SESSION['add_to_cart_count']++;
    if($_SESSION['add_to_cart_count'] > 100){ // limit to 100 adds per session
        if(is_ajax()){
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Too many add to cart requests. Please try again later.']);
            exit;
        } else {
            header('HTTP/1.1 429 Too Many Add To Cart Requests');
            die('Too many add to cart requests.');
        }
    }
    return $_SESSION['add_to_cart_count'];
 }

// Limiting Remove product request per session
 function countRemoveProductRequests(){
    if(!isset($_SESSION['remove_product_count'])){
        $_SESSION['remove_product_count'] = 0;
    }
    $_SESSION['remove_product_count']++;
    if($_SESSION['remove_product_count'] > 100){ // limit to 100 removes per session
        if(is_ajax()){
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Too many remove requests. Please try again later.']);
            exit;
        } else {
            header('HTTP/1.1 429 Too Many Remove Requests');
            die('Too many remove requests.');
        }
    }
    return $_SESSION['remove_product_count'];
 }

// secure cookie creation

function setSecureCookie($name, $value, $expire = 3600){
    $domain = ($_SERVER['HTTP_HOST'] === 'localhost') ? '' : $_SERVER['HTTP_HOST'];
    setcookie($name, $value, [
        'expires' => time() + $expire,
        'path' => '/',
        'domain' => $domain,
        'secure' => false, // set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'strict'
    ]);
}

// secure cookie retrieval
function getSecureCookie($name){
    return $_COOKIE[$name] ?? null;
}


 // Enforce HTTPS for use later if needed
/*
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}
*/

//PHP Code for Secure Error Logging

// 1. Disable display of errors in browser
ini_set('display_errors', 'Off');
ini_set('display_startup_errors', 'Off');

// 2. Enable error logging to file
ini_set('log_errors', 'On');

// 3. Set the path for the error log file
// Make file path NOT accessible via the web browser.
$error_log_file = __DIR__ . '/../error_logs/php_errors.log';
ini_set('error_log', $error_log_file);

// 4. Set comprehensive error reporting level
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

// 5. Create error_logs directory if it doesn't exist
$error_logs_dir = __DIR__ . '/../console_error_fallback';
if (!file_exists($error_logs_dir)) {
    mkdir($error_logs_dir, 0755, true);
}

// 6. Function to log JavaScript errors via AJAX
function logJavaScriptError($message, $url, $line, $column, $error_type = 'javascript') {
    $error_logs_dir = __DIR__ . '/../error_logs';
    $js_error_log = $error_logs_dir . '/js_errors.log';

    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $log_entry = "[$timestamp] [$error_type] IP: $ip | UA: $user_agent | URL: $url | Line: $line | Col: $column | Message: $message" . PHP_EOL;

    error_log($log_entry, 3, $js_error_log);
}

// 7. Handle AJAX requests for JavaScript error logging
if (isset($_POST['js_error']) && isset($_POST['js_url']) && isset($_POST['js_line'])) {
    $message = base64_decode($_POST['js_error'] ?? '');
    $url = $_POST['js_url'] ?? '';
    $line = $_POST['js_line'] ?? '';
    $column = $_POST['js_column'] ?? '';
    $error_type = $_POST['js_error_type'] ?? 'javascript';

    logJavaScriptError($message, $url, $line, $column, $error_type);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Error logged successfully']);
    exit;
}

// 8. Add headers to prevent console errors from displaying
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');


// 9. Function to get JavaScript error logging script
function getJavaScriptErrorHandler() {
    return "
<script>
// JavaScript Error Handler - Prevents console errors from displaying
//Define Base Path for server 
const internal_server = '<?php echo INTERNAL_SERVER_URL ?>';
(function() {
    // Store original console methods
    const originalConsole = {
        error: console.error,
        warn: console.warn,
        log: console.log,
        info: console.info,
        debug: console.debug
    };

    // Override console methods to log to server instead of displaying
    function logToServer(errorType, args) {
        const message = Array.from(args).map(arg =>
            typeof arg === 'object' ? JSON.stringify(arg) : String(arg)
        ).join(' ');

        const errorData = new FormData();
        errorData.append('js_error', btoa(message));
        errorData.append('js_url', window.location.href);
        errorData.append('js_line', '0');
        errorData.append('js_column', '0');
        errorData.append('js_error_type', errorType);

        // Send error to server via AJAX
        fetch(internal_server + '/log_js_error.php', {
            method: 'POST',
            body: errorData
        }).catch(() => {
            // Silently fail if AJAX request fails
        });
    }

    // Override console methods
    console.error = function(...args) {
        logToServer('error', args);
        // Optionally still log to original console for debugging (remove in production)
        // originalConsole.error.apply(console, args);
    };

    console.warn = function(...args) {
        logToServer('warning', args);
        // originalConsole.warn.apply(console, args);
    };

    console.log = function(...args) {
        logToServer('log', args);
        // originalConsole.log.apply(console, args);
    };

    // Handle unhandled JavaScript errors
    window.addEventListener('error', function(event) {
        const errorData = new FormData();
        errorData.append('js_error', btoa(event.message));
        errorData.append('js_url', event.filename);
        errorData.append('js_line', event.lineno);
        errorData.append('js_column', event.colno);
        errorData.append('js_error_type', 'unhandled_error');

        fetch(internal_server + '/log_js_error.php', {
            method: 'POST',
            body: errorData
        }).catch(() => {
            // Silently fail
        });
    });

    // Handle unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        const errorData = new FormData();
        errorData.append('js_error', btoa(String(event.reason)));
        errorData.append('js_url', window.location.href);
        errorData.append('js_line', '0');
        errorData.append('js_column', '0');
        errorData.append('js_error_type', 'unhandled_promise_rejection');

        fetch(internal_server + '/log_js_error.php', {
            method: 'POST',
            body: errorData
        }).catch(() => {
            // Silently fail
        });
    });
})();
</script>";
}

// 10. Function to output JavaScript error handler
function outputJavaScriptErrorHandler() {
    echo getJavaScriptErrorHandler();
}


?>
