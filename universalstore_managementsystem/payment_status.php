<?php
/**
 * Payment Status Page
 * Shows payment status and handles redirects
 * Enhanced security implementation
 */

require_once __DIR__ . '/../config/config.php';
require_once INIT_PATH . '/init.php';

if (!defined('APP_INIT')) {
    http_response_code(403);
    die('403 Access Denied.');
}

require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

// ===== SECURITY HEADERS =====
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' https:;');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Check authentication
if (empty($authenticatedUserId)) {
    header("Location: " . AUTHENTICATION_URL);
    exit;
}

// Verify referrer to prevent redirects from external sources
$validReferrers = [
    parse_url(universalstore_URL, PHP_URL_HOST),
    parse_url(BASE_URL, PHP_URL_HOST)
];

$referrer = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST) : null;
if ($referrer && !in_array($referrer, $validReferrers)) {
    error_log("Suspicious referrer detected for payment status: " . $_SERVER['HTTP_REFERER']);
    // Log but don't block - allow direct access too
}

// Detect if being accessed from iframe
if (isset($_GET['iframe']) && $_GET['iframe'] === '1') {
    http_response_code(403);
    die('Payment status cannot be accessed from iframe');
}

// Get payment ID from URL or session
$payment_id = htmlspecialchars($_GET['payment_id'] ?? $_SESSION['current_payment_id'] ?? null, ENT_QUOTES, 'UTF-8');

if (!$payment_id) {
    error_log("No payment ID provided for payment status access");
    header("Location: " . BASE_URL . "/index?error=no_payment");
    exit;
}

// Validate payment ID format (prevent injection attacks)
if (preg_match('/[^a-zA-Z0-9_.-]/', $payment_id)) {
    error_log("Invalid payment ID format attempted: " . $payment_id);
    header("Location: " . BASE_URL . "/index?error=invalid_payment_id");
    exit;
}

// Initialize payment manager
$paymentManager = new \internal_logic\paymentManager(null, $authenticatedUserId);

// Get payment details
$paymentDetails = $paymentManager->getPaymentDetails($payment_id);

if (!$paymentDetails) {
    error_log("Payment not found or access denied for: " . $payment_id);
    header("Location: " . BASE_URL . "/index?error=payment_not_found");
    exit;
}

// CRITICAL SECURITY CHECK: Verify payment belongs to authenticated user
if ($paymentDetails['user_id'] !== $authenticatedUserId) {
    error_log("SECURITY ALERT: Unauthorized payment access attempt - User {$authenticatedUserId} tried to access payment {$payment_id} belonging to {$paymentDetails['user_id']}. IP: " . $_SERVER['REMOTE_ADDR']);
    header("Location: " . ERROR_URL . "?code=403&message=" . urlencode("You do not have permission to view this payment"));
    exit;
}

$errorMessage = htmlspecialchars($_GET['error_message'] ?? '', ENT_QUOTES, 'UTF-8');
if ($errorMessage && $paymentDetails['status'] !== 'completed') {
    $paymentDetails['status'] = 'failed';
}

