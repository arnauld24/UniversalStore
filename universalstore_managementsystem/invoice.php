<?php 

// INITIALIZATION & SECURITY
require_once __DIR__ . '/../config/config.php';
require_once INIT_PATH . '/init.php';

// Security check
if (!defined('APP_INIT')) {
    error_log('Attempted direct access to secure utility file: ' . $_SERVER['REQUEST_URI']);
    http_response_code(403); 
    header("Location: " . ERROR_URL . "?code=403&message=" . urlencode("Access Denied."));
    exit('403 Access Denied.'); 
}

require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use core\Controller;
use internal_logic\InvoiceGenerator;

// Authentication check
$authenticatedUserId = Controller::getAuthenticatedUserId();

if (!$authenticatedUserId) {
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $_SESSION['redirect_after_oauth'] = $current_url;
    header("Location: " . AUTHENTICATION_URL);
    exit;
}

// Get order ID from URL parameter
$orderId = isset($_GET['order_id']) ? trim($_GET['order_id']) : null;

if (empty($orderId)) {
    http_response_code(403);
    error_log('"invoice.php" => Security error: Order ID is required.');
    $redirect_url = ERROR_URL . "?code=403&message=" . urlencode('Security error: invoice access requires a valid order ID.');
    header("Location: " . $redirect_url);
    exit;
}

// Verify the authenticated user owns this order
try {
    $db = new \external_server\Connect();
    $verifyStmt = $db->prepare("SELECT 1 FROM orders WHERE order_id = :order_id AND user_id = :user_id LIMIT 1");
    $verifyStmt->execute([':order_id' => $orderId, ':user_id' => $authenticatedUserId]);
    $ownsOrder = $verifyStmt->fetchColumn();

    if (!$ownsOrder) {
        http_response_code(403);
        error_log('"invoice.php" => Security error: User ' . $authenticatedUserId . ' attempted unauthorized access to order ' . $orderId);
        $redirect_url = ERROR_URL . "?code=403&message=" . urlencode('Security error: You are not authorized to view this invoice.');
        header("Location: " . $redirect_url);
        exit;
    }
} catch (Exception $e) {
    error_log('"invoice.php" => Database verification failed: ' . $e->getMessage());
    http_response_code(500);
    $redirect_url = ERROR_URL . "?code=500&message=" . urlencode('Security error: Unable to verify invoice access.');
    header("Location: " . $redirect_url);
    exit;
}

try {
    $invoiceGenerator = new InvoiceGenerator();
    
    // Check if download is requested
    if (isset($_GET['download']) && $_GET['download'] === '1') {
        $pdf = $invoiceGenerator->generatePDF($orderId);
        $filename = 'Invoice_' . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        
        $pdf->Output($filename, 'D');
        exit;
    }

    $invoiceData = $invoiceGenerator->getInvoiceData($orderId);
    if (empty($invoiceData) || !is_array($invoiceData)) {
        throw new Exception('Invoice data is unavailable.');
    }
    $html = $invoiceGenerator->generateHTML($invoiceData);
} catch (Exception $e) {
    error_log("Invoice error: " . $e->getMessage());
    http_response_code(404);
    $redirect_url = ERROR_URL . "?code=404&message=" . urlencode('ERROR: ORDER NOT FOUND OR ACCESS DENIED.');
    header("Location: " . $redirect_url);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="<?php echo ASSETS_URL; ?>/css/tailwind.css" rel="stylesheet">
    <link href="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" rel="icon">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .invoice-wrapper { box-shadow: none; border: none; }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 md:p-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header Actions -->
        <div class="no-print mb-6 flex items-center justify-between gap-4">
            <h1 class="text-3xl font-bold text-slate-800">Invoice</h1>
            <div class="flex gap-3">
                <button onclick="window.print()" class="px-4 py-2 bg-black cursor-pointer hover:bg-slate-700 text-white rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Print
                </button>
                <a href="?order_id=<?php echo urlencode($orderId); ?>&download=1" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Download PDF
                </a>
                <a href="<?php echo PROFILE_URL; ?>?tab=orders" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-lg text-sm font-semibold transition-all">
                    Back to Orders
                </a>
            </div>
        </div>

        <!-- Invoice Container -->
        <div class="invoice-wrapper bg-white rounded-lg shadow-lg overflow-hidden">
            <?php echo $html; ?>
        </div>
    </div>

    <script>
        // Prevent accidental navigation
        window.onbeforeunload = function() {
            return false;
        };
    </script>
</body>
</html>
