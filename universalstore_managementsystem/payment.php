<?php

 // require config.php which holds BASE_PATH and BASE_URL
require_once __DIR__ . '/../config/config.php';
// Require the main file which handles SESSION and COOKIE management , LOGOUT,LOGGED_IN, VISITORS ETC
require_once INIT_PATH . '/init.php';
// Define the security constant if it hasn't been defined yet.
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403);
    $redirect_url =  ERROR_URL . "?code=403&message= " . urlencode("Access Denied.");
    header("Location: $redirect_url");
    die('403 Access Denied.');
}

// Autoload and secure session helper (registers autoloader and starts session)
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

// ===== PAYMENT SECURITY MEASURES =====

// 1. ENHANCED SECURITY HEADERS
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' https: data:; connect-src \'self\' https://api.mesomb.com;');
header('Referrer-Policy: strict-origin-when-cross-origin');

// 2. PAYMENT PAGE ACCESS TOKEN GENERATION & VALIDATION
function generatePaymentAccessToken($orderId, $userId, $sessionToken) {
    $tokenData = [
        'order_id' => $orderId,
        'user_id' => $userId,
        'session_token' => $sessionToken,
        'timestamp' => time(),
        'nonce' => bin2hex(random_bytes(16)),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent_hash' => hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')
    ];
    
    return bin2hex(random_bytes(32)) . '.' . base64_encode(json_encode($tokenData));
}

function validatePaymentAccessToken($token, $userId, $expectedOrderId, $sessionToken) {
    if (!$token) {
        return ['valid' => false, 'reason' => 'Token not provided'];
    }
    
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return ['valid' => false, 'reason' => 'Invalid token format'];
    }
    
    try {
        $tokenData = json_decode(base64_decode($parts[1]), true);
        
        // Validate token data
        if (!$tokenData) {
            return ['valid' => false, 'reason' => 'Invalid token data'];
        }
        
        // Check if token has expired (30 minutes)
        if (time() - $tokenData['timestamp'] > 1800) {
            return ['valid' => false, 'reason' => 'Token has expired'];
        }
        
        // Validate user
        if ($tokenData['user_id'] !== $userId) {
            error_log("Payment token user mismatch: expected {$userId}, got {$tokenData['user_id']}");
            return ['valid' => false, 'reason' => 'Token user mismatch'];
        }
        
        // Validate order ID
        if ($tokenData['order_id'] !== $expectedOrderId) {
            error_log("Payment token order mismatch: expected {$expectedOrderId}, got {$tokenData['order_id']}");
            return ['valid' => false, 'reason' => 'Token order mismatch'];
        }
        
        // Validate session token
        if ($tokenData['session_token'] !== $sessionToken) {
            error_log("Payment token session mismatch");
            return ['valid' => false, 'reason' => 'Token session mismatch'];
        }
        
        // Validate IP address (allow with warning if different)
        if ($tokenData['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            error_log("Payment token IP mismatch: expected {$tokenData['ip_address']}, got {$_SERVER['REMOTE_ADDR']}");
            // Don't fail here as IP may change (proxy, mobile network), just log
        }
        
        // Validate User-Agent hash
        $currentUserAgentHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($tokenData['user_agent_hash'] !== $currentUserAgentHash) {
            error_log("Payment token User-Agent mismatch");
            return ['valid' => false, 'reason' => 'Token device mismatch'];
        }
        
        return ['valid' => true, 'data' => $tokenData];
        
    } catch (Exception $e) {
        return ['valid' => false, 'reason' => 'Token validation error: ' . $e->getMessage()];
    }
}

// Check if user is logged in
if(empty($authenticatedUserId) && !isset($authenticatedUserId)){
    header("Location: " . AUTHENTICATION_URL);
    exit;
}

