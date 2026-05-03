<?php

namespace core;
use external_server\Connect;
use PDOException;
use Throwable;
use PDO;

// Controller - central authentication helper
class Controller {
    public static function getAuthenticatedUserId() {
        // Data to check against the DB
        $user_id = null;
        $session_token = null;

        // 1. Primary Check: Look in $_SESSION (Fastest and most secure once logged in)
        if (isset($_SESSION['user_id'], $_SESSION['session_token'])) {
            $user_id = $_SESSION['user_id'];
            $session_token = $_SESSION['session_token'];
        } 
        // 2. Secondary Check: Look in secure Cookies (For "Remember Me" persistence)
        elseif (isset($_COOKIE['auth_uid'], $_COOKIE['session_key'])) {
           $cookie_user_id = $_COOKIE['auth_uid'];
           $cookie_session_token = $_COOKIE['session_key'];

        // Before restoring, validate the COOKIE tokens against the database first.
        try {
            $connect = new Connect;
            // Use a temporary prepared statement to validate the cookie tokens
            $stmt = $connect->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND user_session = :session_token AND logged_in = 1");
            $stmt->execute([':user_id' => strval($cookie_user_id), ':session_token' => $cookie_session_token]);
            
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                // Cookie tokens are VALID. Re-establish the PHP session.
                $user_id = $cookie_user_id;
                $session_token = $cookie_session_token;

                // Re-establish the PHP session variables for subsequent requests.
                // This makes the next request rely on $_SESSION (Step 1).
                $_SESSION['user_id'] = $user_id;
                $_SESSION['session_token'] = $session_token;

                // Regenerate the session ID to prevent session fixation on cookie restore
                // session_regenerate_id(true);
                } else {
                // Cookie validation FAILED (expired/mismatched). Immediately destroy it.
                error_log("Security alert: Invalid 'Remember Me' token detected for user_id: {$cookie_user_id}. Clearing cookies.");
                self::destroySessionAndCookies();
                return null;
            }
            } catch (PDOException $e) {
            error_log("DB error during cookie validation: " . $e->getMessage());
            // Fail safe and treat as invalid
            self::destroySessionAndCookies();
            return null;
        }
    } 
        // If no data found in session or cookies, the user is not logged in.
        if (empty($user_id) || empty($session_token)) {
            return null;
        }

