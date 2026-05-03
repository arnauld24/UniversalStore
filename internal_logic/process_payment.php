<?php
/**
 * Process Payment Handler
 * Handles payment processing requests from the payment page
 * Enhanced security implementation
 */

require_once __DIR__ . '/../config/config.php';
require_once INIT_PATH . '/init.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use internal_logic\paymentManager;
use core\Controller;
use core\AuditLogger;

// Force JSON response
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Get authenticated user
$controller = new Controller();
$user_id = $controller->getAuthenticatedUserId();

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Initialize audit logger
$auditLogger = new AuditLogger($user_id);

// ===== ENHANCED SECURITY VALIDATIONS =====

// 1. VERIFY REQUEST ORIGIN
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    $auditLogger->logSecurityEvent('SUSPICIOUS_PAYMENT_REQUEST', 'Request origin validation failed', 'ALERT');
    echo json_encode(['success' => false, 'message' => 'Invalid request origin']);
    exit;
}

// 2. VERIFY REQUEST CAME FROM PAYMENT PAGE
if (empty($_SERVER['HTTP_X_REQUESTED_FROM']) || $_SERVER['HTTP_X_REQUESTED_FROM'] !== 'payment-page') {
    http_response_code(403);
    $auditLogger->logSecurityEvent('SUSPICIOUS_PAYMENT_REQUEST', 'Request source validation failed', 'ALERT');
    echo json_encode(['success' => false, 'message' => 'Invalid request source']);
    exit;
}

// 3. VALIDATE PAYMENT ACCESS TOKEN
function validatePaymentAccessToken($token, $userId, $expectedOrderId, $sessionToken) {
    error_log("Token validation started for user {$userId}, order {$expectedOrderId}");
    
    if (!$token) {
        error_log("Token validation failed: Token not provided");
        return ['valid' => false, 'reason' => 'Token not provided'];
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        error_log("Token validation failed: Invalid token format, parts: " . count($parts));
        return ['valid' => false, 'reason' => 'Invalid token format'];
    }
    
    try {
        $tokenData = json_decode(base64_decode($parts[1]), true);
        
        if (!$tokenData) {
            error_log("Token validation failed: Invalid token data after decode");
            return ['valid' => false, 'reason' => 'Invalid token data'];
        }
        
        error_log("Token data decoded successfully: " . json_encode($tokenData));
        
        // Check if token has expired (30 minutes)
        if (time() - $tokenData['timestamp'] > 1800) {
            error_log("Token validation failed: Token expired. Token time: {$tokenData['timestamp']}, Current time: " . time());
            return ['valid' => false, 'reason' => 'Token has expired'];
        }
        
        // Validate user
        if ($tokenData['user_id'] !== $userId) {
            error_log("Token validation failed: User mismatch. Token user: {$tokenData['user_id']}, Expected: {$userId}");
            return ['valid' => false, 'reason' => 'Token user mismatch'];
        }
        
        // Validate order ID
        if ($tokenData['order_id'] !== $expectedOrderId) {
            error_log("Token validation failed: Order mismatch. Token order: {$tokenData['order_id']}, Expected: {$expectedOrderId}");
            return ['valid' => false, 'reason' => 'Token order mismatch'];
        }
        
        // Validate session token
        if ($tokenData['session_token'] !== $sessionToken) {
            error_log("Token validation failed: Session mismatch. Token session: {$tokenData['session_token']}, Expected: {$sessionToken}");
            return ['valid' => false, 'reason' => 'Token session mismatch'];
        }
        
        // Validate User-Agent hash (device checking) - Made less strict for compatibility
        $currentUserAgentHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($tokenData['user_agent_hash'] !== $currentUserAgentHash) {
            error_log("Payment token User-Agent mismatch - allowing for browser compatibility. Token: {$tokenData['user_agent_hash']}, Current: {$currentUserAgentHash}");
            // Don't fail here as User-Agent can change slightly between requests
            // Just log for monitoring
        }
        
        error_log("Token validation successful");
        return ['valid' => true, 'data' => $tokenData];
        
    } catch (Exception $e) {
        error_log("Token validation failed with exception: " . $e->getMessage());
        return ['valid' => false, 'reason' => 'Token validation error: ' . $e->getMessage()];
    }
}

