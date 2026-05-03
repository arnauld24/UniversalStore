<?php

namespace core;

require_once __DIR__ . '/../config/config.php';
// Define the security constant guard. If APP_INIT isn't defined the rest of the
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
    http_response_code(403);
    $redirect_url =  ERROR_URL . "?code=403&message=" . urlencode("Access Denied.");
    header("Location: $redirect_url");
    exit;
}

// No controller implementation here anymore; Controller lives in Controller.php

// Keep legacy class name used in other files: codeGenarator
class utility {
    public function generateCode($length){
            try{
            // Generate $length bytes of cryptographically secure random data
            $random_bytes = random_bytes($length);
            // Convert the bytes to a hex string for storage (e.g., 32 bytes -> 64 characters)
            $password = bin2hex($random_bytes);

            return $password;
          }
          catch(\Exception $e){
            // Handle the exception if random_bytes() fails
            error_log("Error generating secure random bytes: " . $e->getMessage());
            return null;
          }
        }
        //generating user_id
        public function generateUserid($length){
            try{
                $random_bytes = random_bytes($length);
                $user_id = bin2hex($random_bytes);
                return $user_id;
            }
            catch(\Exception $e){
                error_log("Error generating user_id: " . $e->getMessage());
                return null;
            }
        }
                /**
         * Encrypt sensitive data using AES-256-CBC
         */
      public function encryptData($data) {
            $config = parse_ini_file(__DIR__ . '/../config/config.ini');
            $key = $config['ENCRYPTION_KEY'] ?? '';
            if (empty($key) || strlen($key) !== 32) {
                throw new \Exception('Invalid encryption key');
            }
            $iv = random_bytes(16);
            $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
            return base64_encode($iv . $encrypted);
        }

        /**
         * Decrypt sensitive data
         */
        public function decryptData($encryptedData) {
           if (empty($encryptedData)) {
        return ""; // Don't try to decrypt empty fields
    }

    $config = parse_ini_file(__DIR__ . '/../config/config.ini');
    $key = $config['ENCRYPTION_KEY'] ?? '';
    
    if (empty($key) || strlen($key) !== 32) {
        throw new \Exception('Invalid encryption key');
    }

    $data = base64_decode($encryptedData, true); // Use 'true' for strict check

    // Check if base64_decode failed or the data is too short to contain an IV (16 bytes)
    if ($data === false || strlen($data) <= 16) {
        return $encryptedData; // Return as-is if it's not valid encrypted data
    }

    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    
    // If decryption fails, it might be plain text
    return ($decrypted === false) ? $encryptedData : $decrypted;
}
                
                /**
        * Emit a standardized JSON error response and exit.
        * Rotates the CSRF token when available and sets the provided HTTP status code.
        *
        * @param string $message Human-readable error message
        * @param int $httpCode HTTP status code (default 500)
        * @param array $extra Additional keys to merge into the JSON payload (e.g. ['redirect' => $url])
        * @return void (exits)
        */
        public function jsonError(string $message, int $httpCode = 500, array $extra = []): void {
            // Attempt to rotate CSRF token if helper exists
            if (function_exists('rotateCsrfToken')) {
                try {
                    rotateCsrfToken();
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $payload = array_merge(['success' => false, 'message' => $message], $extra);
            if (function_exists('getCsrfToken')) {
                try {
                    $payload['csrf_token'] = htmlspecialchars(getCsrfToken());
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            http_response_code($httpCode);
            echo json_encode($payload);
            exit;
        }

    }





// Alias utility into global namespace because older code instantiates it globally
if (!class_exists('utility')) {
    class_alias(__NAMESPACE__ . '\\utility', 'utility');
}
?>