        // 3. Ultimate Check: Validate Session Token against the Database
        try {
            $connect = new Connect;
            $stmt = $connect->prepare("SELECT user_id FROM users WHERE user_id = :user_id AND user_session = :session_token AND logged_in = 1");
            $stmt->execute([':user_id' => strval($user_id), ':session_token' => $session_token]);
            
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                // Session is valid
                return $user_id;
            }
        } catch (PDOException $e) {
            error_log("DB error during session validation: " . $e->getMessage());
            // Fail safe and treat as invalid
        }

        // 4. If validation fails (expired/stolen/mismatch), destroy all tokens, update logged_in status to false
        try{ 
        $connect = new Connect();
        $logged_in = boolval(false);
        $stmt = $connect->prepare("UPDATE users SET logged_in = :logged_in WHERE user_id = :user_id");
        $stmt->execute([':logged_in' => $logged_in, ':user_id' => $user_id]);
        } catch (PDOException $e) {
            error_log("Error updating logged_in on invalid session: " . $e->getMessage());
        }
        error_log("Security alert: Invalid session token detected for user_id: {$user_id}. Clearing session.");
        self::destroySessionAndCookies();
        
        return null;
    }

    public static function destroySessionAndCookies() {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"] ?? '/', $params["domain"] ?? '',
                $params["secure"] ?? false, $params["httponly"] ?? true
            );
        }

        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
        }

        $domain = $_SERVER['HTTP_HOST'] ?? '';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie('auth_uid', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        setcookie('session_key', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    // create cookies 
     public function createSessionAndCookies($user_id, $session_token) {
        // The domain logic is used in setcookie and defined here to ensure consistency
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        // 1. Set values in $_SESSION 
        $_SESSION['user_id'] = $user_id;
        $_SESSION['session_token'] = $session_token; // The unique token stored in the DB

        // 2. Set the secure cookies (for persistent login across browser restarts)

        // Cookie for user_id
        setcookie('auth_uid', $user_id, [
            'expires' => time() + 60 * 60 * 24 * 30, // 30 days
            'path' => "/",
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true, // Essential for security, prevents XSS reading
            'samesite' => 'Lax'
        ]);
        
        // Cookie for the secure session token
        setcookie('session_key', $session_token, [
            'expires' => time() + 60 * 60 * 24 * 30, // 30 days
            'path' => "/",
            'domain' => $domain, 
            'secure' => $secure, // Only transmit over HTTPS
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    //Function to fetch user info

    public function userData($user_id) {

        try{
        $connect = new Connect;
        $stmt = $connect->prepare("SELECT user_id, user_fname, user_lname, user_email,user_phone,user_avatar, last_login, user_account_creation_date, is_verified FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if($result){
            return $result;
        }
        return null;
    } catch(PDOException $e) {
        error_log("Error occured while fetching user data:". $e->getMessage());
    }
}
    public function userDeliveryAddress($user_id, $email) {
        // $email will not be use now  but can be use when needed
        try{
            $connect = new Connect;
            $stmt = $connect->prepare("SELECT * FROM delivery_address WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if($result){
                return $result;
            }
            return null;
        }catch(PDOException $e){
            error_log("Error occured while fetching user Delivery Addresss:" .$e->getMessage());
        }
    }

    // Function to update user delivery address (insert if not exists, update if exists)
    public function updateUserDeliveryAddress($user_id, $fname, $lname, $postcode, $address, $city, $country,?string $email = null, ?string $phone = null) {
        try{
            $connect = new Connect;
            // Check if record exists
            $checkStmt = $connect->prepare("SELECT COUNT(*) FROM delivery_address WHERE user_id = :user_id");
            $checkStmt->execute([':user_id' => $user_id]);
            $exists = $checkStmt->fetchColumn() > 0;

            if ($exists) {
                // Update existing record
                $stmt = $connect->prepare("UPDATE delivery_address SET user_fname = :fname, user_lname = :lname, user_postcode = :postcode, user_address = :address, user_email = :email, user_city = :city, user_country = :country, user_phone = :phone WHERE user_id = :user_id");
            } else {
                // Insert new record
                $stmt = $connect->prepare("INSERT INTO delivery_address (user_id, user_email, user_fname, user_lname, user_postcode, user_address, user_city, user_country, user_phone) VALUES (:user_id, :email, :fname, :lname, :postcode, :address, :city, :country,:phone)");
            }
            $stmt->execute([
                ':fname' => $fname,
                ':lname' => $lname,
                ':postcode' => $postcode,
                ':address' => $address,
                ':city' => $city,
                ':country' => $country,
                ':user_id' => $user_id,
                ':email' => $email,
                ':phone' => $phone
            ]);
            return true;
        }catch(PDOException $e){
            error_log("Error occured while updating user Delivery Addresss:" .$e->getMessage());
            return false;
        }
    }

    //function to update user info 
    public function updateUserPersonalInfo($user_id,$email, $fname, $lname, $phone){
        try{
            $connect = new Connect();
            // check if any email and phone number exist 
           $stmt = $connect->prepare("SELECT user_id FROM users WHERE (user_email = :email OR user_phone = :phone) AND user_id != :user_id LIMIT 1");
           $stmt->execute([':email' => $email, ':phone' => $phone, ':user_id' => $user_id]);
            if($stmt->fetchColumn()){
                error_log("UpdateUserPErsonalInfo: Email Address or phone number already exist");
                return "Email Address or phone number already exist";
            }
            $connect->beginTransaction();
            $stmt =  $connect->prepare("UPDATE users SET user_fname = :fname, user_lname = :lname , user_email = :email, user_phone = :phone WHERE user_id = :user_id ");
            $stmt->execute([':user_id' => $user_id, ':email' => $email, ':phone' => $phone , ':fname' => $fname, ':lname' => $lname]);
            $stmt = $connect->prepare("UPDATE delivery_address SET user_phone = :phone WHERE user_id = :user_id");
            $stmt->execute([':phone' => $phone, ':user_id' => $user_id]);

            $connect->commit();
            // check affected rows
            $rows_affected = $stmt->rowCount();

            if($rows_affected === 1){
                // Exactly one row updated. Success.
            return true;
            } elseif ($rows_affected === 0) {
                // No rows updated. This usually means the data submitted was identical to the existing data    
                return true;
            }else{
               //  if user_id is unique this error will not occur
                error_log("Transaction performed on updating Personal Info, Multiple rows updated for user_id: " . $user_id);
            return true;
        }

        }catch(Throwable $e){
            if($connect->inTransaction()){
                $connect->rollBack();
            }
            error_log("Error occured while updating personal info:" .$e->getMessage());
            return false;
        }
    }

    // function to update user password 
    public function updateUserPassword(string $user_id, string $email, string $current_password, string $new_password){
        $connect = new Connect();
        // check if current user password is equivalent to that of the db
        $stmt = $connect->prepare("SELECT user_password FROM users WHERE user_id = :user_id AND user_email = :email");
        $stmt->execute([':user_id' => $user_id, ':email' => $email]);
        if($result = $stmt->fetch(PDO::FETCH_ASSOC)){
            $hashed_db_password = $result['user_password'];
            if(!password_verify($current_password, $hashed_db_password)){ 
              return "Current password is incorrect";
            }
           
        }
        $password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        try{
        $stmt = $connect->prepare("UPDATE users SET user_password = :password WHERE user_id = :user_id AND user_email = :email");
        $result = $stmt->execute([':password' => $password_hashed, ':user_id' => $user_id, ':email' => $email]);
        // Return true only if the update query affected at least one row.
        return $stmt->rowCount() > 0;

    }catch(Throwable $e){
            error_log("Error occured while updating personal info:" .$e->getMessage());
            return false;
        }
    }
        //function to check user orders
    public function verifyOrders(string $user_id, ?string $order_id = null){
        try{
            $conn = new Connect();
            $stmt = $conn->prepare("SELECT 1 FROM orders WHERE order_id = :order_id AND user_id = :user_id LIMIT 1");
            $stmt->execute([':order_id' => $order_id, ':user_id'=> $user_id]);
            return (bool) $stmt->fetchColumn();
        }catch(Throwable $e){
            error_log("Error occured while verifying/checking users orders details:" . $e->getMessage() . $e->getTraceAsString());
            return false;
        }
    }
}



?>