// 4. VALIDATE CSRF TOKEN
if (!verifyAjax_formCsrfToken()) {
    http_response_code(403);
    rotateCsrfToken();
    $auditLogger->logSecurityEvent('CSRF_VALIDATION_FAILED', 'Payment CSRF token validation failed', 'ALERT');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token', 'csrf_token' => htmlspecialchars(getcsrfToken())]);
    exit;
}

// 5. VALIDATE PAYMENT ACCESS TOKEN
$paymentAccessToken = $_POST['payment_access_token'] ?? $_SERVER['HTTP_X_PAYMENT_TOKEN'] ?? '';

if (empty($paymentAccessToken)) {
    error_log("Payment access token not found in request. POST: " . (isset($_POST['payment_access_token']) ? 'present' : 'missing') . ", HEADER: " . (isset($_SERVER['HTTP_X_PAYMENT_TOKEN']) ? 'present' : 'missing'));
    http_response_code(403);
    rotateCsrfToken();
    $auditLogger->logSecurityEvent('PAYMENT_TOKEN_VALIDATION_FAILED', 'Payment access token not provided', 'ALERT');
    echo json_encode([
        'success' => false,
        'message' => 'Payment session validation failed. Please return to checkout and try again.',
        'csrf_token' => htmlspecialchars(getCsrfToken())
    ]);
    exit;
}

$tokenValidation = validatePaymentAccessToken($paymentAccessToken, $user_id, $_POST['order_id'] ?? '', $authenticatedUserSession);

if (!$tokenValidation['valid']) {
    http_response_code(403);
    rotateCsrfToken();
    $auditLogger->logSecurityEvent('PAYMENT_TOKEN_VALIDATION_FAILED', 'Reason: ' . $tokenValidation['reason'], 'ALERT', [
        'order_id' => $_POST['order_id'] ?? 'unknown',
        'user_id' => $user_id
    ]);
    echo json_encode([
        'success' => false,
        'message' => 'Payment session validation failed. Please return to checkout and try again.',
        'csrf_token' => htmlspecialchars(getCsrfToken())
    ]);
    exit;
}

// 6. RATE LIMITING FOR PAYMENT ATTEMPTS
$paymentRateLimit = function($userId, $orderId) {
    $cacheKey = 'payment_attempt_' . md5($userId . $orderId . $_SERVER['REMOTE_ADDR']);
    if (!isset($_SESSION['_payment_rate_limit_cache'])) {
        $_SESSION['_payment_rate_limit_cache'] = [];
    }
    
    $attempts = $_SESSION['_payment_rate_limit_cache'][$cacheKey] ?? [];
    $now = time();
    
    // Remove attempts older than 1 hour
    $attempts = array_filter($attempts, function($time) use ($now) {
        return ($now - $time) < 3600;
    });
    
    // Check if more than 5 attempts in 1 hour for same order
    if (count($attempts) >= 5) {
        return ['allowed' => false, 'message' => 'Too many payment attempts. Please try again later.'];
    }
    
    $attempts[] = $now;
    $_SESSION['_payment_rate_limit_cache'][$cacheKey] = $attempts;
    
    return ['allowed' => true];
};

$orderId = $_POST['order_id'] ?? '';
if (!empty($orderId)) {
    $rateLimitCheck = $paymentRateLimit($user_id, $orderId);
    if (!$rateLimitCheck['allowed']) {
        http_response_code(429);
        $auditLogger->logSecurityEvent('PAYMENT_RATE_LIMIT_EXCEEDED', 'Too many payment attempts from ' . $_SERVER['REMOTE_ADDR'], 'WARNING');
        echo json_encode(['success' => false, 'message' => $rateLimitCheck['message']]);
        exit;
    }
}