// 3. RATE LIMITING FOR PAYMENT PAGE ACCESS
$rateLimit = function($userId) {
    $cacheKey = 'payment_page_access_' . md5($userId . $_SERVER['REMOTE_ADDR']);
    if (!isset($_SESSION['_rate_limit_cache'])) {
        $_SESSION['_rate_limit_cache'] = [];
    }
    
    $attempts = $_SESSION['_rate_limit_cache'][$cacheKey] ?? [];
    $now = time();
    
    // Remove attempts older than 1 hour
    $attempts = array_filter($attempts, function($time) use ($now) {
        return ($now - $time) < 3600;
    });
    
    // Check if more than 20 attempts in 1 hour
    if (count($attempts) >= 20) {
        return ['allowed' => false, 'message' => 'Too many payment page access attempts. Please try again later.'];
    }
    
    $attempts[] = $now;
    $_SESSION['_rate_limit_cache'][$cacheKey] = $attempts;
    
    return ['allowed' => true];
};

$rateLimitCheck = $rateLimit($authenticatedUserId);
if (!$rateLimitCheck['allowed']) {
    error_log("Rate limit exceeded for user: {$authenticatedUserId}");
    header("Location: " . ERROR_URL . "?code=429&message=" . urlencode($rateLimitCheck['message']));
    exit;
}

// Get order_id from URL or session
$order_id = htmlspecialchars(isset($_GET['order_id']) ? $_GET['order_id'] : ($_SESSION['current_order_id'] ?? null), ENT_QUOTES, 'UTF-8');
if (!$order_id) {
    header("Location: " . ERROR_URL . "?code=400&message=" . urlencode('No order ID provided'));
    exit;
}

// Initialize payment manager
$paymentManager = new \internal_logic\paymentManager($order_id, $authenticatedUserId);

// Check if order belongs to user
if (!$paymentManager->checkOrderId($order_id, $authenticatedUserId)) {
    error_log("Unauthorized payment access attempt - Order {$order_id} by user {$authenticatedUserId}");
    header("Location: " . ERROR_URL . "?code=403&message=" . urlencode('You do not have permission to access this order'));
    exit;
}

// Get order details
$orderDetails = $paymentManager->getOrderDetails($order_id);
if (!$orderDetails) {
    header("Location: " . ERROR_URL . "?code=404&message=" . urlencode('Order not found'));
    exit;
}

// Get available payment methods
$paymentMethods = $paymentManager->getAvailablePaymentMethods();

// Check if payment already completed
if (($orderDetails['payment_status'] ?? 'pending') === 'completed') {
    header("Location: " . PROFILE_URL . "?tab=orders");
    exit;
}

// 4. GENERATE PAYMENT ACCESS TOKEN
$paymentAccessToken = generatePaymentAccessToken($order_id, $authenticatedUserId, $authenticatedUserSession);

// 5. SET SECURE HTTPONLY COOKIE WITH TOKEN
setcookie(
    'payment_access_token',
    $paymentAccessToken,
    [
        'expires' => time() + 1800, // 30 minutes
        'path' => '/',
        'domain' => parse_url(BASE_URL, PHP_URL_HOST),
        'secure' => true, // Only HTTPS
        'httponly' => true, // Cannot be accessed via JavaScript
        'samesite' => 'Strict' // CSRF protection
    ]
);

