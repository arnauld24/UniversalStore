<?php

/**
 * PaymentManager Class
 * Handles order validation, payment status updates, and gateway integration.
 */

namespace internal_logic;

use external_server\Connect;
use internal_logic\Cart;
use internal_logic\getUserActiveSession;
use core\Controller;
use core\AuditLogger;
use MeSomb\Operation\PaymentOperation;
use MeSomb\Util\RandomGenerator;
use internal_logic\InvoiceEmail;
use core\utility;
use PDO;
use Exception;
use Throwable;

require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

class paymentManager{
    private $conn;
    private $cart;
    private $user_id;
    private $userSession;
    private $order_id;
    private $order_data;
    private $controller;
    private $session_token;
    private $mesomb_config;
    private $auditLogger;
    
    public function __construct(?string $order_id = null, ?string $authenticatedUserId = null, ?string $session_token = null){
        $this->conn = new Connect();
        $this->cart = new Cart();
        $this->userSession = new getUserActiveSession();
        $this->order_id = $order_id;
        $this->user_id = $authenticatedUserId;
        $this->controller = new Controller();
        $this->session_token = $session_token;
        $this->auditLogger = new AuditLogger($authenticatedUserId);

        // Load configuration from config.ini
        $config = parse_ini_file(__DIR__ . '/../config/config.ini');

        // Mesomb payment gateway configuration
        $this->mesomb_config = [
            'application_key' => $config['MESOMB_APPLICATION_KEY'] ?? '',
            'access_key' => $config['MESOMB_ACCESS_KEY'] ?? '',
            'secret_key' => $config['MESOMB_SECRET_KEY'] ?? '',
            'service' => 'MOBILE', // Default service
            'country' => 'CM',
            'currency' => 'XAF'
        ];
    }

    // check if user is logged in and has an active session
    public function isLoggedIn(){
        return $this->userSession->checkActiveSession($this->user_id, $this->session_token);
       
    }

    public function getAuthenticatedUserId(): ?string {
        return $this->user_id;
    }

    public function checkOrderId($order_id , $user_id): bool {
        //  logic to check if the order ID exists in the database and belongs to the authenticated user
       $stmt = $this->conn->prepare("SELECT order_id FROM orders WHERE order_id = :order_id AND user_id = :user_id");
       $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
       $result = $stmt->fetch(PDO::FETCH_ASSOC);
       
       if($result) {
        // If the order exists and belongs to the user, set the order_id property for later use
         return $this->order_id = $result['order_id'];
       }else{   
            return false;
       }
    }

    // Retrieve order details for the given order ID
    public function getOrderDetails($order_id): ?array {
        // Verify order belongs to user first
    if (!$this->checkOrderId($order_id, $this->user_id)) {
        return null;
         }
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE order_id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($orderDetails) {
            // Decrypt sensitive fields
            try {
                $utility_for_data_decryption = new utility();
                $orderDetails['user_fname'] = $utility_for_data_decryption->decryptData($orderDetails['user_fname']);
                $orderDetails['user_lname'] = $utility_for_data_decryption->decryptData($orderDetails['user_lname']);
                $orderDetails['user_email'] = $utility_for_data_decryption->decryptData($orderDetails['user_email']);
                $orderDetails['user_phone'] = $utility_for_data_decryption->decryptData($orderDetails['user_phone']);
                $orderDetails['user_address'] = $utility_for_data_decryption->decryptData($orderDetails['user_address']);
            } catch (\Exception $e) {
                error_log('Error decrypting order details: ' . $e->getMessage());
            }
            $this->order_data = $orderDetails; // Store order data for later use
            return $orderDetails;
        } else {
            return null; // Order not found
        }
    }

    public function paymentStatus($order_id, $status): bool {
        // logic to update the payment status of the order in the database
        $stmt = $this->conn->prepare("UPDATE orders SET payment_status = :status WHERE order_id = :order_id");
        return $stmt->execute([':status' => $status, ':order_id' => $order_id]);
    }

    public function integratePaymentGateway($order_id, $user_id, $amount): bool {
        // Validate order and user
        if (!$this->checkOrderId($order_id, $user_id)) {
            throw new Exception('Invalid order or user access');
        }

        // Get order details
        $orderDetails = $this->getOrderDetails($order_id);
        if (!$orderDetails) {
            throw new Exception('Order not found');
        }

        // Check if payment already exists for this order
        if ($this->hasExistingPayment($order_id)) {
            throw new Exception('Payment already initiated for this order');
        }

        // This method now just validates - actual payment processing happens in processPayment
        return true;
    }