// Get order details
$orderDetails = $paymentManager->getOrderDetails($paymentDetails['order_id']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo getCsrfToken(); ?>">

    <title>Payment Status - Universal Store</title>

    <!-- Bootstrap CDN -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/css/bootstrap.min.css">
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome-free-5.15.4-web/css/all.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/includes/fontawesome/fontawesome-free-6.7.2-web/css/all.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style1.css">
    <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">

    <!-- JavaScript Error Logger -->
    <script src="<?php echo ASSETS_URL; ?>/js/error-logger.js"></script>

    <style>
        .status-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .status-icon {
            font-size: 4rem;
            margin-bottom: 20px;
        }
        .status-success { color: #28a745; }
        .status-pending { color: #ffc107; }
        .status-failed { color: #dc3545; }
        .status-processing { color: #007bff; }
    </style>
</head>
<body>

<!-- Navigation -->
<div class="checkout-nav">
    <a href="<?php echo universalstore_URL; ?>/index" class="brand-check">
        <img class="logo-ckeck" src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo" />
        <span class="font-rubik">UNIVERSAL STORE</span>
    </a>
</div>

<section class="my-5 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card status-card">
                    <div class="card-body text-center p-5">

                        <?php
                        $status = $paymentDetails['status'];
                        $statusClass = '';
                        $statusIcon = '';
                        $statusTitle = '';
                        $statusMessage = '';

                        switch ($status) {
                            case 'completed':
                                $statusClass = 'status-success';
                                $statusIcon = 'fas fa-check-circle';
                                $statusTitle = 'Payment Successful!';
                                $statusMessage = 'Your payment has been processed successfully. You will receive a confirmation email shortly.';
                                break;
                            case 'processing':
                                $statusClass = 'status-processing';
                                $statusIcon = 'fas fa-clock';
                                $statusTitle = 'Payment Processing';
                                $statusMessage = 'Your payment is being processed. Please wait while we confirm the transaction.';
                                break;
                            case 'pending':
                                $statusClass = 'status-pending';
                                $statusIcon = 'fas fa-hourglass-half';
                                $statusTitle = 'Payment Pending';
                                $statusMessage = 'Please check your phone and complete the payment confirmation.';
                                break;
                            case 'failed':
                            case 'cancelled':
                                $statusClass = 'status-failed';
                                $statusIcon = 'fas fa-times-circle';
                                $statusTitle = 'Payment Failed';
                                $failureExplanation = $paymentDetails['failure_reason'] ?? $errorMessage;
                                $statusMessage = 'Your payment could not be processed. ' . ($failureExplanation ?: 'Please try again or contact support.');
                                break;
                            default:
                                $statusClass = 'status-pending';
                                $statusIcon = 'fas fa-question-circle';
                                $statusTitle = 'Payment Status Unknown';
                                $statusMessage = 'We are checking your payment status. Please wait...';
                        }
                        ?>

                        <div class="status-icon <?php echo $statusClass; ?>">
                            <i class="<?php echo $statusIcon; ?>"></i>
                        </div>

                        <h2 class="<?php echo $statusClass; ?> mb-3"><?php echo $statusTitle; ?></h2>

                        <div class="alert alert-<?php echo $status === 'completed' ? 'success' : ($status === 'failed' ? 'danger' : 'info'); ?> mb-4">
                            <?php echo $statusMessage; ?>
                        </div>

                        <!-- Payment Details -->
                        <div class="payment-details text-left mb-4 p-3 bg-light rounded">
                            <h5 class="text-primary mb-3"><i class="fas fa-receipt me-2"></i>Payment Details</h5>
                            <div class="row">
                                <div class="col-sm-6">
                                    <p><strong>Payment ID:</strong><br><?php echo htmlspecialchars($payment_id); ?></p>
                                    <p><strong>Order ID:</strong><br><?php echo htmlspecialchars($paymentDetails['order_id']); ?></p>
                                </div>
                                <div class="col-sm-6">
                                    <p><strong>Amount:</strong><br><?php echo number_format($paymentDetails['amount'], 0, ',', ' '); ?> FCFA</p>
                                    <p><strong>Method:</strong><br><?php echo htmlspecialchars($paymentDetails['payment_method']); ?></p>
                                </div>
                            </div>
                            <p><strong>Status:</strong>
                                <span class="badge bg-<?php echo $status === 'completed' ? 'success' : ($status === 'failed' ? 'danger' : 'warning'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($status),ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </p>
                            <?php if ($paymentDetails['completed_at']): ?>
                                <p><strong>Completed:</strong><br><?php echo date('M j, Y g:i A', strtotime($paymentDetails['completed_at'])); ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <?php if ($status === 'completed'): ?>
                                <a href="<?php echo PROFILE_URL; ?>?tab=orders&order_id=<?php echo urlencode($paymentDetails['order_id']); ?>" class="btn btn-success btn-lg me-2">
                                    <i class="fas fa-eye me-2"></i>View Order
                                </a>
                                <a href="<?php echo universalstore_URL; ?>/shop" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                                </a>
                            <?php elseif ($status === 'failed' || $status === 'cancelled'): ?>
                                <a href="<?php echo universalstore_URL; ?>/payment?order_id=<?php echo urlencode($paymentDetails['order_id']); ?>" class="btn btn-warning btn-lg me-2">
                                    <i class="fas fa-redo me-2"></i>Try Again
                                </a>
                                <a href="<?php echo universalstore_URL; ?>/contact" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-headset me-2"></i>Contact Support
                                </a>
                            <?php else: ?>
                                <button onclick="checkPaymentStatus()" class="btn btn-primary btn-lg me-2" id="checkStatusBtn">
                                    <i class="fas fa-sync me-2"></i>Check Status
                                </button>
                                <a href="<?php echo universalstore_URL; ?>/index" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-home me-2"></i>Back to Home
                                </a>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="<?php echo ASSETS_URL; ?>/includes/bootstrap-5.3.7-dist/js/bootstrap.bundle.min.js"></script>

<?php if ($status !== 'completed' && $status !== 'failed'): ?>
<script>
let lastStatus = '<?php echo $status; ?>';
let checkAttempts = 0;
const MAX_CHECK_ATTEMPTS = 60; // Stop checking after 60 attempts (30 mins with 30s interval)

function checkPaymentStatus() {
    const btn = document.getElementById('checkStatusBtn');
    const originalText = btn ? btn.innerHTML : '';

    // Disable button and show loading
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking...';
    }

    checkAttempts++;
    if (checkAttempts > MAX_CHECK_ATTEMPTS) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
        alert('Payment verification timed out. Please contact support if payment was deducted from your account.');
        return;
    }

    // Check payment status via AJAX
    fetch('<?php echo INTERNAL_LOGIC_URL; ?>/check_payment_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'  // Proper AJAX header
        },
        body: 'payment_id=<?php echo urlencode($payment_id); ?>&csrf_token=<?php echo urlencode(getCsrfToken()); ?>&previous_status=' + encodeURIComponent(lastStatus),
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            lastStatus = data.status;
            
            if (data.status_changed) {
                // Status changed, reload page to show new status
                console.log('Payment status changed to: ' + data.status);
                // Small delay to ensure DB is updated
                setTimeout(() => {
                    location.reload();
                }, 500);
            } else {
                // Status unchanged, update attempt info
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
                
                const message = `Checking... (Attempt ${checkAttempts}/${MAX_CHECK_ATTEMPTS})\n` +
                               'Current Status: ' + (data.status || 'unknown') + '\n' +
                               'Please wait while we confirm your payment.';
                console.log(message);
            }
        } else {
            // Error response but not connection error
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
            console.error('Status check error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Status check fetch error:', error);
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
        // Don't show alert on network errors, just retry silently
    });
}

// Auto-check status every 30 seconds for pending/processing payments
<?php if (in_array($status, ['pending', 'processing'])): ?>
const statusCheckInterval = setInterval(checkPaymentStatus, 30000);
// Uncheck on page unload to prevent errors
window.addEventListener('beforeunload', () => {
    clearInterval(statusCheckInterval);
});
<?php endif; ?>
</script>
<?php endif; ?>

</body>
</html>