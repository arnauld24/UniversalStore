<?php

namespace internal_logic;
require_once __DIR__ . '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use DateInterval;
use DateTime;
use external_server\Connect;
use PDO;
use Throwable;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
if(session_status() === PHP_SESSION_NONE){
    session_start();
}
class otpVerification{

    // Function to send mail
    public function send_mail($recipient,$subject, $message, $sender){

        $mail = new PHPMailer(true);
        try{
        // Server settings
        $mail->isSMTP();// Use SMTP

        $mail->SMTPDebug = 0;
        $mail->SMTPAuth = TRUE;// Enable SMTP authentication
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->Host = 'smtp.gmail.com'; 
        $mail->Username = 'fonkouaarnauld@gmail.com'; // SMTP username
        $mail->Password = 'zwwkhteflwgpycyq';// SMTP password
    
        $mail->IsHTML(true);
        $mail->AddAddress($recipient,"esteemed customer");
        $mail->SetFrom("fonkouaarnauld@gmail.com", "My websites");
        $mail->Subject = $subject;
        $content = $message;

        $mail->MsgHTML($content);
        if(!$mail->Send()){
            //echo "Error while sending Email.";
            return false;
        }else{
            //echo "Email sent successfully";
            return true ;
        }
    }catch(Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }finally{
        $mail->clearAddresses();
    }
    }

    // function to generate and send otp code
    public static function generateAndSendOtp($user_id, $email, $fname,$lname){
        try{
            $emailError = '';
            $connect = new Connect();
            // Create new  otp code 
            $otp_code = random_int(100000,999999);
            // Define the validity period
            $validity_minutes = 5;
            $current_time = new DateTime();
            $interval = new DateInterval('PT' . $validity_minutes . 'M');
            // Clone the current time and add the interval to it
            $expires_at_dt = clone $current_time;
            $expires_at_dt->add($interval);
            //format the result for insertion
            $expires_at = $expires_at_dt->format('Y-m-d H:i:s');
            $created_at = $current_time->format('Y-m-d H:i:s');
            $is_verified = boolval(false);

            // check if user exist in database to update its field
            $stmt = $connect->prepare("SELECT * FROM email_otp WHERE user_id = :user_id AND user_email = :email");
            $stmt->execute([':user_id'=> $user_id, ':email' => $email]);
            $selectstmt = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($selectstmt){
                // if exist only update the fields
                $updatestmt = $connect->prepare("UPDATE email_otp SET otp_code = :otp_code , created_at = :created_at, expires_at = :expires_at, is_verified = :is_verified WHERE user_id = :user_id AND user_email = :email ");
                $updated = $updatestmt->execute([
                    ':otp_code' => $otp_code,
                    ':created_at' => $created_at,
                    ':expires_at' => $expires_at,
                    ':is_verified' => $is_verified,
                    ':user_id' => $user_id,
                    ':email' => $email
                ]);
                $updatestmt->closeCursor();

                      //Send otpCode to user's email
                $reciever = $email;
                $subject = "Verify Account UniversalStore <$email>";
                $body = "Hii! "." $fname $lname \n Email "." $email \n Verification otp code"." $otp_code \n Please enter this code to verify your Account. \ It expires on $expires_at";
                $sender = "From: universalstore@gmail.com";
                
                if(mail($reciever,$subject,$body,$sender)){
                    error_log("email otp send.");
                    return true;
                }else{
                    $emailError = 'Error occured while sending OTP to email. Please try again later';
                    $_SESSION['emailError'] = $emailError;
                    error_log("email otp not send");
                   return false;
                } 
            }else{
            // Insert to db
            $stmt = $connect->prepare("INSERT INTO email_otp (user_id,user_email,otp_code,created_at,expires_at,is_verified) VALUES (:user_id,:user_email,:otp_code,:created_at,:expires_at,:is_verified) ");
            $stmt->execute([
                ':user_id' => $user_id,
                'user_email' => $email,
                ':otp_code' => $otp_code,
                ':created_at' => $created_at,
                ':expires_at' => $expires_at,
                ':is_verified' => $is_verified
            ]);
            $stmt->closeCursor();
            
                //Send otpCode to user's email
                $reciever = $email;
                $subject = "Verify Account UniversalStore <$email>";
                $body = "Hii! "." $fname  $lname \n Email "." $email \n Verification otp code"." $otp_code \n Please enter this code to verify your Account. \ It expires on $expires_at";
                $sender = "From: universalstore@gmail.com";

                if(mail($reciever,$subject,$body,$sender)){
                    error_log("email otp send.");
                    return true;
                }else{
                    $emailError = 'Unexpected error occured. Please try again later';
                    $_SESSION['emailError'] = $emailError;
                    error_log("email otp not send");
                   return false;
                    
                } 
            }
        }catch(Throwable $e){
            error_log("Error while generating and sending otp: " . $e->getTraceAsString());
            return false;
        }

    }
    // Function to verify Otp code
    Public function verifyOtp($user_id, $email, $otp_code){
        try{
            $connect = new Connect();
            $stmt = $connect->prepare("SELECT * FROM email_otp WHERE user_id = :user_id AND user_email = :user_email AND otp_code = :otp AND expires_at > NOW() LIMIT 1");
            $stmt->execute([ ':user_id' => $user_id, ':user_email' => $email, ':otp' => $otp_code ]);
            $result = $stmt->rowCount();
            $stmt->closeCursor();
            if($result > 0){
                //update db to verified
                $is_verified = boolval(true);
                $null_otp = null; // set otp to null 
                $expires_at = null; // set to null means he is verified

                // Start transaction to ensure data integrity and multiple insertions
                $connect->beginTransaction();
                $stmt1 = $connect->prepare("UPDATE email_otp SET is_verified = :is_verified, otp_code = :otp_code , expires_at = :expires_at WHERE user_id = :user_id AND user_email = :user_email");
                $stmt1->execute([ ':is_verified' => $is_verified, ':otp_code' => $null_otp, ':user_id' => $user_id, ':user_email' => $email , ':expires_at' => $expires_at]);
                $stmt2 = $connect->prepare("UPDATE users SET is_verified = :is_verified WHERE user_id = :user_id AND user_email = :user_email");
                $stmt2->execute([ ':is_verified' => $is_verified, ':user_id' => $user_id, ':user_email' => $email ]);
                $stmt3 = $connect->prepare("UPDATE users_session SET is_verified = :is_verified WHERE user_id = :user_id");
                $stmt3->execute([ ':is_verified' => $is_verified, ':user_id' => $user_id]);
                
                // If both operations succeeded, make the changes permanent.
                $connect->commit();

                return true;
            }else{
                return 'OTP Code invalid. Retry or Request for a new code.';
            }
        }
        catch(Throwable $e){
            //Roll back transaction if any error occurs
            if($connect->inTransaction()){
                $connect->rollBack();
            }
            error_log("Error while verifying otp: " . $e->getTraceAsString());
            return false;
        }

}

}
?>