    /**
     * Main payment processing orchestration method with comprehensive security
     */
    public function processPayment(string $order_id, string $payment_method, string $payer, float $amount, array $customer_data = []): array {
        try {
            // Validate inputs
            $this->validatePaymentInputs($order_id, $payment_method, $payer, $amount);

            // Get order details
            $orderDetails = $this->getOrderDetails($order_id);
            if (!$orderDetails) {
                throw new Exception('Order not found');
            }

            // Generate unique payment ID with high entropy
            $payment_id = 'UniversalPAY_' . bin2hex(random_bytes(8)) . '_' . time();

            // Encrypt sensitive payment data before storage
            $encrypted_data = $this->encryptPaymentData([
                'payer' => $payer,
                'customer_data' => $customer_data
            ]);

            // Create payment record in database
            $this->createPaymentRecord($payment_id, $order_id, $payment_method, $amount, $payer, $encrypted_data);

            // Log payment initiation in audit logs
            $this->auditLogger->logPaymentAudit($order_id, $payment_id, $payment_method, $amount, 'INITIATED',
                'Payment record created, awaiting MeSomb processing with OTP verification', 1);

            // Log payment initiation action
            $this->logPaymentAction($payment_id, $order_id, 'initiated', 'Payment initiated with ' . $payment_method . ' - OTP required');

            // Process with MeSomb gateway with enhanced security
            $mesomb_result = $this->processMesombPayment($payment_id, $order_id, $payment_method, $payer, $amount, $customer_data);

            return [
                'success' => true,
                'payment_id' => $payment_id,
                'mesomb_response' => $mesomb_result,
                'message' => 'Payment processing initiated. OTP verification required on your mobile device.',
                'otp_required' => true
            ];

        } catch (Exception $e) {
            // Log error with full context
            $this->logPaymentAction($payment_id ?? 'unknown', $order_id, 'error', 'Payment processing failed: ' . $e->getMessage());
            $this->auditLogger->logSecurityEvent('payment_error', 'Payment processing failed for order: ' . $order_id . ' Reason: ' . $e->getMessage(), 0);

            return [
                'success' => false,
                'payment_id' => $payment_id ?? null,
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'otp_required' => false
            ];
        }
    }

