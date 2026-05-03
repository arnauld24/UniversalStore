<?php
// Define BASE PATH and URL - Protected against duplicate defines

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// --- DYNAMIC BASE URL LOGIC ---
// This part detects your IP address so the site works on your phone
// if (!defined('BASE_URL')) {
//     $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
//     $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost'; // This will be 'localhost' or '192.168.x.x'
    
//     // We combine the protocol, the host (IP), and your project folder
//     define('BASE_URL', $protocol . $host . '/PHPPROJECT');
// }
if (!defined('BASE_URL')) {
    // 1. Detect Protocol (Check if Packetriot is sending HTTPS)
    $protocol = "http://";
    if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) {
        $protocol = "https://";
    }

    $host = !empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // 2. Use the FULL path to your project folder
    define('BASE_URL', $protocol . $host . '/PHPPROJECT');
}

if (!defined('UNIVERSALSTORE_PATH')) define('UNIVERSALSTORE_PATH', BASE_PATH . '/universalstore_managementsystem');
if (!defined('SERVER_PATH')) define('SERVER_PATH', BASE_PATH . '/external_server');
if (!defined('INTERNAL_LOGIC_PATH')) define('INTERNAL_LOGIC_PATH', BASE_PATH . '/internal_logic');
if (!defined('DASHBOARD_PATH')) define('DASHBOARD_PATH', BASE_PATH . '/universalstore_managementsystem/dashboard');
if (!defined('ACCOUNT_PATH')) define('ACCOUNT_PATH', BASE_PATH . '/universalstore_managementsystem/account/authentication');
if (!defined('CORE_PATH')) define('CORE_PATH', BASE_PATH . '/core');
if (!defined('INIT_PATH')) define('INIT_PATH', BASE_PATH . '/config');
if (!defined('TEMPLATES_PATH')) define('TEMPLATES_PATH', BASE_PATH . '/templates');
if (!defined('INTERNAL_SERVER_PATH')) define('INTERNAL_SERVER_PATH', BASE_PATH . '/universalstore_managementsystem/internal_server');

// All URLs below now inherit the IP address from BASE_URL
if (!defined('universalstore_URL')) define('universalstore_URL', BASE_URL . '/universalstore_managementsystem');
if (!defined('INDEX_URL')) define('INDEX_URL', BASE_URL . '/universalstore_managementsystem/index');
if (!defined('CART_URL')) define('CART_URL', BASE_URL . '/universalstore_managementsystem/cart');
if (!defined('DASHBOARD_URL')) define('DASHBOARD_URL', BASE_URL . '/universalstore_managementsystem/profile?tab=profile');
if (!defined('PROFILE_URL')) define('PROFILE_URL', BASE_URL . '/universalstore_managementsystem/dashboard/profile');
if (!defined('VERIFY_ACCOUNT_URL')) define('VERIFY_ACCOUNT_URL', BASE_URL . '/universalstore_managementsystem/account/authentication/otp_verification');
if (!defined('AUTHENTICATION_URL')) define('AUTHENTICATION_URL', BASE_URL . '/universalstore_managementsystem/account/authentication/authentication');
if (!defined('ERROR_URL')) define('ERROR_URL', BASE_URL . '/universalstore_managementsystem/account/authentication/core/error_page.php');
if (!defined('ASSETS_URL')) define('ASSETS_URL', BASE_URL . '/universalstore_managementsystem/assets');
if (!defined('SERVER_URL')) define('SERVER_URL', BASE_URL . '/external_server');
if (!defined('INTERNAL_LOGIC_URL')) define('INTERNAL_LOGIC_URL', BASE_URL . '/internal_logic');
if (!defined('INTERNAL_SERVER_URL')) define('INTERNAL_SERVER_URL', BASE_URL . '/universalstore_managementsystem/internal_server');
if (!defined('CORE_URL')) define('CORE_URL', BASE_URL . '/core');
?>