<?php
// Allow CORS and POST requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON or POST data
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

// Check if data is encrypted
if (isset($input['encrypted_data'])) {
    // Decrypt the data
    try {
        $decryptedJson = urldecode(base64_decode($input['encrypted_data']));
        $input = json_decode($decryptedJson, true);
        if ($input === null) {
            throw new Exception('Invalid decrypted JSON');
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid encrypted data']);
        exit;
    }
}

// Validate required fields
$required_fields = ['js_error', 'js_url'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || trim((string)$input[$field]) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required field: ' . $field]);
        exit;
    }
}

// Sanitize input
$message = htmlspecialchars($input['js_error'], ENT_QUOTES, 'UTF-8');
$url = htmlspecialchars($input['js_url'], ENT_QUOTES, 'UTF-8');
$line = isset($input['js_line']) && trim((string)$input['js_line']) !== '' ? htmlspecialchars($input['js_line'], ENT_QUOTES, 'UTF-8') : '0';
$column = isset($input['js_column']) && trim((string)$input['js_column']) !== '' ? htmlspecialchars($input['js_column'], ENT_QUOTES, 'UTF-8') : '0';
$error_type = isset($input['js_error_type']) ? htmlspecialchars($input['js_error_type'], ENT_QUOTES, 'UTF-8') : 'error';
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars($_SERVER['HTTP_USER_AGENT'], ENT_QUOTES, 'UTF-8') : 'Unknown';

// Decode base64 encoded JavaScript error message if it appears to be encoded
if (!empty($message)) {
    // Check if the message looks like base64 (contains only valid base64 characters)
    if (preg_match('/^[A-Za-z0-9+\/=]+$/', $message) && strlen($message) % 4 === 0) {
        try {
            $decoded_message = base64_decode($message, true);
            if ($decoded_message !== false) {
                // Verify it's valid UTF-8 and not binary data
                if (mb_check_encoding($decoded_message, 'UTF-8') && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $decoded_message)) {
                    $message = htmlspecialchars($decoded_message, ENT_QUOTES, 'UTF-8');
                }
            }
        } catch (Exception $e) {
            // If decoding fails, keep the original message
            error_log("Failed to decode base64 JavaScript error: " . $e->getMessage());
        }
    }
}

// Validate error type
$allowed_error_types = ['error', 'warning', 'log', 'unhandled_error', 'unhandled_promise_rejection', 'info'];
if (!in_array($error_type, $allowed_error_types)) {
    $error_type = 'error';
}

// Create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/../../error_logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

// Log JavaScript error to file
$log_file = $logs_dir . '/js_errors.txt';
$timestamp = date('Y-m-d H:i:s');
$log_entry = "[{$timestamp}] [{$error_type}] Message: {$message} | URL: {$url} | Line: {$line} | Column: {$column} | User Agent: {$user_agent}\n";

// Append to log file
$result = file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to write to log file']);
    exit;
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'JavaScript error logged successfully',
    'timestamp' => $timestamp
]);
exit;
?>
