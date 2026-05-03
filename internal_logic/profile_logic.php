<?php

// Include configuration file for BASE PATH and BASE URL
require_once __DIR__ . '/../config/config.php';

// // Require initialization file
// require_once INIT_PATH . '/init.php';
// // Define the security constant if it hasn't been defined yet.
// if (!defined('APP_INIT')) {
//     error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
//     http_response_code(403); 
//     $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
//     header("Location: $redirect_url");
//     die('403 Access Denied.'); 
// }
// include Secure session and CSRF cookie Management
require_once SERVER_PATH . '\secure_session_csrf_cookie.php';

use core\Controller;

    //Required Patterns(format)
const REGEX_NAME = '/^[a-zA-Z\s\-\.\']{2,50}$/'; // Letters, spaces, hyphens, periods, apostrophes (2-50 chars)
const REGEX_POSTCODE = '/^[a-zA-Z0-9\s\-]{4,20}$/'; // Letters, numbers, spaces, hyphens (4-20 chars)
const REGEX_ADDRESS = '/^[a-zA-Z0-9\s\-\,\.\/]{5,255}$/'; // Letters, numbers, spaces, basic punctuation (5-255 chars)
const REGEX_CITY_COUNTRY = '/^[a-zA-Z0-9\s\-\.]{2,100}$/'; // Letters, spaces, hyphens, periods (2-100 chars)
//Cameroon Phone Pattern
const REGEX_PHONE = '/^\+237(?:6(?:5[5-9]|6[0-9]|7[0-9]|8[0-9]|9[0-9])|2(?:2[0-9]|3[0-9]))[0-9]{6}$/';

//international for later use 
const REGEX_INTER_PHONE = '/^\+\d{1,4}\d{6,15}$/';
const REGEX_PASSWORD = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/';

// AUTHENTICATION & AUTHORIZATION

$authenticatedUserId = Controller::getAuthenticatedUserId();

if (!$authenticatedUserId) {
    if(is_ajax()){
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit;
    } else {
        header("Location: " . AUTHENTICATION_URL);
        exit;
    }
}

$user_id = $authenticatedUserId;
$controller = new Controller();
$user_data = $controller->userData($user_id);
if (!$user_data) {
    if(is_ajax()){
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User data not found.']);
        exit;
    } else {
        header("Location: " . AUTHENTICATION_URL);
        exit;
    }
}
$email = $user_data['user_email'];

// Force script to recieve AJax/Json request
// helper to detext AJAX request
function is_ajax() {
    // Existing Detection by header
    if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return true;
    }
    // Additional detection to accept an explicit ajax flag form POST/GET/FormData
    if(!empty($_REQUEST['ajax']) && (int)$_REQUEST['ajax'] === '1') {
        return true;
    }
    return false;
}
// Safety: if ajax request contains delivery_id and personal_info_flag
if(is_ajax()) {
    if(isset($_REQUEST['delivery_id'])){
    $delivery_id = trim($_REQUEST['delivery_id']); 
    handleDeliveryAddressUpdate($user_id, $email, $delivery_id, $controller);
}elseif (isset($_REQUEST['personal_info_flag'])) {
    // This is the Personal Information form submission
    handlePersonalInfoUpdate($user_id, $email, $controller); 
}elseif (isset($_REQUEST['password_flag'])) {
    // This is to handle Edit password form submission
    handlePasswordUpdate($user_id, $email, $controller);
}
else {
        // AJAX request, but missing a necessary form identifier
        send_ajax_response(false, 'Missing form identifier. Request denied.');
    }
}
else {
    // Not an AJAX request, redirect to profile page
    http_response_code(403);
    header('Location: ' . INDEX_URL);
    exit("direct access not allowed");
}

//function to handle AJAX errors.
function send_ajax_response(bool $success, string $message) {
    if(is_ajax()){
    header('Content-Type: application/json');
    rotateCsrfToken();
    echo json_encode([
        'success' => $success, 
        'message' => $message, 
        'csrf_token' => htmlspecialchars(getCsrfToken())
    ]);
    exit;
}else{
    header('Location: ' . PROFILE_URL);
        exit;
}
}

