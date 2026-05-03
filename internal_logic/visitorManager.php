<?php 

namespace internal_logic;

use DateTime;
use external_server\Connect;
use internal_logic\Cart;
use core\UserTracker;
use PDO;
use Exception;
use PDOException;
use Throwable;

require_once __DIR__ . '/../config/config.php';

// Define the security constant if it hasn't been defined yet.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
    header("Location: " . $redirect_url);
    die('403 Access Denied.'); 
}

require_once SERVER_PATH . '/secure_session_csrf_cookie.php';
require_once CORE_PATH . '/UserTracker.php';

class visitorManager{
    
    public function generateVisitorcode($length){
        try{
        
        $random_bytes = random_bytes($length);
        $visitor_id = bin2hex($random_bytes);
        return $visitor_id;
        }catch(Exception $e){
            error_log("Error while generating visitor id" . $e->getMessage());
            return null;
        }
    }
    //function to create visitor cookie and session
    protected function setVisitorSessionCookie($visitor_id, $visitor_token){
        try{
            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
            // set values in session (consistent keys)
            $_SESSION['visitor_id'] = $visitor_id;
            $_SESSION['visitor_session'] = $visitor_token;

            // Set Secure COOKIES
            // set visitor_id cookie
            setcookie('visitor_id', $visitor_id, [
                'expires' => time() + 60 * 60 * 24 * 30, // 30 days
                'path' => '/',
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            // set visitor_session cookie
            setcookie('is_vis_sess', $visitor_token, [
                'expires' => time() + 60 * 60 * 24 * 30, // 30 days
                'path' => '/',
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

        }catch(Exception $e){
            http_response_code(500);
            error_log("Error while creating cookies: " . $e->getMessage());
            die();
        }
    }
    // function to destroy  visitor_id and visitor_token
    public static function destroyVisitorSessionCookie(){
        // clear session data
        $_SESSION = [];
        // Invalidate the PHP session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 86400 * 30,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        // Destroy the PHP session
        session_destroy();

        // Invalidate the persistent cookies (visitor_id and visitor_token)
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie('visitor_id', '',[
          'expires' => time() - 86400 * 30,
          'path' => '/',
          'domain' => $domain,
          'secure' => $secure,
          'httponly' => true,
          'samesite' => 'Lax']);

                setcookie('is_vis_sess', '',[
          'expires' => time() - 86400 * 30,
          'path' => '/',
          'domain' => $domain,
          'secure' => $secure,
          'httponly' => true,
          'samesite' => 'Lax']);  
    }
    //function to create visitor_id and  visitor_token
    public function trackVisitor(){
        try{
        $connect = new Connect();
        if (session_status() === PHP_SESSION_NONE){
                    session_start();
                }
        $visitor_ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown' ;

        // Check if visitor_id cookie exists and is valid in DB
        if (isset($_COOKIE['visitor_id'])) {
            try{
            $visitor_id = $_COOKIE['visitor_id'];
            $stmt = $connect->prepare("SELECT * FROM visitors WHERE visitor_id = :visitor_id AND expires_at > NOW()");
            $stmt->execute([':visitor_id' => $visitor_id]);
            if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Valid cookie, restore and update token/IP
                $new_visitor_token = $this->generateVisitorcode(32);
                $updatestmt = $connect->prepare("UPDATE visitors SET visitor_session = :visitor_session, visitor_ip_address = :visitor_ip_address WHERE visitor_id = :visitor_id");
                $updatestmt->execute([
                    ':visitor_session' => $new_visitor_token,
                    ':visitor_ip_address' => $visitor_ip_address,
                    ':visitor_id' => $visitor_id
                ]);
                // Set session and cookies
                $_SESSION['visitor_id'] = $visitor_id;
                $_SESSION['visitor_session'] = $new_visitor_token;
                $this->setVisitorSessionCookie($visitor_id, $new_visitor_token);
                return;
            }
        }catch(Throwable $e){
            error_log("Error while checking and restoring visitor_id " . $e->getTraceAsString());
        }
        }
        // if no cookie , check if visitor_id exists in db using IP and then restore 
        // if(!isset($_COOKIE['visitor_id'])){
        //   try{
        //     $visitor_id = $_SESSION['visitor_id'];
        //     $stmt = $connect->prepare("SELECT * FROM visitors WHERE visitor_id = ':visitor_id AND expires_at > NOW()");
        //     $stmt->execute([':visitor_id' => $visitor_id]);
        //     if($result = $stmt->fetch(PDO::FETCH_ASSOC)){
        //      $new_visitor_token = $this->generateVisitorcode(32);
        //         $updatestmt = $connect->prepare("UPDATE visitors SET visitor_session = :visitor_session, visitor_ip_address = :visitor_ip_address WHERE visitor_id = :visitor_id");
        //         $updatestmt->execute([
        //             ':visitor_session' => $new_visitor_token,
        //             ':visitor_ip_address' => $visitor_ip_address,
        //             ':visitor_id' => $visitor_id
        //         ]);
        //         // Set session and cookies
        //         $_SESSION['visitor_id'] = $visitor_id;
        //         $_SESSION['visitor_session'] = $new_visitor_token;
        //         $this->setVisitorSessionCookie($visitor_id, $new_visitor_token);
        //         return;
        //     }
        // }catch(Throwable $e){
        //     error_log("Error while checking and restoring visitor_id: " . $e->getTraceAsString());
        // }
        // }

        // No valid cookie, create new visitor_id and visitor_token
        $new_visitor_token = $this->generateVisitorcode(32);
        $new_visitor_id = $this->generateVisitorcode(32);

        // Take all relevant information from visitor
        $visitor_ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; // get visitor IP address
        $created_at = date('Y-m-d H:i:s');
        $future_timestamp = time() + 60 * 60 * 24 * 30; // 30 days
        $expires_at = date('Y-m-d H:i:s', $future_timestamp);

        $stmt = $connect->prepare("INSERT INTO visitors(visitor_id,visitor_session,visitor_ip_address,created_at,expires_at)
         values(:visitor_id,:visitor_session,:visitor_ip_address,:created_at,:expires_at)");
        $stmt->bindParam(':visitor_id', $new_visitor_id, PDO::PARAM_STR);
        $stmt->bindParam(':visitor_session', $new_visitor_token, PDO::PARAM_STR);
        $stmt->bindParam(':visitor_ip_address', $visitor_ip_address, PDO::PARAM_STR);
        $stmt->bindParam(':created_at', $created_at, PDO::PARAM_STR);
        $stmt->bindParam(':expires_at', $expires_at, PDO::PARAM_STR);
        $stmt->execute();

        // Set session variables
        $_SESSION['visitor_id'] = $new_visitor_id;
        $_SESSION['visitor_session'] = $new_visitor_token;
        // Rotate the PHP Session ID for Session Fixation prevention
        session_regenerate_id(true);
        // Set long-lived cookie
        $this->setVisitorSessionCookie($new_visitor_id,$new_visitor_token);

        }catch(PDOException $e){
            error_log("Database error trackVisitor:" . $e->getMessage());
            http_response_code(500);
            die();
        }catch(Exception $e){
            error_log("General error in trackVisitor:" . $e->getMessage() . $_SERVER['REQUEST_URI']);
            http_response_code(500);
            die();
        }


    }
    //function to get visitor_id for later use
    public static function get_visitor_id(){
        try{
        $connect = new Connect();
        //check if visitor_id from session match with that for the database 
        $visitor_id = $_SESSION['visitor_id'];
        if(isset($visitor_id)){
        $stmt = $connect->prepare("SELECT * FROM visitors WHERE visitor_id = :visitor_id");
        $stmt->execute([':visitor_id' => $visitor_id]);
        // if session[visitor id] match get the one from the db for more security
        if($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $visitor_id = $row['visitor_id'];  // this is the visitor_id that will be use 
            return (string)$visitor_id;
        }
    }
    return null;
    }catch(PDOException $e){
        // failed silently
        error_log(" Database error while getting visitor_id: " . $e);
        return null;
    }
    }

    // Function to link Visitor to new User
    public function linkVisitorUser($visitor_id,$user_id){
        try{
            if (empty($visitor_id) || empty($user_id)) {
                 // Silently return if essential data is missing.
                return;
            }
            $connect = new Connect();

            // Check if the current visitor_id is already linked to a DIFFERENT user.
            $stmt = $connect->prepare("SELECT user_id FROM visitors WHERE visitor_id = :visitor_id LIMIT 1");
            $stmt->execute([':visitor_id' => $visitor_id]);
            $existing_linked_user = $stmt->fetchColumn();

            if ($existing_linked_user && $existing_linked_user !== $user_id) {
                // CONFLICT: This visitor ID is already linked to another user.
                // This occurs when a user logs out and another logs in from the same browser.
                // We must create a NEW, unique visitor record for the current user.

                $new_visitor_id = $this->generateVisitorcode(32);
                $new_visitor_token = $this->generateVisitorcode(32);
                $visitor_ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $now = date('Y-m-d H:i:s');
                $expires_at = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * 30)); // 30 days

                $insertStmt = $connect->prepare(
                    "INSERT INTO visitors(visitor_id, user_id, visitor_session, visitor_ip_address, created_at, expires_at, linked_at)
                     VALUES(:visitor_id, :user_id, :visitor_session, :visitor_ip_address, :created_at, :expires_at, :linked_at)"
                );
                $insertStmt->execute([
                    ':visitor_id' => $new_visitor_id,
                    ':user_id' => $user_id,
                    ':visitor_session' => $new_visitor_token,
                    ':visitor_ip_address' => $visitor_ip_address,
                    ':created_at' => $now,
                    ':expires_at' => $expires_at,
                    ':linked_at' => $now
                ]);

                // The visitor's cart from the old visitor_id is still in $_SESSION['cart'].
                // The cart migration logic in cart.php will now correctly associate it with the new user.
                // We now update the session and cookies to reflect this new visitor identity.
                $this->setVisitorSessionCookie($new_visitor_id, $new_visitor_token);

                // Server-side visitor->user merge tracking
                $userTracker = new UserTracker();
                $userTracker::mergeVisitorToUser($user_id, $visitor_id);

            } elseif (!$existing_linked_user) {
                // NO CONFLICT: The visitor_id is not linked to any user yet. Link it now.
                $stmt = $connect->prepare("UPDATE visitors SET user_id = :user_id, linked_at = :linked_at WHERE visitor_id = :visitor_id AND user_id IS NULL");
                $linked_at = date('Y-m-d H:i:s');
                $stmt->execute([':user_id' => $user_id, ':linked_at' => $linked_at, ':visitor_id' => $visitor_id]);

                // Server-side visitor->user merge tracking
                $userTracker = new UserTracker();
                $userTracker::mergeVisitorToUser($user_id, $visitor_id);

            }
            // If $existing_linked_user === $user_id, do nothing. The link is already correct.

        } catch (PDOException $e) {
            http_response_code(500);
            error_log("Database errors: " . $e->getMessage());
            die();
        }catch(Exception $e){
            error_log("Something went wrong:" . $e->getMessage());
            http_response_code(500);
            die();
        }

    }

    //Check link status
    public function checkLinkStatus($visitor_id, $user_id){
        try{
        // check from database if link has already been performed
        $connect = new Connect();
        $stmt = $connect->prepare("SELECT visitor_id, user_id FROM visitors WHERE visitor_id = :visitor_id AND user_id = :user_id LIMIT 1");
        $stmt->execute([':visitor_id' => $visitor_id, ':user_id' => $user_id]);
        $checkResult = $stmt->fetch(PDO::FETCH_ASSOC);
        if($checkResult){
            // If $checkResult is NOT false (meaning a row was found), return TRUE
            return (bool)$checkResult;
        }
    }catch (PDOException $e) {
            error_log("DB Error: Failed to check visitor link status for user_id {$user_id}. Details: " . $e->getMessage());
            return false;
        }catch(Throwable $e){
            error_log("CRITICAL THROWABLE in checklink status: " . $e->getTraceAsString());
        }

    }

    // function to load visitor cart from database 
    public function loadVisitorCartFromDb(?string $visitor_id): void
    {
        // Only proceed if we have a visitor_id and the session cart is currently empty.
        if (empty($visitor_id) || !empty($_SESSION['cart'])) {
            return;
        }

        try {
            $connect = new Connect();
            $stmt = $connect->prepare("SELECT data FROM visitors WHERE visitor_id = :visitor_id LIMIT 1");
            $stmt->execute([':visitor_id' => $visitor_id]);
            $json_cart_data = $stmt->fetchColumn();

            // If we found data, decode it and populate the session.
            if ($json_cart_data) {
                $cart_array = json_decode($json_cart_data, true);
                // Ensure the decoded data is a valid array before assigning.
                if (is_array($cart_array)) {
                    $_SESSION['cart'] = $cart_array;
                } else {
                    // Data was corrupted JSON, initialize an empty cart.
                    $_SESSION['cart'] = [];
                }
            } else {
                // No record or no cart data, ensure session cart is empty.
                $_SESSION['cart'] = [];
            }
        } catch (PDOException $e) {
            error_log("Error loading visitor cart from database for visitor_id {$visitor_id}: " . $e->getMessage());
            // On DB error, ensure a safe state (empty cart).
            $_SESSION['cart'] = [];
        }
    }
}


?>