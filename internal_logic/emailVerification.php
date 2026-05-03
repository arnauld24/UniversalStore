<?php 

require_once __DIR__ . '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use internal_logic\otpVerification;
use core\Controller;

// AUTHENTICATION & AUTHORIZATION

$authenticatedUserId = Controller::getAuthenticatedUserId();

// Function to detect ajax/json request
if(!$authenticatedUserId){
    if(is_ajax()){
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}else{
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

//Function to send JSON response
function send_json_response($success, $message){
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
    header('Location: '. INDEX_URL);
    exit;
}

}
//Force script to detect ajax/json request
//helper to detect ajax

function is_ajax(){
     if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return true;
    }
    // Additional detection to accept an explicit ajax flag form POST/GET/FormData
    if (!empty($_REQUEST['ajax']) && (string)$_REQUEST['ajax'] === '1') {
        return true;
    }
    return false;
}

// Safety: if ajax request contains opt_key
// Require AJAX and a form identifier (otp_key_flag). Do NOT return early; set $otp_key for later processing.
if (!is_ajax()) {
    // Not an AJAX request, redirect to profile page
    http_response_code(403);
    header('Location: ' . INDEX_URL);
    exit("direct access not allowed");
}

// At this point it's an AJAX request. Ensure form identifier is present.
// Parse JSON input if content-type is application/json
$input_data = [];
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $input_data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_json_response(false, "Invalid JSON data.");
    }
}

// Extract otp_key_flag from input data or $_REQUEST
if (isset($input_data['otp_key_flag']) && !empty($input_data['otp_key_flag'])) {
    $otp_key = (string) $input_data['otp_key_flag'];
} elseif (isset($_REQUEST['otp_key_flag']) && !empty($_REQUEST['otp_key_flag'])) {
    $otp_key = (string) $_REQUEST['otp_key_flag'];
} else {
    send_json_response(false, "Missing form identifier. Request denied.");
}

if (!empty($otp_key)) {
    // validate csrfToken
    validateAjax_formCsrfToken();

    // Extract OTP fields from input_data or INPUT_POST
    $otp1 = isset($input_data['otp1']) ? htmlspecialchars($input_data['otp1'], ENT_QUOTES, 'UTF-8') : htmlspecialchars(filter_input(INPUT_POST, 'otp1') ?? '', ENT_QUOTES, 'UTF-8');
    $otp2 = isset($input_data['otp2']) ? htmlspecialchars($input_data['otp2'], ENT_QUOTES, 'UTF-8') : htmlspecialchars(filter_input(INPUT_POST, 'otp2') ?? '', ENT_QUOTES, 'UTF-8');
    $otp3 = isset($input_data['otp3']) ? htmlspecialchars($input_data['otp3'], ENT_QUOTES, 'UTF-8') : htmlspecialchars(filter_input(INPUT_POST, 'otp3') ?? '', ENT_QUOTES, 'UTF-8');
    $otp4 = isset($input_data['otp4']) ? htmlspecialchars($input_data['otp4'], ENT_QUOTES, 'UTF-8') : htmlspecialchars(filter_input(INPUT_POST, 'otp4') ?? '', ENT_QUOTES, 'UTF-8');
    $otp5 = isset($input_data['otp5']) ? htmlspecialchars($input_data['otp5'], ENT_QUOTES, 'UTF-8') : htmlspecialchars(filter_input(INPUT_POST, 'otp5') ?? '', ENT_QUOTES, 'UTF-8');
    $otp6 = isset($input_data['otp6']) ? htmlspecialchars($input_data['otp6'], ENT_QUOTES, 'UTF-8') : htmlspecialchars(filter_input(INPUT_POST, 'otp6') ?? '', ENT_QUOTES, 'UTF-8');

    if(!isset($otp1) || !isset($otp2) || !isset($otp3) || !isset($otp4) || !isset($otp5) || !isset($otp6)){
        send_json_response(false, 'All fields are required.');
    }else{

         $otp_code = $otp1.$otp2.$otp3.$otp4.$otp5.$otp6;
        // initiate the OtpVerification
        $otpVerification = new otpVerification();
        $result = $otpVerification->verifyOtp($user_id, $email, $otp_code);
        if($result === true){
            if(is_ajax()){
         header('Content-Type: application/json');
         rotateCsrfToken();
         echo json_encode([
            'success' => true, 
            'message' => "Email verified Successfully",
            'csrf_token' => htmlspecialchars(getCsrfToken()),
            'redirect' => PROFILE_URL
    ]);
    exit;
}else{
    header('Location: '. INDEX_URL);
    exit;
}
        }else{ // DB Error Response
            if(is_string($result)){
            send_json_response(false, $result);
          }else{
             // default error handling
            send_json_response(false, 'An unexpected error occured. Please try again later.');
            }
        }
    }
}

?>