//Function to handle password update
function handlePasswordUpdate(string $user_id, string $email, Controller $controller){
    // Check CSRF Token
    validateAjax_formCsrfToken();

    // Sanitize and Validate Inputs
    $currentPass = htmlspecialchars(filter_input(INPUT_POST, 'currentPassword') ?? '', ENT_QUOTES, 'UTF-8');
    $newPass = htmlspecialchars(filter_input(INPUT_POST, 'newPassword') ?? '', ENT_QUOTES, 'UTF-8');
    $confirmPass = htmlspecialchars(filter_input(INPUT_POST, 'confirmPassword') ?? '', ENT_QUOTES, 'UTF-8');

    if(empty($currentPass)){
        send_ajax_response(false, 'Current password is required.');
    }elseif(empty($newPass)){
        send_ajax_response(false, 'New password is required.');
    }elseif(empty($confirmPass)){
        send_ajax_response(false, 'Confirm password is required.');
    }
    elseif($currentPass == $newPass){
        send_ajax_response(false, 'New password cannot be the same as the current password.');
    }elseif(!preg_match(REGEX_PASSWORD, $newPass)){
        send_ajax_response(false, 'At least 8 characters, including 1 uppercase, 1 lowercase and 1 number.');
    }
    elseif($newPass != $confirmPass){
        send_ajax_response(false, 'New password and confirm password do not match.');
    }
    else{
        // Initiate Controller to call the updateuserpassword function
        $result = $controller->updateUserPassword($user_id, $email, $currentPass,$newPass);
        // Success Response
        if($result === true){
            send_ajax_response(true, 'Password updated successfully.');
        } else {
            // DB Error Response
            if(is_string($result)){
            send_ajax_response(false, $result);
    }else{
        // default error handling
            send_ajax_response(false, 'An unexpected error occured. Please try again later.');
            }
        } 
    }
}

// Handles validation and update for the Personal Information form.
function handlePersonalInfoUpdate(string $user_id, string $email, Controller $controller){
    // Check CSRF Token
    validateAjax_formCsrfToken();

    // Sanitize and Validate Inputs specific to Personal Info
    $p_fname = htmlspecialchars(filter_input(INPUT_POST, 'fname') ?? '', ENT_QUOTES, 'UTF-8');
    if (strlen($p_fname) > 50) $p_fname = substr($p_fname, 0, 50);
    $p_lname = htmlspecialchars(filter_input(INPUT_POST, 'lname') ?? '', ENT_QUOTES, 'UTF-8');
    if (strlen($p_lname) > 50) $p_lname = substr($p_lname, 0, 50);
    $p_email = htmlspecialchars(filter_input(INPUT_POST, 'email') ?? '', ENT_QUOTES, 'UTF-8');
    if (!filter_var($p_email, FILTER_VALIDATE_EMAIL)) {
         send_ajax_response(false, 'Invalid email address format.');
    }
    $p_phone_full = htmlspecialchars(filter_input(INPUT_POST, 'phone') ?? '', ENT_QUOTES, 'UTF-8');
    // Validation for phone: Check if it matches a Cameroon and basic international format 
    if (!empty($p_phone_full) && !preg_match(REGEX_PHONE, $p_phone_full)) {
        send_ajax_response(false, 'Invalid phone number format.');
    }
    // Validation Checks for fname and lname
    if (empty($p_fname) || !preg_match(REGEX_NAME, $p_fname)) {
        send_ajax_response(false, 'Family name is invalid.');
    } elseif (empty($p_lname) || !preg_match(REGEX_NAME, $p_lname)) {
        send_ajax_response(false, 'Surname is invalid.');
    }
    $result = $controller->updateUserPersonalInfo($user_id, $p_email, $p_fname, $p_lname, $p_phone_full);
    
        // Success Response
        if ($result === true) {
        // Success response
        send_ajax_response(true, 'Personal information updated successfully.');
        }
    else {
        // DB Error Response
        if (is_string($result)) {
             send_ajax_response(false, $result); // Send the specific error message
        } else {
             // Generic fallback error
            send_ajax_response(false, 'An unexpected error occurred while saving. Please try again later.');
        }
    }
}

 

