<?php
// Define the security constant if it hasn't been defined yet.
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
 // set header to recieve json/ajax request  
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
 
 // verify request is post
        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            //verify content-type is JSON
            if (stripos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
                http_response_code(415); // Media/Data type not supported
                $redirect_url =  ERROR_URL . "?code=415&message=" . urlencode("Media/Data type not supported");
                header("Location: $redirect_url");
                error_log("Unsupported Media Type. Expected application/json.");
                echo json_encode(['success' => false, 'message' => 'Server Error. Please try again']);
                exit;
            }
        // enable json receive 
         $data = json_decode(file_get_contents("php://input"), true);
    // Check if JSON decoding was successful and data is present
    if (json_last_error() !== JSON_ERROR_NONE || $data === null) {
        http_response_code(406); // Bad Request
        error_log("invalid JSON data received.");
        $redirect_url =  ERROR_URL . "?code=406&message= " .urlencode('Unknown Server Error. Warning');
        header("Location: $redirect_url");
        echo json_encode(['success' => false, 'message' => 'An error occured while processing']);

        exit;
    }

    //verify specific action from json
    if(($data['action'] ?? null) === 'store_context'){
    //    $csrf_token = $data['csrf_token'] ?? '';
      if(!validateAjax_CsrfToken()){
            http_response_code(403);
            error_log("CSRF token is invalid.");
            echo json_encode(['success' => false, 'message' => 'Session expired. Please reload page and try again ']); //CSRF TOKEN INVALID
            exit;
        }
        //retrieve user context data 
        $submittedTimezone = $data['tz'] ?? 'UTC'; 
        $submittedDevicetype = $data['dt'] ?? 'Unknown';
        $submittedLocale = $data['lc'] ?? 'en-US';
        // Validate and sanitize timezone , devicetype and locale
            // Validate timezone
            $validTimezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
            if (in_array($submittedTimezone, $validTimezones, true)) {
                $timezone = $submittedTimezone;
            } else {// use default if invalid 
                $timezone = 'UTC';  }
            // Sanitize device type
            $devicetype = filter_var(trim($submittedDevicetype), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (empty($devicetype)) {
            $devicetype = 'Unknown';}
            // Validate locale format (e.g., en, en-US, fr, fr-CA)
            if (preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $submittedLocale)) {
                $locale = filter_var(trim($submittedLocale), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            } else {
                // use default if invalid
                $locale = 'en-US';}
            
        if (!empty($timezone) && !empty($devicetype) && !empty($locale)) {
         $_SESSION['context_data'] = [
            'timezone' => $timezone,
            'devicetype' => $devicetype,
            'locale' => $locale
        ];

        // If success return 200(ok)
        http_response_code(200);
        error_log("context data stored successfully");
        echo json_encode(['success' => true, 'message' => 'Context data stored successfully.']);
        exit;
    }
        else {
            http_response_code(200);
            error_log("failed to store context data ");
            echo json_encode(['success' => true, 'message' => 'An error occured.Try again.']);
        }
    }
      else {
            http_response_code(200);
            error_log("failed to store context data ");
            echo json_encode(['success' => true, 'message' => 'Failed to continue action.']);
        }
    }
      else{
            http_response_code(405); // unauthorized method
            error_log("Request_Method: Unauthorized method.(POST) required only". $_SERVER['RESQUEST_METHOD']);
            $redirect_url =  ERROR_URL . "?code=405&message=". urlencode("Warning Method strictly not allowed");
            header("Location: $redirect_url");
            echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
            exit;
      }
?>