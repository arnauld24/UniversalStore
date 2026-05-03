<?php

use core\GoogleAuthController;

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

require_once __DIR__ . '/../../../config/config.php';
require_once CORE_PATH . '/google_oauth_handler.php';
require_once INIT_PATH . '/oauth_config.php';
// use absolute path based on this file to reliably include the secure session helper
// secure session helper lives at project_root/server/secure_session_csrf_cookie.php
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';


try{
// check if code is send by google after login with account
try{
if(isset($_GET['code'])){
    $token = $gClient->fetchAccessTokenWithAuthCode($_GET["code"]);
}
// If there is no code redirect to Authentication page
// else{
//   header("Location: authentication.php");
//   exit();
// }
}catch (Exception $e) {
    error_log("Google Token Exchange Error: " . $e->getMessage()); 
    header("Location: " . AUTHENTICATION_URL);
    $_SESSION['google_error'] = 'Authentication failed: Invalid code or token exchange failed.';
    exit();
}

// verify if the code aand state are present in the url return by google
if (!isset($_GET['code']) || !isset($_GET['state'])) {
    http_response_code(400);
    $redirect_url =  ERROR_URL . "?code=400&message= " . urlencode('missing important parameters. Please try again');
    header("Location: $redirect_url");
}

$state_from_google = $_GET['state'];

// verify Stored State in session exist
if (!isset($_SESSION['oauth_state'])) {
  // If no stored state in session, try to recover it from temporary oauth_state cookie
  $cookieState = $_COOKIE['oauth_state'] ?? null;
  if ($cookieState) {
    // restore into session for verification below
    $_SESSION['oauth_state'] = $cookieState;
  } else {
    // if no stored state exist possible suspect request
    error_log("Google OAuth Handler: Missing session state. Possible CSRF Tentative. Suspect request");
    http_response_code(400);
    $redirect_url =  ERROR_URL . "?code=400&message=" . urlencode('session expired. Please try again');
    header("Location: $redirect_url");
    exit();
  }
}

$state_from_session = $_SESSION['oauth_state'];

// verify stored state match
if ($state_from_google !== $state_from_session) {
    // If store state don't match possible CSRF attack
    http_response_code(400);
    var_dump($_SESSION['oauth_state']);
    error_log("Google OAuth Handler: State mismatch. CSRF attempt detected. Ban user immediately");
    $redirect_url =  ERROR_URL . "?code=400&message= " . urlencode('CSRF attempt detected. Ban User');
    header("Location: $redirect_url ");
    exit();
}
// destroy state after reading
unset($_SESSION['oauth_state']);

// Retrieve data send by ajax store in session 
$context_data = $_SESSION['context_data'] ?? [];
unset($_SESSION['context_data']);  // destroy after use

//initialise variables with context data
$timezone = $context_data['timezone'] ?? 'UTC';
$deviceType = $context_data['devicetype'] ?? 'Unknown';
$locale = $context_data['locale'] ?? 'en-US';

 try {
// verify if user's token is valid/correct or trying to hack
if(!isset($token["error"]) || $token["error"] !== "invalid_grant"){
   
        // initializes the Google OAuth2 service
        $oAuth = new Google\Service\Oauth2($gClient);
        // Fetch user's basic profile info after the login & grant permission
        $userData = $oAuth->userinfo_v2_me->get();

        //insert data
        $controller = new GoogleAuthController;
        $controller->insertData(array(
          'email' => $userData['email'],
          'avatar' => $userData['picture'],
          'familyName' => $userData['familyName'],
          'givenName' => $userData['givenName'],
          'id' => $userData['id'],
          'tz' => $timezone, 
          'dt' => $deviceType,
          'lc' => $locale,
          'csrf_token' => getCsrfToken()
        ));
    } // redirect to authentication if error
else{
  $_SESSION['google_error'] = 'Google authentication failed due to an error. Please try again';// (invalid Token)
  header("Location: " . AUTHENTICATION_URL);
  exit();
}
}catch (Exception $e) {
        error_log("Google OAuth Error: " . $e->getMessage());
        $_SESSION['google_error'] = 'Google authentication failed. Please try again.';
        header("Location: " . AUTHENTICATION_URL);
        exit();
    }
  }catch(PDOException $e){
        error_log("Google OAuth Error". $e->getMessage());
        http_response_code(500);
        $redirect_url =  ERROR_URL . "?code=500&message=" . urlencode('Server Request Error or No Authorisation.');
        header("Location: $redirect_url");
  }catch(Exception $e){
    error_log("Unauthorized page landing ". $e->getMessage());
    header("Location: " . AUTHENTICATION_URL);
  }
  catch(Throwable $e){
    error_log("General authentication error in verify url:" . $e->getTraceAsString());
    header("Location: " . AUTHENTICATION_URL);
  }



?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account - Verification || UniversalStore</title>
  
  <!-- custom css file -->
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">

</head>
<body>
  
  

</body>
</html>