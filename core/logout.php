<?php

namespace core ;
use external_server\Connect;
use Exception;
use PDOException;

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
// Ensure secure session helper is loaded; it will register the autoloader.
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
require_once __DIR__ . '/../config/config.php';
try{
 class logout{

  public function logout_user(){
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
        // logged_in status to false
        $connect = new connect();
        $logged_in = boolval(false);
        $stmt = $connect->prepare("UPDATE users SET logged_in = :logged_in WHERE user_id = :user_id AND user_session = :session");
        $stmt->execute([':logged_in' => $logged_in, ':user_id' => $_SESSION['user_id'],':session' => $_SESSION['session_token']]);
    //immediately generate new session id
    session_regenerate_id(true);
    // Destroy the session
    session_unset();
    session_destroy();
    
    //unset cookie
    // Set cookie parametersfor unset: name, value, expire, path, domain, secure, httponly
    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; // Checks if the connection is HTTPS
    setcookie('auth_uid', '',[
    'expires' => time() - 60 * 60 * 24 * 30,
    'path' => "/",
    'domain' => $domain,
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax' // samesite addition for CSRF protection
]);
    setcookie('session_key', '', [
                'expires' => time() - 60 * 60 * 24 * 30, // 30 days
                'path' => "/",
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        // Also destroy oauth_state so it can regenearate
    setcookie('oauth_state', '',[
        'expires' => time() - 600, // 10 minutes
        'path' => "/",
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
   
// Capture referer url (Page before logout)
if (isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
    // Check to ensure the referrer isn't the login/logout page itself
    if (strpos($_SERVER['HTTP_REFERER'], 'authentication.php') === false && strpos($_SERVER['HTTP_REFERER'], 'logout.php') === false) {
         $_SESSION['redirect_after_oauth'] = $_SERVER['HTTP_REFERER'];
    }
}

    header("Location: " . AUTHENTICATION_URL);
    die();
        }
     }
}catch(PDOException $e){
    http_response_code(500);
    error_log("Database error while updating logged_in status: " . $e->getMessage());
    die();
}
catch(Exception $e){
    http_response_code(500);
    error_log("An error occured while logging out:" . $e->getMessage());
    die();
}
?>