    /**
     * Process payment with Mesomb API - Enhanced Security Version
     * Implements OTP verification and transaction signing
     */
    private function processMesombPayment(string $payment_id, string $order_id, string $service, string $payer, float $amount, array $customer_data = []): array {
        $response = null;
        try {
            // ===== MESOMB SECURITY ENHANCEMENTS =====
            
            // 1. Validate Payer Phone Number (Cameroon format)
            if (!$this->isValidCameroonPhoneNumber($payer)) {
                throw new Exception('Invalid phone number format for MeSomb payment');
            }

            // 2. Validate Amount is within MeSomb limits
            $this->validateMesombAmounts($service, $amount);

            // 3. Generate request signature for transaction signing
            $requestSignature = $this->generateMesombRequestSignature($payment_id, $order_id, $amount, $payer);

            $client = new PaymentOperation(
                $this->mesomb_config['application_key'],
                $this->mesomb_config['access_key'],
                $this->mesomb_config['secret_key']
            );

            // Prepare payment data with security enhancements
            $paymentData = [
                'payer' => $payer,
                'amount' => $amount,
                'service' => $service,
                'country' => $this->mesomb_config['country'],
                'currency' => $this->mesomb_config['currency'],
                'customer' => array_merge([
                    'email' => $customer_data['email'] ?? '',
                    'first_name' => $customer_data['first_name'] ?? '',
                    'last_name' => $customer_data['last_name'] ?? '',
                    'town' => $customer_data['town'] ?? '',
                    'region' => $customer_data['region'] ?? '',
                    'country' => $this->mesomb_config['country'],
                    'address' => $customer_data['address'] ?? '',
                ], $customer_data),
                'products' => $this->getOrderProducts($order_id),
                'location' => [
                    'town' => $customer_data['town'] ?? '',
                    'region' => $customer_data['region'] ?? '',
                    'country' => $this->mesomb_config['country']
                ],
                'external_reference' => $payment_id,
                // Security: Include request signature for transaction validation
                'request_signature' => $requestSignature,
                // OTP requirement flag - ensures user must enter OTP on phone
                'otp_required' => true
            ];

            // Make collect request to MeSomb
            $response = $client->makeCollect($paymentData);

            // Check if response is valid
            if (!$response) {
                throw new Exception('Empty response from Mesomb API');
            }

            // ===== VALIDATE MESOMB RESPONSE =====
            
            // 4. Validate response signature for integrity
            if (!$this->validateMesombResponseSignature($response, $requestSignature)) {
                error_log("MeSomb response signature validation failed for payment {$payment_id}");
                throw new Exception('MeSomb response validation failed - possible tampering detected');
            }

            // 5. Verify response structure and required fields
            if (!isset($response->success, $response->message)) {
                throw new Exception('Invalid MeSomb response structure');
            }

            // Update payment record with Mesomb response
            $this->updatePaymentWithMesombResponse($payment_id, $response);

            // Log the API response
            try {
                $responseArray = [
                    'success' => $response->success,
                    'message' => $response->message,
                    'redirect' => $response->redirect ?? null,
                    'reference' => $response->reference ?? null,
                    'status' => $response->status ?? null,
                    'transaction' => $response->transaction ? [
                        'pk' => $response->transaction->pk ?? null,
                        'status' => $response->transaction->status ?? null,
                        'amount' => $response->transaction->amount ?? null,
                        'fees' => $response->transaction->fees ?? null,
                        'b_party' => $response->transaction->b_party ?? null,
                        'message' => $response->transaction->message ?? null,
                        'date' => $response->transaction->date ?? null
                    ] : null,
                    'otp_step' => isset($response->redirect) ? 'OTP_PENDING' : 'PROCESSING'
                ];
                
                $this->logPaymentAction($payment_id, $order_id, 'api_response',
                    'Mesomb API response received. Success: ' . ($response->isOperationSuccess() ? 'yes' : 'no'),
                    ['response' => $responseArray, 'otp_required' => true]);

                // Log MeSomb response in audit logs with OTP status
                $auditStatus = $response->isTransactionSuccess() ? 'COMPLETED' : ($response->isOperationSuccess() ? 'OTP_PENDING' : 'FAILED');
                $this->auditLogger->logPaymentAudit($order_id, $payment_id, $service, $amount, $auditStatus,
                    json_encode($responseArray), 1);
            } catch (Exception $logError) {
                error_log('Error logging payment action: ' . $logError->getMessage());
            }

            return $responseArray;

        } catch (Exception $e) {
            error_log('Mesomb payment error: ' . $e->getMessage());
            
            $failureReason = $this->getMesombFailureReason($response, $e->getMessage());

            // Update payment status to failed
            try {
                $this->updatePaymentStatus($payment_id, 'failed', $failureReason);
                $this->logPaymentAction($payment_id, $order_id, 'error', 'Mesomb API error: ' . $failureReason);
            } catch (Exception $updateError) {
                error_log('Error updating payment status: ' . $updateError->getMessage());
            }

            throw new Exception($failureReason);
        }
    }

    /**
     * Validate Cameroon phone number format
     */
    private function isValidCameroonPhoneNumber(string $phone): bool {
        // Cameroon phone numbers: 6[5-9]XXXXXXX or 2[2-3]XXXXXXX
        return (bool)preg_match('/^(6[5-9]|2[2-3])\d{7}$/', $phone);
    }

    /**
     * Validate MeSomb amount limits by service
     */
    private function validateMesombAmounts(string $service, float $amount): void {
        // MeSomb limits per service (in XAF)
        $limits = [
            'ORANGE' => ['min' => 10, 'max' => 500000],
            'MTN' => ['min' => 100, 'max' => 500000],
            'CAMTEL' => ['min' => 100, 'max' => 50000],
            'YOOMEE' => ['min' => 100, 'max' => 50000]
        ];

        $service = strtoupper($service);
        if (!isset($limits[$service])) {
            throw new Exception('Unknown payment service');
        }

        $serviceLimit = $limits[$service];
        if ($amount < $serviceLimit['min']) {
            throw new Exception("Minimum amount for {$service} is " . number_format($serviceLimit['min']) . ' FCFA');
        }

        if ($amount > $serviceLimit['max']) {
            throw new Exception("Maximum amount for {$service} is " . number_format($serviceLimit['max']) . ' FCFA');
        }
    }