// Function to handle delivery address form 
function handleDeliveryAddressUpdate(string $user_id, string $email, string $delivery_id, Controller $controller){
    //check CSRF token validity
    validateAjax_formCsrfToken();
    // sanitize and validate input
   $d_fname = htmlspecialchars(filter_input(INPUT_POST, 'fname') ?? '' ,ENT_QUOTES, 'UTF-8');
   if(strlen($d_fname) > 50) $d_fname = substr($d_fname, 0, 50);
    $d_lname = htmlspecialchars(filter_input(INPUT_POST, 'lname') ?? '' ,ENT_QUOTES, 'UTF-8');
    if(strlen($d_lname) > 50) $d_lname = substr($d_lname, 0, 50);
    $d_postcode = htmlspecialchars(filter_input(INPUT_POST, 'postcode') ?? '' ,ENT_QUOTES, 'UTF-8');
    if(strlen($d_postcode) > 20) $d_postcode = substr($d_postcode, 0, 20);
    $d_address = htmlspecialchars(filter_input(INPUT_POST, 'address') ?? '' ,ENT_QUOTES, 'UTF-8');
    if(strlen($d_address) > 255) $d_address = substr($d_address, 0, 255);
    $d_city = htmlspecialchars(filter_input(INPUT_POST, 'city') ?? '' ,ENT_QUOTES, 'UTF-8'); 
    if(strlen($d_city) > 100) $d_city = substr($d_city, 0, 100);
    $d_country =  htmlspecialchars(filter_input(INPUT_POST, 'country') ?? '' ,ENT_QUOTES, 'UTF-8');
    if(strlen($d_country) > 100) $d_country = substr($d_country, 0, 100);
    $phone = htmlspecialchars(filter_input(INPUT_POST, 'phone') ?? '', ENT_QUOTES, 'UTF-8');
    
    $d_phone_full = '';
    if(!empty($phone)){
    $d_phone_full = "+237" . $phone;
        }
    
    $d_email = htmlspecialchars(filter_input(INPUT_POST, 'email') ?? '', ENT_QUOTES, 'UTF-8');
    
if (empty($d_fname)) {
    send_ajax_response(false, 'Please provide your family name.');
} elseif (!preg_match(REGEX_NAME, $d_fname)) {
    send_ajax_response(false, 'Family name contains invalid characters or is too short.');

} elseif (empty($d_lname)) {
    send_ajax_response(false, 'Please provide your surname.');
} elseif (!preg_match(REGEX_NAME, $d_lname)) {
    send_ajax_response(false, 'Surname contains invalid characters or is too short.');

} elseif (empty($d_postcode)) {
    send_ajax_response(false, '!!! Postcode empty or invalid.');
} elseif (!preg_match(REGEX_POSTCODE, $d_postcode)) {
    send_ajax_response(false, 'Postcode format is invalid.');

} elseif (empty($d_address)) {
    send_ajax_response(false, '!!! Street address empty or invalid.');
} elseif (!preg_match(REGEX_ADDRESS, $d_address)) {
    send_ajax_response(false, 'Street address format is invalid.');

} elseif (empty($d_city)) {
    send_ajax_response(false, '!!! City name empty or invalid.');
} elseif (!preg_match(REGEX_CITY_COUNTRY, $d_city)) {
    send_ajax_response(false, 'City name contains invalid characters.');

} elseif (empty($d_country)) {
    send_ajax_response(false, '!!! Country name empty or invalid.');
} elseif (!preg_match(REGEX_CITY_COUNTRY, $d_country)) {
    send_ajax_response(false, 'Country name contains invalid characters.');

} else if (!empty($d_phone_full) && !preg_match(REGEX_PHONE, $d_phone_full)) {
        send_ajax_response(false, 'Invalid phone number format.');
    } 
else if (!empty($d_email) && !filter_var($d_email, FILTER_VALIDATE_EMAIL)) {
         send_ajax_response(false, 'Invalid email address format.');
    }
else {
    // call the controller class to use its methods
    $controller = new Controller();
    if($controller->updateUserDeliveryAddress($user_id,$d_fname,$d_lname, $d_postcode, $d_address,$d_city, $d_country,$d_email, $d_phone_full)){ 
          if(is_ajax()){
            header('Content-Type: application/json');
             rotateCsrfToken();

          echo json_encode([
            'success' => true, 
            'message' => 'Delivery address updated with success.', 
            'csrf_token' => htmlspecialchars(getCsrfToken()),
            'fname' => htmlspecialchars($d_fname),
            'lname' => htmlspecialchars($d_lname),
            'postcode' => htmlspecialchars($d_postcode),
            'address' => htmlspecialchars($d_address),
            'city' => htmlspecialchars($d_city),
            'country' => htmlspecialchars($d_country), 
            'phone' => htmlspecialchars($d_phone_full),
            'email' => htmlspecialchars($d_email)  
          ]);
          exit;
}else{
    header('Location: ' . PROFILE_URL);
        exit;
}
    }else{
         send_ajax_response(false, 'An unexpected error occured. Please try again later');
    }
}
}
?>