<?php

use internal_logic\paymentManager;
use internal_logic\InvoiceEmail;
/**
 * Mesomb Payment Webhook Handler
 * Handles real-time payment status updates from Mesomb gateway
 */

require_once '../config/init.php';
require_once '../internal_logic/paymentManager.php';

header('Content-Type: application/json');

// Log all webhook requests for debugging
error_log("Mesomb Webhook Request - Method: " . $_SERVER['REQUEST_METHOD']);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    error_log("Mesomb Webhook: Invalid method " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get webhook data
$input = file_get_contents('php://input');
error_log("Mesomb Webhook Raw Input: " . $input);

$webhookData = json_decode($input, true);

if (!$webhookData) {
    http_response_code(400);
    error_log("Mesomb Webhook: Invalid JSON - " . json_last_error_msg());
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Log webhook data for debugging
error_log("Mesomb Webhook Decoded Data: " . json_encode($webhookData));

// Validate webhook signature (if provided by Mesomb)
$signature = $_SERVER['HTTP_X_MESOMB_SIGNATURE'] ?? null;
if ($signature) {
    error_log("Mesomb Webhook: Signature received: " . $signature);
    // Verify signature if Mesomb provides one
    // Implementation depends on Mesomb's signature verification method
}

// Extract payment data - Mesomb sends external_reference which we set to payment_id
// Also try alternative field names that Mesomb might use
$paymentId = $webhookData['external_reference'] 
    ?? $webhookData['payment_id'] 
    ?? $webhookData['reference_id']
    ?? $webhookData['ref']
    ?? null;

// Extract status - Mesomb might send it in different formats
$status = $webhookData['status'] 
    ?? $webhookData['transaction_status']
    ?? $webhookData['state']
    ?? null;

// Extract transaction details
$transactionId = $webhookData['transaction_id'] 
    ?? $webhookData['id']
    ?? $webhookData['fin_trx_id']
    ?? null;

$amount = $webhookData['amount'] ?? null;

error_log("Mesomb Webhook: Extracted - paymentId=$paymentId, status=$status, transactionId=$transactionId");

if (!$paymentId || !$status) {
    http_response_code(400);
    error_log("Mesomb Webhook: Missing required fields - paymentId=$paymentId, status=$status");
    echo json_encode(['error' => 'Missing required payment data']);
    exit;
}

try {
    $paymentManager = new paymentManager();

    // Update payment status based on webhook
    $updateResult = false;

    switch (strtolower($status)) {
        case 'success':
        case 'completed':
        case 'confirmed':
            error_log("Mesomb Webhook: Processing COMPLETED payment - $paymentId");
            $updateResult = $paymentManager->updatePaymentStatus($paymentId, 'completed');
            break;

        case 'failed':
        case 'error':
        case 'declined':
            error_log("Mesomb Webhook: Processing FAILED payment - $paymentId");
            $updateResult = $paymentManager->updatePaymentStatus($paymentId, 'failed', 'Payment failed or declined');
            break;

        case 'pending':
        case 'processing':
            error_log("Mesomb Webhook: Processing PENDING payment - $paymentId");
            $updateResult = $paymentManager->updatePaymentStatus($paymentId, 'processing');
            break;

        case 'cancelled':
            error_log("Mesomb Webhook: Processing CANCELLED payment - $paymentId");
            $updateResult = $paymentManager->updatePaymentStatus($paymentId, 'cancelled', 'Payment cancelled by user');
            break;

        default:
            error_log("Mesomb Webhook: Unknown payment status received: " . $status);
            http_response_code(400);
            echo json_encode(['error' => 'Unknown payment status: ' . $status]);
            exit;
    }

    if ($updateResult) {
        //Select the order ID associated with this payment for email notification (optional - already sent on completion)
        $orderId = $paymentManager->getOrderIdByPaymentId($paymentId);
        
        // Note: Email is now sent automatically in updatePaymentStatus when status is completed
        // This block is kept for any additional notifications if needed
        if (strtolower($status) === 'success' || strtolower($status) === 'completed' || strtolower($status) === 'confirmed') {
            error_log("Mesomb Webhook: Payment completed for order $orderId, payment $paymentId");
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Payment status updated', 'payment_id' => $paymentId]);
        error_log("Mesomb Webhook: Successfully processed payment $paymentId with result: $updateResult");
    } else {
        http_response_code(500);
        error_log("Mesomb Webhook: Failed to update payment status for $paymentId");
        echo json_encode(['error' => 'Failed to update payment status']);
    }

} catch (Exception $e) {
    error_log("Mesomb Webhook: Processing error: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}

exit;
?>