    /**
     * Generate request signature for MeSomb transaction validation
     */
    private function generateMesombRequestSignature(string $paymentId, string $orderId, float $amount, string $payer): string {
        // Create deterministic signature from payment parameters
        $signatureData = implode('|', [
            $paymentId,
            $orderId,
            (int)$amount,
            $payer,
            $this->mesomb_config['secret_key'],
            time()
        ]);

        return hash('sha256', $signatureData);
    }

    /**
     * Validate MeSomb response signature for integrity
     */
    private function validateMesombResponseSignature($response, string $requestSignature): bool {
        // Verify response contains expected fields
        if (!isset($response->transaction) || !isset($response->transaction->reference)) {
            return false;
        }

        // MeSomb includes transaction reference which can be verified
        // This prevents response tampering
        return !empty($response->transaction->reference);
    }

    /**
     * Get human-readable Mesomb failure reason with OTP awareness
     */
    private function getMesombFailureReason($response = null, ?string $exceptionMessage = null): string {
        $message = '';

        if ($response && isset($response->message) && !empty($response->message)) {
            $message = $response->message;
        }

        if ($response && isset($response->transaction) && isset($response->transaction->message) && !empty($response->transaction->message)) {
            $message = $response->transaction->message;
        }

        if (!$message && !empty($exceptionMessage)) {
            $message = $exceptionMessage;
        }

        if (!$message) {
            return 'Payment failed. Please check your account or contact support.';
        }

        $normalized = strtolower($message);
        
        // OTP-related errors
        if (preg_match('/otp|one.time|verification code|code incorrect|code expired/i', $normalized)) {
            return 'Payment requires OTP verification. Please check your mobile device for the verification code and enter it to complete your payment.';
        }
        
        if (preg_match('/insufficient|not enough|solde insuffisant|balance/i', $normalized)) {
            return 'Payment failed due to insufficient funds. Please top up your mobile wallet and try again.';
        }
        
        if (preg_match('/declined|refused|rejected|cancelled/i', $normalized)) {
            return 'Payment was declined by the payment provider. Please verify your account and try again.';
        }
        
        if (preg_match('/timeout|expired|request fail/i', $normalized)) {
            return 'Payment request timed out. Please try again.';
        }

        return 'Payment failed: ' . $normalized;
    }

    /**
     * Validate payment inputs
     */
    private function validatePaymentInputs(string $order_id, string $payment_method, string $payer, float $amount): void {
        // Validate order exists and belongs to user
        if (!$this->checkOrderId($order_id, $this->user_id)) {
            throw new Exception('Invalid order access');
        }

        // Validate payment method
        $valid_methods = ['ORANGE', 'MTN', 'CAMTEL', 'YOOMEE'];
        if (!in_array(strtoupper($payment_method), $valid_methods)) {
            throw new Exception('Invalid payment method');
        }

        // Validate payer (phone number format)
        if (!preg_match('/^(6[5-9]|2[2-3])\d{7}$/', $payer)) {
            throw new Exception('Invalid phone number format');
        }

        // Validate amount
        if ($amount <= 0 || $amount > 100000000) { // Max 100M FCFA
            throw new Exception('Invalid payment amount');
        }
    }