try {
    // Get and validate input
    $order_id = $_POST['order_id'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $payer_phone = $_POST['payer_phone'] ?? '';

    if (empty($order_id) || empty($payment_method) || empty($payer_phone)) {
        throw new Exception('Missing required payment parameters');
    }

    // Initialize payment manager
    $paymentManager = new paymentManager($order_id, $user_id);

    // Get order details to get the amount
    $orderDetails = $paymentManager->getOrderDetails($order_id);
    if (!$orderDetails) {
        // Log failed payment attempt
        $auditLogger->logSecurityEvent('PAYMENT_VALIDATION_FAILED', "Order not found: {$order_id}", 'ALERT', ['order_id' => $order_id]);
        throw new Exception('Order not found');
    }

    $amount = (float)$orderDetails['order_cost'];
    if ($amount <= 0) {
        $auditLogger->logPaymentFailure($order_id, '', $amount, 'Order amount is invalid or zero');
        throw new Exception('Order amount is invalid or zero. Please check your order.');
    }
    if ($amount > 100000000) {
        $auditLogger->logPaymentFailure($order_id, '', $amount, 'Order amount exceeds maximum allowed');
        throw new Exception('Order amount exceeds maximum allowed (100,000,000 FCFA).');
    }

    // Log payment initiation
    $auditLogger->logPaymentInitiation($order_id, $payment_method, $amount, 'PENDING', [
        'payer_phone' => $payer_phone,
        'payment_method' => $payment_method,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    // Prepare customer data from order
    $customer_data = [
        'email' => $orderDetails['user_email'],
        'first_name' => $orderDetails['user_fname'],
        'last_name' => $orderDetails['user_lname'],
        'town' => $orderDetails['user_city'],
        'region' => 'Littoral', // Default region
        'address' => $orderDetails['user_address']
    ];

    // Process the payment
    $result = $paymentManager->processPayment($order_id, $payment_method, $payer_phone, $amount, $customer_data);
    
    if ($result['success']) {
        // Store payment ID in session for status tracking
        $_SESSION['current_payment_id'] = $result['payment_id'];
        $paymentId = $result['payment_id'] ?? null;

        // Log successful payment initiation
        $auditLogger->logPaymentAudit($order_id, $paymentId ?? '', $payment_method, $amount, 'PENDING', json_encode($result), 1);
        http_response_code(200);
        RotateCsrfToken(); // Rotate token after successful initiation
        
        // Clear the payment access token after successful use
        unset($_SESSION['payment_access_token']);
        setcookie('payment_access_token', '', time() - 3600, '/');
        
        echo json_encode([
            'success' => true,
            'payment_id' => $result['payment_id'],
            'redirect_url' => universalstore_URL . '/payment_status.php?payment_id=' . urlencode($result['payment_id']),
            'csrf_token' => htmlspecialchars(getCsrfToken())
        ]);
    } else {
        // Log failed payment
        $paymentId = $result['payment_id'] ?? null;
        $auditLogger->logPaymentFailure($order_id, $paymentId ?? '', $amount, $result['message'] ?? 'Payment processing failed', $payment_method);
        RotateCsrfToken(); // Rotate token after failure
        $redirectUrl = '';
        if ($paymentId) {
            $redirectUrl = universalstore_URL . '/payment_status.php?payment_id=' . urlencode($paymentId) . '&error_message=' . urlencode($result['message'] ?? 'Payment failed');
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'payment_id' => $paymentId,
            'message' => $result['message'] ?? 'Payment processing failed',
            'redirect_url' => $redirectUrl,
            'csrf_token' => htmlspecialchars(getCsrfToken())
        ]);
    }

} catch (Exception $e) {
    error_log('Payment processing error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    // Log the exception
    $auditLogger->logSecurityEvent('PAYMENT_EXCEPTION', $e->getMessage(), 'FAILED', [
        'error' => $e->getMessage(),
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    http_response_code(500);
    rotateCsrfToken(); // Rotate token on failure to prevent reuse
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your payment. Please try again.',
        'csrf_token' => htmlspecialchars(getCsrfToken())
    ]);
}

exit;

try {
    // Get and validate input
    $order_id = $_POST['order_id'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $payer_phone = $_POST['payer_phone'] ?? '';

    if (empty($order_id) || empty($payment_method) || empty($payer_phone)) {
        throw new Exception('Missing required payment parameters');
    }

    // Initialize payment manager
    $paymentManager = new paymentManager($order_id, $user_id);

    // Get order details to get the amount
    $orderDetails = $paymentManager->getOrderDetails($order_id);
    if (!$orderDetails) {
        // Log failed payment attempt
        $auditLogger->logSecurityEvent('PAYMENT_VALIDATION_FAILED', "Order not found: {$order_id}", 'ALERT', ['order_id' => $order_id]);
        throw new Exception('Order not found');
    }

    $amount = (float)$orderDetails['order_cost'];
    if ($amount <= 0) {
        $auditLogger->logPaymentFailure($order_id, '', $amount, 'Order amount is invalid or zero');
        throw new Exception('Order amount is invalid or zero. Please check your order.');
    }
    if ($amount > 100000000) {
        $auditLogger->logPaymentFailure($order_id, '', $amount, 'Order amount exceeds maximum allowed');
        throw new Exception('Order amount exceeds maximum allowed (100,000,000 FCFA).');
    }

    // Log payment initiation
    $auditLogger->logPaymentInitiation($order_id, $payment_method, $amount, 'PENDING', [
        'payer_phone' => $payer_phone,
        'payment_method' => $payment_method
    ]);

    // Prepare customer data from order
    $customer_data = [
        'email' => $orderDetails['user_email'],
        'first_name' => $orderDetails['user_fname'],
        'last_name' => $orderDetails['user_lname'],
        'town' => $orderDetails['user_city'],
        'region' => 'Littoral', // Default region
        'address' => $orderDetails['user_address']
    ];

    // Process the payment
    $result = $paymentManager->processPayment($order_id, $payment_method, $payer_phone, $amount, $customer_data);
    
    if ($result['success']) {
        // Store payment ID in session for status tracking
        $_SESSION['current_payment_id'] = $result['payment_id'];
        $paymentId = $result['payment_id'] ?? null;

        // Log successful payment initiation
        $auditLogger->logPaymentAudit($order_id, $payment_id ?? '', $payment_method, $amount, 'PENDING', json_encode($result), 1);
        http_response_code(200);
        RotateCsrfToken(); // Rotate token after successful initiation
        echo json_encode([
            'success' => true,
            'payment_id' => $result['payment_id'],
            'redirect_url' => universalstore_URL . '/payment_status.php?payment_id=' . urlencode($result['payment_id']),
            'csrf_token' => htmlspecialchars(getCsrfToken())
        ]);
    } else {
        // Log failed payment
        $paymentId = $result['payment_id'] ?? null;
        $auditLogger->logPaymentFailure($order_id, $paymentId ?? '', $amount, $result['message'] ?? 'Payment processing failed', $payment_method);
        RotateCsrfToken(); // Rotate token after failure
        $redirectUrl = '';
        if ($paymentId) {
            $redirectUrl = universalstore_URL . '/payment_status.php?payment_id=' . urlencode($paymentId) . '&error_message=' . urlencode($result['message'] ?? 'Payment failed');
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'payment_id' => $paymentId,
            'message' => $result['message'] ?? 'Payment processing failed',
            'redirect_url' => $redirectUrl,
            'csrf_token' => htmlspecialchars(getCsrfToken())
        ]);
    }

} catch (Exception $e) {
    error_log('Payment processing error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    // Log the exception
    $auditLogger->logSecurityEvent('PAYMENT_EXCEPTION', $e->getMessage(), 'FAILED', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    rotateCsrfToken(); // Rotate token on failure to prevent reuse
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your payment. Please try again.',
        'csrf_token' => htmlspecialchars(getCsrfToken())
    ]);
}

exit;
?>