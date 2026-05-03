<?php

include '../../server/connection.php';
require_once 'google_oauth_handler.php';
// header for security and JSON
header('Content-Type: application/json');


// Deny direct access if it's not a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
// Check if JSON decoding was successful and data is present
if ($data === null) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data.']);
    exit;
}
// // Generate UserID
// function generateUserid($length){
//     try{
//     $random_bytes = random_bytes($length);
//     $user_id = bin2hex($random_bytes);
//     return $user_id;
//     }
//      catch(Exception $e){
//             // Handle the exception if random_bytes() fails
//             error_log("Error generating User ID: " . $e->getMessage());
//             return null;
//           }
//  }
//  // generating sessionid
//  function generateCode($length){
//             try{
//             // Generate $length bytes of cryptographically secure random data
//             $random_bytes = random_bytes($length);
//             // Convert the bytes to a hex string for storage (e.g., 32 bytes -> 64 characters)
//             $session = bin2hex($random_bytes);

//             return $session;
//           }
//           catch(Exception $e){
//             // Handle the exception if random_bytes() fails
//             error_log("Error generating Session ID: " . $e->getMessage());
//             return null;
//           }
//         }

//check form data , validate and sanitize

if(!isset($data['email'],$data['signuppassword'], $data['fname'], $data['lname'])){
     http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}
$user_id = $this->generateUserid(20);
$email = filter_var(trim($data['email']));
$password = trim($data['signuppassword']);
$fname = filter_var(trim($data['fname']), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
$lname = filter_var(trim($data['lname']), FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);


if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    http_response_code(422); // Unprocessable Entity
    echo json_encode(['success' => false, 'message' => 'Invalid email or password minimum length(8).']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
    exit;
}
// hash password for security
$hashed_password = password_hash($password,PASSWORD_DEFAULT);

// Create new user
$connect = new Connect;

 // check if email exist when inserting data
    $stmt = $connect->prepare("SELECT * FROM users WHERE user_email = :email");
    $stmt->execute([':email' => $data['email']]);

    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$info["user_id"]){
    
    $stmt = $connect->prepare("INSERT INTO users (user_id,fname,lname,email,signuppassword,user_session) VALUES (:user_id,:user_fname,:user_lname,:user_email,:user_password,:user_session)");
    $stmt->execute([
        ':user_id' => $user_id,
        ':fname' => $fname,
        ':lname' => $lname,
        ':email' => $email,
        ':signuppassword' => $hashed_password,
        ':user_session' => $session
    ]);
     echo json_encode([
        'success' => true, 
        'message' => 'Registration successful! You will be redirected shortly.',
        'redirect' => '../../index.php'
    ]);
        if($stmt){
            setcookie('user_id', $user_id, time()+60*60*24*30, "/", "");
            setcookie('user_session', $session, time()+60*60*24*30, "/", "");
            header("Location: authentication.php");
                exit();
        }else{
            return "Error  inserting user data.";
        }
    }else{
        setcookie('user_id', $info['user_id'], time()+60*60*24*30, "/", "");
                setcookie('user_session', $info['user_session'], time()+60*60*24*30, "/", "");
                header("Location: authentication.php");
                exit();
    }

           
?>