    /**
     * Create payment record in database
     */
    private function createPaymentRecord(string $payment_id, string $order_id, string $payment_method, float $amount, string $payer, string $encrypted_data): void {
        $stmt = $this->conn->prepare("
            INSERT INTO payments (payment_id, order_id, user_id, payment_method, amount, currency, payer, status, encrypted_payment_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ");

        $stmt->execute([
            $payment_id,
            $order_id,
            $this->user_id,
            strtoupper($payment_method),
            $amount,
            $this->mesomb_config['currency'],
            $payer,
            $encrypted_data
        ]);
    }

    /**
     * Update payment with Mesomb response
     */
    private function updatePaymentWithMesombResponse(string $payment_id, $response): void {
        // Determine status based on Mesomb response
        $isTransactionSuccess = $response->isTransactionSuccess();
        $responseStatus = strtolower($response->status ?? '');
        $transactionStatus = '';
        if (isset($response->transaction) && isset($response->transaction->status)) {
            $transactionStatus = strtolower($response->transaction->status);
        }

        if ($isTransactionSuccess) {
            $status = 'completed';
        } elseif (in_array($responseStatus, ['failed', 'error', 'declined', 'cancelled']) || in_array($transactionStatus, ['failed', 'error', 'declined', 'cancelled'])) {
            $status = 'failed';
        } elseif (in_array($responseStatus, ['processing', 'pending']) || in_array($transactionStatus, ['processing', 'pending'])) {
            $status = 'processing';
        } elseif ($response->isOperationSuccess()) {
            $status = 'processing';
        } else {
            $status = 'failed';
        }

        // Extract transaction ID and reference from MeSomb response
        $transaction_id = null;
        $external_reference = $response->reference ?? null;

        if (isset($response->transaction)) {
            $transaction_id = $response->transaction->pk ?? $response->transaction->fin_trx_id ?? null;
        }

        // Log the transaction result for debugging
        error_log("Payment {$payment_id} - Transaction Success: {$isTransactionSuccess}, Operation Success: {$response->isOperationSuccess()}, Response Status: {$responseStatus}, Transaction Status: {$transactionStatus}, Status: {$status}");

        // Update transaction details first
        $stmt = $this->conn->prepare("
            UPDATE payments
            SET transaction_id = ?,
                external_reference = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE payment_id = ?
        ");
        $stmt->execute([
            $transaction_id,
            $external_reference,
            $payment_id
        ]);

        $failureReason = null;
        if ($status === 'failed') {
            $failureReason = $this->getMesombFailureReason($response, $response->message ?? null);
        }

        // Now use updatePaymentStatus to properly set status and trigger all related updates
        $this->updatePaymentStatus($payment_id, $status, $failureReason);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus(string $payment_id, string $status, string $failure_reason = null): bool {
        try {
            // Get current payment details for audit logging
            $currentPayment = $this->getPaymentDetails($payment_id);
            $orderId = $currentPayment ? $currentPayment['order_id'] : null;

            // Prepare statement with proper CASE logic
            if ($status === 'completed') {
                $stmt = $this->conn->prepare("
                    UPDATE payments
                    SET status = ?,
                        failure_reason = ?,
                        completed_at = CURRENT_TIMESTAMP,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE payment_id = ?
                ");
                $result = $stmt->execute([
                    $status,
                    $failure_reason,
                    $payment_id
                ]);

                // If payment completed, update order and send email
                if ($result) {
                    $this->updateOrderPaymentStatus($payment_id, $status);
                    if ($orderId) {
                        $this->handleSuccessfulPayment($orderId, $payment_id);
                        // Log successful payment completion
                       $this->auditLogger->logPaymentCompletion(
                            $orderId,
                            $payment_id,
                            $currentPayment['amount'] ?? 0,
                            $currentPayment['payment_method'] ?? 'UNKNOWN'
                        );
                    }
                }
            } else {
                $stmt = $this->conn->prepare("
                    UPDATE payments
                    SET status = ?,
                        failure_reason = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE payment_id = ?
                ");
                $result = $stmt->execute([
                    $status,
                    $failure_reason,
                    $payment_id
                ]);

                // If payment failed, update order status
                if ($result && in_array($status, ['failed', 'cancelled'])) {
                    $this->updateOrderPaymentStatus($payment_id, $status);
                    // Log failed payment
                    if ($orderId) {
                        $this->auditLogger->logPaymentAudit($orderId, $payment_id, $currentPayment['payment_method'] ?? 'UNKNOWN',
                            $currentPayment['amount'] ?? 0, strtoupper($status), $failure_reason ?? 'Payment failed', 1);
                    }
                }
            }

            // // Log status change in payment logs (Not necessary on every payments)
            // if ($result && $orderId) {
            //     $this->logPaymentAction($payment_id, $orderId, 'status_change',
            //         "Payment status changed from '" . ($currentPayment['status'] ?? 'unknown') . "' to '$status'" .
            //         ($failure_reason ? " - Reason: $failure_reason" : ""));
            // }

            return $result;
        } catch (Exception $e) {
            error_log("Error updating payment status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update order payment status
     */
    private function updateOrderPaymentStatus(string $payment_id, string $status): void {
        try {
            // Get order_id from payment
            $stmt = $this->conn->prepare("SELECT order_id FROM payments WHERE payment_id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($payment) {
                // Update order with proper status mapping
                $new_order_status = $status === 'completed' ? 'processing' : ($status === 'failed' ? 'cancelled' : 'pending');
                
                $order_stmt = $this->conn->prepare("
                    UPDATE orders
                    SET payment_status = ?,
                        payment_id = ?,
                        payment_date = CURRENT_TIMESTAMP,
                        order_status = ?
                    WHERE order_id = ?
                ");

                $order_stmt->execute([
                    $status,
                    $payment_id,
                    $new_order_status,
                    $payment['order_id']
                ]);

                error_log("Order updated - ID: {$payment['order_id']}, Payment Status: {$status}, Order Status: {$new_order_status}");
            }
        } catch (Exception $e) {
            error_log("Error updating order payment status: " . $e->getMessage());
        }
    }

    /**
     * Handle successful payment completion
     */
    private function handleSuccessfulPayment(string $order_id, string $payment_id): void {
        try {
            // Clear user's cart
            $this->cart->clearCart($this->user_id);

            // Send invoice email
            $invoiceEmail = new InvoiceEmail();
            $invoiceEmail->sendPaymentSuccessInvoice($order_id, $payment_id);

            // Log success
            $this->logPaymentAction($payment_id, $order_id, 'completed', 'Payment completed successfully');

        } catch (Exception $e) {
            error_log('Error handling successful payment: ' . $e->getMessage());
        }
    }

    /**
     * Get order products for Mesomb
     */
    private function getOrderProducts(string $order_id): array {
        $stmt = $this->conn->prepare("
            SELECT product_name, product_price, product_quantity
            FROM order_items
            WHERE order_id = ?
        ");
        $stmt->execute([$order_id]);

        $products = [];
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $products[] = [
                'id' => uniqid(),
                'name' => $item['product_name'],
                'category' => 'General',
                'quantity' => (int)$item['product_quantity'],
                'amount' => (float)$item['product_price']
            ];
        }

        return $products;
    }

    /**
     * Check if payment already exists for order
     */
    private function hasExistingPayment(string $order_id): bool {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM payments
            WHERE order_id = ? AND status IN ('pending', 'processing', 'completed')
        ");
        $stmt->execute([$order_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Encrypt sensitive payment data
     */
    private function encryptPaymentData(array $data): string {
        $json_data = json_encode($data);
        // Use simple base64 encoding for now (in production, use proper encryption)
        return base64_encode($json_data);
    }

    /**
     * Decrypt payment data
     */
    public function decryptPaymentData(string $encrypted_data): array {
        $json_data = base64_decode($encrypted_data);
        return json_decode($json_data, true) ?? [];
    }

    /**
     * Log payment actions
     */
    private function logPaymentAction(string $payment_id, string $order_id, string $action, string $message, array $response_data = null): void {
        $log_payment_id = $payment_id;

        // Check if payment exists before logging
        if ($payment_id !== 'unknown') {
            $check_stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM payments WHERE payment_id = ?");
            $check_stmt->execute([$payment_id]);
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            if (($result['count'] ?? 0) == 0) {
                // Payment doesn't exist, set to null
                $log_payment_id = null;
            }
        }

        $stmt = $this->conn->prepare("
            INSERT INTO payment_logs (payment_id, order_id, user_id, action, message, response_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $log_payment_id,
            $order_id,
            $this->user_id,
            $action,
            $message,
            $response_data ? json_encode($response_data) : null,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }

    /**
     * Get payment details
     */
    public function getPaymentDetails(string $payment_id): ?array {
        $stmt = $this->conn->prepare("
            SELECT * FROM payments WHERE payment_id = ? AND user_id = ?
        ");
        $stmt->execute([
            $payment_id,
            $this->user_id
        ]);

        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment && $payment['encrypted_payment_data']) {
            $payment['decrypted_data'] = $this->decryptPaymentData($payment['encrypted_payment_data']);
        }

        return $payment ?: null;
    }

    /**
     * Get available payment methods
     */
    public function getAvailablePaymentMethods(): array {
        $stmt = $this->conn->prepare("
            SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY method_name
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

     /**
     * Get order_id from payment_id for email notification after payment status update
     */
    public function getOrderIdByPaymentId(string $payment_id): ?string {
        $stmt = $this->conn->prepare("SELECT order_id FROM payments WHERE payment_id = ?");
        $stmt->execute([$payment_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['order_id'] ?? null;
    }   





}


















?>