// 6. STORE TOKEN IN SESSION AS WELL
$_SESSION['payment_access_token'] = $paymentAccessToken;
$_SESSION['payment_token_timestamp'] = time();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1" />
 <meta name="csrf-token" content="<?php echo getCsrfToken(); ?>">

  <title>Payment - Universal Store</title>

  <!-- bootstrap CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL;?>/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">

  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL;?>/includes/fontawesome-free-5.15.4-web/css/all.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL;?>/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">

  <!-- custom css file -->
  <link rel="stylesheet" href="<?php echo ASSETS_URL;?>/css/style1.css">
  <link rel="stylesheet" href="<?php echo ASSETS_URL;?>/css/tailwindcss.css">
  <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">

  <!-- JavaScript Error Logger -->
  <script src="<?php echo ASSETS_URL;?>/js/error-logger.js"></script>

  <style>
    .payment-card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      transition: transform 0.3s ease;
    }
    .payment-card:hover {
      transform: translateY(-5px);
    }
    .payment-method {
      border: 2px solid #e9ecef;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 15px;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative; min-height: 15vh; color: white; align-items: center;
    }
    .payment-method.selected {
      border-color: #007bff;
      background-color: #f8f9fa;
    }
    .payment-method:hover {
      border-color: #007bff;
      transform: scale(1.02);
    }
    .method-icon {
      font-size: 2rem;
      margin-bottom: 10px;
    }
    .orange { color: #ff6600; }
    .mtn { color: #ffc107; }
    .camtel { color: #28a745; }
    .yoomee { color: #dc3545; }
    .loading-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.7);
      display: none;
      justify-content: center;
      align-items: center;
      z-index: 9999;
    }
    .spinner {
      width: 50px;
      height: 50px;
      border: 5px solid #f3f3f3;
      border-top: 5px solid #007bff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="text-center text-white">
    <div class="spinner mx-auto mb-3"></div>
    <h4>Processing Payment...</h4>
    <p>Please check your phone for payment confirmation</p>
  </div>
</div>

<!--  Home Section   --->
<div class="checkout-nav">
    <a href="<?php echo universalstore_URL; ?>/index" class="brand-check">
          <img class="logo-ckeck" src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo" />
          <span class="font-rubik" >UNIVERSAL STORE</span>
    </a>
</div>

<section class="my-5 py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card payment-card">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h2 class="mb-0"><i class="fas fa-credit-card me-2"></i>Complete Your Payment</h2>
                    <p class="mb-0 mt-2">Order #<?php echo htmlspecialchars($order_id); ?></p>
                </div>

                <div class="card-body p-4">
                    <!-- Order Summary -->
                    <div class="order-summary mb-4 p-3 bg-light rounded">
                        <h5 class="text-primary mb-3"><i class="fas fa-shopping-cart me-2"></i>Order Summary</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($orderDetails['user_fname'] . ' ' . $orderDetails['user_lname']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($orderDetails['user_email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($orderDetails['user_phone']); ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <p><strong>Total Amount:</strong></p>
                                <h4 class="text-success"><?php echo number_format($orderDetails['order_cost'], 0, ',', ' '); ?> FCFA</h4>
                                <p><strong>Status:</strong>
                                    <span class="badge bg-<?php echo ($orderDetails['payment_status'] ?? 'pending') === 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($orderDetails['payment_status'] ?? 'pending'); ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method Selection -->
                    <div class="payment-methods mb-4">
                        <h5 class="text-primary mb-3"><i class="fas fa-mobile-alt me-2"></i>Select Payment Method</h5>

                        <form id="paymentForm">
                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
                            <input type="hidden" name="payment_access_token" value="<?php echo htmlspecialchars($paymentAccessToken); ?>">
                            <input type="hidden" name="payment_nonce" value="<?php echo bin2hex(random_bytes(16)); ?>"></div>

                            <div class="row">
                                <?php foreach ($paymentMethods as $method): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="payment-method" data-method="<?php echo htmlspecialchars($method['method_name']); ?>">
                                        <div class="text-center">
                                            <div class="method-icon <?php echo strtolower($method['method_name']); ?>">
                                                <?php if ($method['method_name'] === 'ORANGE'): ?>
                                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/om.jpeg?updatedAt=1770682540781" class="w-20" style="justify-self:center;" alt="Orange Money" class="payment-logo" />
                                                    <!-- <i class="fas fa-circle"></i> -->
                                                <?php elseif ($method['method_name'] === 'MTN'): ?>
                                                    <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/MTN.png" class="w-20" style="justify-self:center;" alt="MTN" class="payment-logo" />
                                                    <!-- <i class="fas fa-circle"></i> -->
                                                <?php //elseif ($method['method_name'] === 'CAMTEL'): ?>
                                                    <!-- <img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/camtel.png" class="w-20" style="justify-self:center;" alt="CAMTEL" class="payment-logo" />
                                                    <i class="fas fa-circle"></i> -->
                                                <?php else: ?>
                                                    <i class="fas fa-circle"></i>
                                                <?php endif; ?>
                                            </div>
                                            <h6><?php echo htmlspecialchars($method['display_name']); ?></h6>
                                            <p class="text-muted small"><?php echo htmlspecialchars($method['description']); ?></p>
                                            <?php if ($method['min_amount'] && $method['max_amount']): ?>
                                                <small class="text-muted">
                                                    Min: <?php echo number_format($method['min_amount'], 0, ',', ' '); ?> FCFA -
                                                    Max: <?php echo number_format($method['max_amount'], 0, ',', ' '); ?> FCFA
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Phone Number Input -->
                            <div class="phone-input-section mt-4" style="display: none;">
                                <h6 class="text-primary mb-3"><i class="fas fa-phone me-2"></i>Enter Your Phone Number</h6>
                                <div class="row">
                                    <div class="col-md-8">
                                        <input type="tel" class="form-control form-control-lg" id="payerPhone" name="payer_phone"
                                               placeholder="6XXXXXXXX" pattern="^(6[5-9]|2[2-3])\d{7}$" required>
                                        <small class="form-text text-muted">Enter the phone number linked to your mobile money account</small>
                                    </div>
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-primary btn-lg w-100" id="payBtn" disabled>
                                            <i class="fas fa-paper-plane me-2"></i>Pay Now
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Security Notice -->
                    <div class="alert alert-info">
                        <i class="fas fa-shield-alt me-2"></i>
                        <strong>Secure Payment:</strong> Your payment information is encrypted and processed securely through Mesomb payment gateway.
                        You will receive a confirmation prompt on your phone to complete the transaction.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</section>

<script src="assets/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

<script>

    // ===== PAYMENT SECURITY VALIDATION =====
    
    // Validate payment access token before submission
    function validatePaymentToken() {
        const tokenInput = document.querySelector('input[name="payment_access_token"]');
        const nonce = document.querySelector('input[name="payment_nonce"]');
        
        if (!tokenInput || !tokenInput.value) {
            console.error('Payment access token not found');
            return false;
        }
        
        if (!nonce || !nonce.value) {
            console.error('Payment nonce not found');
            return false;
        }
        
        return true;
    }

    // Check for page reload/redirect tampering
    function setupSecurityMonitoring() {
        let pageLoadTime = performance.now();
        
        // Detect unusual navigation patterns
        window.addEventListener('beforeunload', function() {
            // Log if page is being left during payment
            const form = document.getElementById('paymentForm');
            if (form && !form.classList.contains('processing-payment')) {
                console.log('User navigating away from payment page');
            }
        });
        
        // Detect if page is being accessed from an iframe (clickjacking protection)
        if (window.self !== window.top) {
            console.error('Payment page is being accessed from within an iframe - SECURITY RISK');
            document.body.innerHTML = '<h1>Access Denied</h1>';
            window.top.location.href = window.location.href;
            exit;
        }
    }

    // function to rotate CSRF token on demand (e.g., after failed validation)
    function updateCsrfToken(newToken) {
        const csrfTokenInput = document.querySelector('input[name="csrf_token"]');
        if (csrfTokenInput) {
            csrfTokenInput.value = newToken;
        }
    }

document.addEventListener('DOMContentLoaded', function() {
    // Setup Security Monitoring
    setupSecurityMonitoring();
    
    // Validate payment token
    if (!validatePaymentToken()) {
        alert('Security validation failed. Please return to checkout and try again.');
        window.location.href = '<?php echo universalstore_URL; ?>/checkout';
        return;
    }

    const paymentMethods = document.querySelectorAll('.payment-method');
    const phoneInputSection = document.querySelector('.phone-input-section');
    const payerPhoneInput = document.getElementById('payerPhone');
    const payBtn = document.getElementById('payBtn');
    const paymentForm = document.getElementById('paymentForm');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const csrfToken = document.querySelector('input[name="csrf_token"]');
    const currentCsrftoken = csrfToken ? csrfToken.value : '';
    const paymentAccessToken = document.querySelector('input[name="payment_access_token"]').value;
    let selectedMethod = null;

    // Payment method selection
    paymentMethods.forEach(method => {
        method.addEventListener('click', function() {
            // Remove selected class from all methods
            paymentMethods.forEach(m => m.classList.remove('selected'));
            // Add selected class to clicked method
            this.classList.add('selected');
            selectedMethod = this.dataset.method;

            // Show phone input section
            phoneInputSection.style.display = 'block';
            payerPhoneInput.focus();

            // Update form
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = 'payment_method';
            methodInput.value = selectedMethod;
            methodInput.id = 'paymentMethodInput';

            // Remove existing method input if any
            const existingInput = document.getElementById('paymentMethodInput');
            if (existingInput) {
                existingInput.remove();
            }

            paymentForm.appendChild(methodInput);
        });
    });

    // Phone number validation
    payerPhoneInput.addEventListener('input', function() {
        const phone = this.value.replace(/\D/g, '');
        const isValid = /^(6[5-9]|2[2-3])\d{7}$/.test(phone);

        if (isValid) {
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
            payBtn.disabled = false;
        } else {
            this.classList.remove('is-valid');
            this.classList.add('is-invalid');
            payBtn.disabled = true;
        }
    });

    // Form submission
    paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!selectedMethod) {
            alert('Please select a payment method');
            return;
        }

        const phone = payerPhoneInput.value.replace(/\D/g, '');
        if (!/^(6[5-9]|2[2-3])\d{7}$/.test(phone)) {
            alert('Please enter a valid phone number');
            return;
        }

        // Additional Security Checks
        if (!validatePaymentToken()) {
            alert('Security validation failed. Page may have expired. Please refresh and try again.');
            return;
        }

        // Show loading overlay
        loadingOverlay.style.display = 'flex';
        payBtn.disabled = true;
        paymentForm.classList.add('processing-payment');

        // Prepare form data
        const formData = new FormData(this);
        formData.append('action', 'process_payment');

        // Send payment request with enhanced security headers
        fetch('<?php echo INTERNAL_LOGIC_URL; ?>/process_payment.php', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': currentCsrftoken,
                'X-Payment-Token': paymentAccessToken,
                'X-Requested-From': 'payment-page'
            },
            method: 'POST',
            body: formData,
            credentials : 'same-origin'
        })
        .then(response => {
            // Check response status
            if (!response.ok && response.status !== 400) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            loadingOverlay.style.display = 'none';
            payBtn.disabled = false;
            paymentForm.classList.remove('processing-payment');
            
            const paymentStatusUrlBase = '<?php echo universalstore_URL; ?>/payment_status.php';

            const redirectToStatusPage = (paymentId, message) => {
                if (!paymentId) {
                    alert(message || 'Payment could not be completed. Please try again.');
                    return;
                }
                let url = paymentStatusUrlBase + '?payment_id=' + encodeURIComponent(paymentId);
                if (message) {
                    url += '&error_message=' + encodeURIComponent(message);
                }
                window.location.href = url;
            };

            if (data.success) {
                redirectToStatusPage(data.payment_id, 'Payment initiated successfully. Please check your phone to complete the transaction.');
                return;
            }

            if (data.payment_id) {
                redirectToStatusPage(data.payment_id, data.message || 'Payment failed. Please check your payment status page for details.');
                return;
            }

            alert('Payment failed: ' + (data.message || 'Unknown error'));
        })
        .catch(error => {
            loadingOverlay.style.display = 'none';
            payBtn.disabled = false;
            paymentForm.classList.remove('processing-payment');
            console.error('Payment error:', error);
            alert('An error occurred while processing your payment. Please try again.');
        });
    });
});
</script>
</body>
</html>
