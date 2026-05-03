<?php

// File handling core app logic and services (session management, logged_in users and visitor tracking etc)

// Define the security constant if it hasn't been defined yet.
define('APP_INIT', true);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// // Enforce HTTPS in production
// if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
//     // For localhost, allow HTTP; for production, redirect to HTTPS
//     if ($_SERVER['HTTP_HOST'] !== 'localhost' && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false) {
//         header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
//         exit;
//     }
// }
// Enforce HTTPS but check for Proxy/Tunnel headers
$is_https = false;
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $is_https = true;
} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $is_https = true;
}

if (!$is_https) {
    if ($_SERVER['HTTP_HOST'] !== 'localhost' && strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false) {
        header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}
// Load secure session helper early. It registers the project autoloader and
// starts sessions/CSRF helpers. Do not require connection.php directly;
// classes should be autoloaded instead.
require_once __DIR__ . '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
// Require optimized tracking
require_once __DIR__ . '/../core/OptimizedTracking.php';

use core\GoogleAuthController; 
use core\logout;
use external_server\Connect;
use internal_logic\visitorManager;
use core\Controller;
use internal_logic\Cart;
use Internal_logic\getUserActiveSession;
// use core\UserTracker; // Removed old tracking
use core\OptimizedTracking;
use core\utility;
// 1. AUTHENTICATION: Check if user is logged in via session or cookies.
// This also handles updating the `logged_in` status in the database.
$authController = new GoogleAuthController();

// Reuse an existing authenticated identity stored in the session when possible
// to avoid re-running the authentication routine on every request.
$authenticatedUserId = null;
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && isset($_SESSION['session_token']) && !empty($_SESSION['session_token']) ) {
    // Session already contains an authenticated user id — reuse it.
    $authenticatedUserId = $_SESSION['user_id'];
    $authenticatedUserSession = $_SESSION['session_token'];
     // Ensure persistent cookies are present and refreshed. If client-side cookies were deleted
        // after we used them to restore the session, recreate them here to extend expiry and keep
        // persistent login working.
        if(!isset($_COOKIE['auth_uid']) || $_COOKIE['auth_uid'] !== isset($_SESSION['user_id']) || !isset($_COOKIE['session_key']) || $_COOKIE['session_key'] !== $_SESSION['session_token']){
        try {
            $instance = new controller();
            // recreate or refresh cookies unconditionally to ensure correct values and expiry
            $instance->createSessionAndCookies($_SESSION['user_id'], $_SESSION['session_token']);
        } catch (\Throwable $e) {
            error_log("Warning: failed to refresh persistent cookies after cookie->session restore: " . $e->getMessage());
        }
    }
    // update user loggedIn status when there is no cookie and session 
        $logController = new GoogleAuthController();
        $logController->updateLoggedInStatus($authenticatedUserId);
} else {
    // update user loggedIn status when there is no cookie and session 
        $logController = new GoogleAuthController();
        $logController->updateLoggedInStatus($authenticatedUserId);

    // No cached authenticated user; run the authentication check which will
    // validate cookies/session against the database as needed.
    $authenticatedUserId = $authController->getAuthenticatedUserId();
    // If authentication succeeded, cache the id in session for subsequent requests.
    if (!empty($authenticatedUserId)) {
        $_SESSION['user_id'] = $authenticatedUserId;
    }
}
// CAPTURE THE CURRENT URL for redirection on authentication
// Get the current URL (protocol://host/path/to/page.php?query=...)
$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

// Store it in a session variable
$_SESSION['redirect_after_oauth'] = $current_url;

// 2. LOGOUT: Handle logout requests.
if(isset($_GET['action']) && $_GET['action'] === 'logout'){
  $logout = new logout();
  $logout->logout_user();
}

// 3. VISITOR MANAGEMENT: Track visitors, get their ID, and load their cart.
$visitorManager = new VisitorManager();
$visitorManager->trackVisitor();
$visitor_id = $visitorManager::get_visitor_id();

// Initialize server-side optimized tracking after visitor setup so the
// visitor record exists and DB schema expectations match.
OptimizedTracking::init();

// If it's a guest (not logged in) and they have a visitor ID, load their cart from the DB.
if (!$authenticatedUserId && $visitor_id) {
    $visitorManager->loadVisitorCartFromDb($visitor_id);
}

// 4. LOGGED-IN USER OPERATIONS
if ($authenticatedUserId) {
    // Alias for clarity in the rest of the script.
    $user_id = $authenticatedUserId;
    $session_token = $_SESSION['session_token'];

    // function to update user last activity time
    $activityController = new getUserActiveSession();
    $activityController->updateLastActivityTime($user_id, $session_token);

    // Link visitor to user if they just logged in.
    if ($visitor_id) {
        $isLinked = $visitorManager->checkLinkStatus($visitor_id, $user_id);
        if (!$isLinked) {
            $visitorManager->linkVisitorUser($visitor_id, $user_id);
        }
    }

    // Instantiate the cart for the logged-in user.
    $cart = new Cart($user_id);
    // Migrate any existing session cart to the database.
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        $cart->migrateSessionCartToDb($user_id);
    }
}

// Perform this functionality only if user logged in
if($authenticatedUserId){

  $cart = new Cart($authenticatedUserId);
// If user just logged in and has a session cart, migrate it now so subsequent
// operations use the DB-backed cart. This is a safe, idempotent call.
if ($cart->isLoggedIn() && !empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart->migrateSessionCartToDb($cart->getAuthenticatedUserId());
}
    $user_id = $authenticatedUserId;
  // Initialize variables to null to prevent errors if user is not logged in or data is missing.
//   $user_data = null;
//   $email = null;
//   $delivery_info = null;
//   $del_address = null;
//   $del_city = null;
//   $del_postcode = null;
//   $del_country = null;
//   $shipping_zone_id = null;

  //Functionality to get user_data
  $controller = new Controller();
  $user_data = $controller->userData($user_id);

  // Initialize utility class to use its functions 
  $utility_for_data_decryption = new utility();

  if ($user_data) {
    //Here i will take only needed data (I will add after if i need)
    $email = $user_data['user_email'] ?? null;
    $fname = $user_data['user_fname'] ?? null;
    $lname = $user_data['user_lname'] ?? null;
    $is_verified = $user_data['is_verified'] ?? null;
    // Functionality to get user Delivery information only if email is available
    if ($email) {
        $delivery_info = $controller->userDeliveryAddress($user_id, $email);
        if ($delivery_info) {
            $del_address = $utility_for_data_decryption->decryptData($delivery_info['user_address']) ?? null;
            $del_city = $delivery_info['user_city'] ?? null;
            $del_postcode = $delivery_info['user_postcode'] ?? null;
            $del_country = $delivery_info['user_country'] ?? null;
            $del_phone = $utility_for_data_decryption->decryptData($delivery_info['user_phone']) ?? null;
            $del_fname = $utility_for_data_decryption->decryptData($delivery_info['user_fname']) ?? null;
            $del_lname = $utility_for_data_decryption->decryptData($delivery_info['user_lname']) ?? null;
            $del_email = $utility_for_data_decryption->decryptData($delivery_info['user_email']) ?? null;
        }
    }

    //Functionality to get Zone ID for shipping calculation
    $cart = new Cart($user_id); // Cart object is already available, but re-instantiating is safe.
    $shipping_zone_id = null;
    if ($cart->isLoggedIn() && $del_country) { // Only calculate if we have at least a country
        $shipping_zone_id = $cart->getShippingZoneId($del_country, $del_city, $del_address, $del_postcode);
        return $shipping_zone_id;
    }
  }
}
?>
