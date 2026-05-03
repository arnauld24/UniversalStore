<?php
namespace Internal_logic;

use external_server\Connect;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Throwable;
use PDO;
require_once __DIR__ .  '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';


class getUserActiveSession {

    // check if user logged in and has an active session
    public function checkActiveSession($user_id, $session_token){
        try{
            $connect = new Connect();
            $stmt = $connect->prepare("SELECT * FROM users_session WHERE user_id = :user_id AND user_session = :user_session AND loggedIn_status = 1 LIMIT 1");
            $stmt->execute([':user_id' => $user_id, ':user_session' => $session_token, ':loggedIn_status' => 1]);
            $result = $stmt->fetchColumn();
            $stmt->closeCursor();
            if($result){
                return true;
            }else{
                return false;
            }
        }catch(Throwable $e){
            error_log("Error while checking user active session: " . $e->getTraceAsString());
            return false;
        }
    }

    //Function to update last activity time 
    public function updateLastActivityTime($user_id, $session_token) {
    try {
        $connect = new Connect();
        $current_time = date('Y-m-d H:i:s');
        
        $stmt = $connect->prepare("UPDATE users_session SET last_activity_time = :time WHERE user_id = :user_id AND user_session = :user_session");
        
        $stmt->execute([':user_id' => $user_id, ':user_session' => $session_token, ':time' => $current_time ]);
        
        $stmt->closeCursor(); 

    } catch (Throwable $e) {
        // Log the error but don't stop the user's request
        error_log("Error updating last activity time: " . $e->getTraceAsString());
    }
}

    // function to get time of user last activity 
    public function getLastActivityTime($user_id , $session_token){
        try{
        $connect = new Connect();
        //Get the last activity time 
        $getstmt = $connect->prepare("SELECT last_activity_time FROM users_session WHERE user_id = :user_id AND user_session = :user_session");
        $getstmt->execute([':user_id' => $user_id, ':user_session' => $session_token ]);
        $result = $getstmt->fetchColumn();
        $getstmt->closeCursor();
        if($result){
            return $result;
        }
        return null;
        }catch(Throwable $e){
            error_log("Error while getting last activity time: " . $e->getTraceAsString());
            return null;
        }
    }

    //function to store user active session data
    public function storeActiveSessionData($user_id,$session_token){
         // get user status, device , ip address, location.timezone,lastlogin, last activity
        try{
            $connect = new Connect();
            $stmt = $connect->prepare("SELECT * FROM users WHERE user_id = :user_id LIMIT 1");
            $stmt->execute([':user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
           if($result){
                $status = $result['logged_in'];
                $login_time =  $result['last_login'];
                $is_verified = $result['is_verified'];
                $time_zone = $result['time_zone'];
                $browser = 'unknown';
                $device = 'unknown';
                // get ipAddress
                $user_ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                //get userAgent data
                $get_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                // Browser and device Detection
                // Set the device parser to not parse all available devices to save memory/time
                //AbstractDeviceParser::set  Cache(false);

                // Instantiate the DeviceDetector
                $dd = new DeviceDetector($get_user_agent);
                // Parse the data
                $dd->parse();
                $output = [];
                if ($dd->isBot()) {
                // Handle bots/crawlers separately
                $output['device'] = 'Bot';
                $output['info'] = $dd->getBot();
                } else {
                    // 4. Extract structured information
                    $output['client'] = $dd->getClient('name') . ' version: ' . $dd->getClient('version'); // Chrome 126.0.0.0
                    $output['os'] = $dd->getOs('name') . ' ' . $dd->getOs('version');           // Windows 10
                    $output['device_type'] = $dd->getDeviceName();                              // desktop, smartphone, tablet
                    $output['brand'] = $dd->getBrandName();                                     // Apple, Samsung
                    $output['model'] = $dd->getModel();                                         // iPhone 15 Pro
                }
                 $device = $output['device_type'] . '(' . $output['brand'] . ' ' . $output['model'] . ' ' . $output['os'] .')' ;   
                 $browser = $output['client'];

               // we are going to use the $login_time to get the first user activity_time
               //check first if there is a user_id and session in the user_session table
               $stmt = $connect->prepare("SELECT * FROM users_session WHERE user_id =:user_id");
               $stmt->execute(['user_id' => $user_id]);
               $checkstmt = $stmt->fetch(PDO::FETCH_ASSOC);
               if($checkstmt){
                $stmt = $connect->prepare("UPDATE users_session SET user_session =:user_session, last_login=:last_login,last_activity_time=:current_activity_time,user_ip_address=:ip_address,browser=:browser,device=:device,is_verified=:is_verified,loggedIn_status=:status,time_zone =:timeZone WHERE user_id = :user_id");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':user_session' => $session_token,
                    ':last_login' => $login_time,
                    ':current_activity_time' => $login_time,
                    ':ip_address' => $user_ip_address,
                    ':browser' => $browser,
                    ':device' => $device,
                    ':is_verified' => $is_verified,
                    ':status' => $status,
                    ':timeZone' => $time_zone
                ]);
                $stmt->closeCursor();
               }else{
                // insert Active session into db 
                $stmt = $connect->prepare("INSERT INTO users_session (user_id,user_session, last_login,last_activity_time,user_ip_address,browser,device,is_verified,loggedIn_status,time_zone) VALUES(:user_id,:user_session,:last_login,:current_activity_time,:ip_address,:browser,:device,:is_verified,:status,:timeZone)");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':user_session' => $session_token,
                    ':last_login' => $login_time,
                    ':current_activity_time' => $login_time,
                    ':ip_address' => $user_ip_address,
                    ':browser' => $browser,
                    ':device' => $device,
                    ':is_verified' => $is_verified,
                    ':status' => $status,
                    ':timeZone' => $time_zone
                ]);
                $stmt->closeCursor();
                // Retrieve the ID of the new row
         }
        }
    }catch(Throwable $e){
            error_log("Error while storing user active session data: " . $e->getTraceAsString());
            return null;
        }
}
    // function to get user session data 
    public function getUserSession($user_id, $session_token){
        try{
            $connect = new Connect();
                // fetch the user active session info 
                $fetchstmt = $connect->prepare("SELECT * FROM users_session WHERE user_id = :user_id AND user_session = :user_session LIMIT 1");
                $fetchstmt->execute([':user_id' => $user_id, 'user_session' => $session_token]);
                $result = $fetchstmt->fetch(PDO::FETCH_ASSOC);
                $fetchstmt->closeCursor();
                if($result){
                    return $result;
                }else {
                    error_log("error fail to fetch user session data");
                    return null;
                }
        }catch(Throwable $e){
            error_log("Error while getting user active session: " . $e->getTraceAsString());
            return null;
        }
        
    }
}


?>