<?php
/**
 * Check Payment Status Handler
 * AJAX endpoint to check current payment status
 */

require_once __DIR__ . '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use internal_logic\paymentManager;
use core\Controller;
use MeSomb\Operation\PaymentOperation;

// Force JSON response
header('Content-Type: application/json');

// Get authenticated user
$controller = new Controller();
$user_id = $controller->getAuthenticatedUserId();

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Note: XMLHttpRequest check is optional - not all clients send it
// Check if this looks like an AJAX request (can also check Content-Type)
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
          isset($_POST['payment_id']); // If payment_id is posted, treat as AJAX

if (!$isAjax && empty($_POST['payment_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateAjax_formCsrfToken()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $payment_id = htmlspecialchars($_POST['payment_id'] ?? '', ENT_QUOTES, 'UTF-8');  
    
    if (empty($payment_id)) {
        throw new Exception('Payment ID is required');
    }

    // Initialize payment manager
    $paymentManager = new paymentManager(null, $user_id);

    // Get current payment details
    $paymentDetails = $paymentManager->getPaymentDetails($payment_id);

    if (!$paymentDetails) {
        throw new Exception('Payment not found');
    }

    // Get previous status to detect changes
    $previous_status = $_POST['previous_status'] ?? null;
    $current_status = $paymentDetails['status'];
    
    // Check if status has changed
    $status_changed = ($previous_status && $previous_status !== $current_status);

    // Log the status check for debugging
    error_log("Payment Status Check - ID: {$payment_id}, Current: {$current_status}, Previous: {$previous_status}, Changed: " . ($status_changed ? 'yes' : 'no'));

    // Return payment status information
    echo json_encode([
        'success' => true,
        'status' => $current_status,
        'status_changed' => $status_changed,
        'message' => 'Payment status checked successfully',
        'payment_details' => [
            'payment_id' => $payment_id,
            'status' => $current_status,
            'amount' => $paymentDetails['amount'],
            'method' => $paymentDetails['payment_method'],
            'transaction_id' => $paymentDetails['transaction_id'],
            'completed_at' => $paymentDetails['completed_at']
        ]
    ]);

} catch (Exception $e) {
    error_log('Payment status check error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to check payment status. ' . $e->getMessage()
    ]);
}

exit;
?>