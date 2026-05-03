<?php
// Define the security constant if it hasn't been defined yet.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    header("Location: " .ERROR_URL . "?code=403&message= " . urlencode("Access Denied."));
    die('403 Access Denied.'); 
}
require_once __DIR__ . '/config.php';
// Require the main file which handles SESSION and COOKIE management ETC
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
// Use the project's Composer autoloader google-api .
require_once __DIR__ . '/../vendor/autoload.php';

if (empty($_SESSION['oauth_state'])) {
    $oauth_state_token = getCsrfToken();
    // store token in session only if not already set
    $_SESSION['oauth_state'] = $oauth_state_token;
    // Also store a short-lived cookie copy of the OAuth state to survive cross-site redirects
    $cookieDomain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $secureFlag = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('oauth_state', $_SESSION['oauth_state'], [
        'expires' => time() + 600, // 10 minutes
        'path' => '/',
        'domain' => $cookieDomain,
        'secure' => $secureFlag,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Get the current Base URL (which we already made tunnel-aware)
// This will be "http://localhost/..." OR "https://purple-surf-51175.pktriot.xyz/..."
$redirectUri = BASE_URL . '/universalstore_managementsystem/account/authentication/verify.php';
$gClient = new Google_Client();
$gClient->setClientId("965860796397-25tbkhmncnnhmbga07qqdamn2nbdvgs5.apps.googleusercontent.com");
$gClient->setClientSecret("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");
$gClient->setApplicationName("UniversalStore - Authentication");
$gClient->setRedirectUri($redirectUri);
$gClient->addScope("https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile");

// Ensure the Google client uses the stored state (either pre-existing or newly created above)
$gClient->setState($_SESSION['oauth_state']);
$login_url = $gClient->createAuthUrl();



// verify Stored State in session exist
if (!isset($_SESSION['oauth_state'])) {
    // if no stored state exist possible suspect request
    error_log("Google OAuth Handler: Missing session state. Possible CSRF Tentative. Suspect request");
    http_response_code(400);
    $redirect_url =  ERROR_URL . "?code=400&message= " . urlencode('session expired. Please try again');
    header("Location: $redirect_url");
}



?>