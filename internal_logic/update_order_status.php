<?php

// INITIALIZATION & SECURITY
require_once __DIR__ . '/../config/config.php';
require_once INIT_PATH . '/init.php';

if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit;
}

require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use core\Controller;
use internal_logic\OrderStatusManager;

header('Content-Type: application/json');

// Check authentication
$authenticatedUserId = Controller::getAuthenticatedUserId();

if (!$authenticatedUserId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Verify CSRF token
try {
    validateAjax_formCsrfToken();
} catch (Exception $e) {
    rotateCsrfToken();
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Security validation failed',
        'csrfToken' => getCsrfToken()
    ]);
    exit;
}

// Check if this is an AJAX request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get request parameters
    $orderId = isset($_POST['order_id']) ? htmlspecialchars(trim($_POST['order_id']), ENT_QUOTES, 'UTF-8') : null;
    $newStatus = isset($_POST['status']) ? htmlspecialchars(trim($_POST['status']), ENT_QUOTES, 'UTF-8') : null;

    // Validate input
    if (empty($orderId) || empty($newStatus)) {
        echo json_encode(['success' => false, 'message' => 'Order ID and status are required']);
        exit;
    }

    // Initialize OrderStatusManager
    $statusManager = new OrderStatusManager();

    // Update order status (this will automatically send emails)
    $success = $statusManager->updateOrderStatus($orderId, $newStatus);

    if ($success) {
        rotateCsrfToken();
        echo json_encode([
            'success' => true,
            'message' => 'Order status updated successfully',
            'order_id' => $orderId,
            'new_status' => $newStatus,
            'csrfToken' => getCsrfToken()
        ]);
    } else {
        rotateCsrfToken();
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update order status',
            'csrfToken' => getCsrfToken()
        ]);
    }
    exit;

} catch (Exception $e) {
    rotateCsrfToken();
    error_log('Error in update_order_status.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . htmlspecialchars($e->getMessage()),
        'csrfToken' => getCsrfToken()
    ]);
    exit;
}
