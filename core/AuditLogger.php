<?php

namespace core;

use external_server\Connect;
use PDO;

/**
 * AuditLogger Class
 * Handles all audit logging for orders, payments, and security events
 */
class AuditLogger {
    private $conn;
    private $user_id;
    private $ip_address;
    private $user_agent;

    public function __construct($user_id = null) {
        $this->conn = new Connect();
        $this->user_id = $user_id ?? ($_SESSION['user_id'] ?? 'SYSTEM');
        $this->ip_address = $this->getClientIP();
        $this->user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    }

    /**
     * Get client IP address (handles proxies)
     */
    private function getClientIP(): string {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP']; // Cloudflare
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
    }

    /**
     * Log order placement
     */
    public function logOrderPlacement($order_id, $status = 'SUCCESS', $description = '', $metadata = []): bool {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, order_id, description, ip_address, user_agent, status, metadata)
                VALUES (:user_id, :action_type, :order_id, :description, :ip_address, :user_agent, :status, :metadata)
            ");

            $metadata_json = json_encode($metadata);
            return $stmt->execute([
                ':user_id' => $this->user_id,
                ':action_type' => 'ORDER_PLACED',
                ':order_id' => $order_id,
                ':description' => $description,
                ':ip_address' => $this->ip_address,
                ':user_agent' => $this->user_agent,
                ':status' => $status,
                ':metadata' => $metadata_json
            ]);
        } catch (\PDOException $e) {
            error_log('Audit log error (ORDER_PLACED): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log payment initiation
     */
    public function logPaymentInitiation($order_id, $payment_method, $amount, $status = 'PENDING', $metadata = []): bool {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, order_id, description, ip_address, user_agent, status, metadata)
                VALUES (:user_id, :action_type, :order_id, :description, :ip_address, :user_agent, :status, :metadata)
            ");

            $description = "Payment initiated via {$payment_method} for amount {$amount} XAF";
            $metadata['payment_method'] = $payment_method;
            $metadata['amount'] = $amount;
            $metadata_json = json_encode($metadata);

            return $stmt->execute([
                ':user_id' => $this->user_id,
                ':action_type' => 'PAYMENT_INITIATED',
                ':order_id' => $order_id,
                ':description' => $description,
                ':ip_address' => $this->ip_address,
                ':user_agent' => $this->user_agent,
                ':status' => $status,
                ':metadata' => $metadata_json
            ]);
        } catch (\PDOException $e) {
            error_log('Audit log error (PAYMENT_INITIATED): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log payment status update
     */
    public function logPaymentStatus($order_id, $payment_id, $old_status, $new_status, $amount = 0, $error = ''): bool {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, order_id, payment_id, description, ip_address, user_agent, status, error_details, metadata)
                VALUES (:user_id, :action_type, :order_id, :payment_id, :description, :ip_address, :user_agent, :status, :error_details, :metadata)
            ");

            $description = "Payment status changed from {$old_status} to {$new_status}";
            $metadata = ['old_status' => $old_status, 'new_status' => $new_status, 'amount' => $amount];
            $metadata_json = json_encode($metadata);

            return $stmt->execute([
                ':user_id' => $this->user_id,
                ':action_type' => 'PAYMENT_STATUS_CHANGED',
                ':order_id' => $order_id,
                ':payment_id' => $payment_id,
                ':description' => $description,
                ':ip_address' => $this->ip_address,
                ':user_agent' => $this->user_agent,
                ':status' => $new_status,
                ':error_details' => $error,
                ':metadata' => $metadata_json
            ]);
        } catch (\PDOException $e) {
            error_log('Audit log error (PAYMENT_STATUS_CHANGED): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log payment completion
     */
    public function logPaymentCompletion($order_id, $payment_id, $amount, $payment_method = '', $status = 'COMPLETED'): bool {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, order_id, payment_id, description, ip_address, user_agent, status, metadata)
                VALUES (:user_id, :action_type, :order_id, :payment_id, :description, :ip_address, :user_agent, :status, :metadata)
            ");

            $description = "Payment completed for amount {$amount} XAF";
            $metadata = ['amount' => $amount, 'payment_method' => $payment_method, 'payment_id' => $payment_id];
            $metadata_json = json_encode($metadata);

            return $stmt->execute([
                ':user_id' => $this->user_id,
                ':action_type' => 'PAYMENT_COMPLETED',
                ':order_id' => $order_id,
                ':payment_id' => $payment_id,
                ':description' => $description,
                ':ip_address' => $this->ip_address,
                ':user_agent' => $this->user_agent,
                ':status' => $status,
                ':metadata' => $metadata_json
            ]);
        } catch (\PDOException $e) {
            error_log('Audit log error (PAYMENT_COMPLETED): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log payment failure
     */
    public function logPaymentFailure($order_id, $payment_id, $amount, $error_message, $payment_method = ''): bool {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, order_id, payment_id, description, ip_address, user_agent, status, error_details, metadata)
                VALUES (:user_id, :action_type, :order_id, :payment_id, :description, :ip_address, :user_agent, :status, :error_details, :metadata)
            ");

            $description = "Payment failed for amount {$amount} XAF";
            $metadata = ['amount' => $amount, 'payment_method' => $payment_method, 'payment_id' => $payment_id];
            $metadata_json = json_encode($metadata);

            return $stmt->execute([
                ':user_id' => $this->user_id,
                ':action_type' => 'PAYMENT_FAILED',
                ':order_id' => $order_id,
                ':payment_id' => $payment_id,
                ':description' => $description,
                ':ip_address' => $this->ip_address,
                ':user_agent' => $this->user_agent,
                ':status' => 'FAILED',
                ':error_details' => htmlspecialchars($error_message),
                ':metadata' => $metadata_json
            ]);
        } catch (\PDOException $e) {
            error_log('Audit log error (PAYMENT_FAILED): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log payment audit details (detailed tracking)
     */
    public function logPaymentAudit($order_id, $payment_id, $payment_method, $amount, $status, $gateway_response = '', $attempt = 1): bool {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO payment_audit_logs 
                (order_id, user_id, payment_id, payment_method, amount, currency, status, payment_gateway, gateway_response, attempt_number, ip_address)
                VALUES (:order_id, :user_id, :payment_id, :payment_method, :amount, :currency, :status, :payment_gateway, :gateway_response, :attempt_number, :ip_address)
            ");

            return $stmt->execute([
                ':order_id' => $order_id,
                ':user_id' => $this->user_id,
                ':payment_id' => $payment_id,
                ':payment_method' => $payment_method,
                ':amount' => $amount,
                ':currency' => 'XAF',
                ':status' => $status,
                ':payment_gateway' => 'MESOMB',
                ':gateway_response' => substr($gateway_response, 0, 2000),
                ':attempt_number' => $attempt,
                ':ip_address' => $this->ip_address
            ]);
        } catch (\PDOException $e) {
            error_log('Audit log error (payment_audit_logs): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log security event
     */
    public function logSecurityEvent($event_type, $description, $status = 'ALERT', $metadata = []): bool {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, description, ip_address, user_agent, status, metadata)
                VALUES (:user_id, :action_type, :description, :ip_address, :user_agent, :status, :metadata)
            ");

            $metadata_json = json_encode($metadata);

            return $stmt->execute([
                ':user_id' => $this->user_id,
                ':action_type' => $event_type,
                ':description' => $description,
                ':ip_address' => $this->ip_address,
                ':user_agent' => $this->user_agent,
                ':status' => $status,
                ':metadata' => $metadata_json
            ]);
        } catch (\PDOException $e) {
            error_log('Audit log error (SECURITY_EVENT): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get audit logs for a specific order
     */
    public function getOrderAuditLogs($order_id, $limit = 50): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM audit_logs 
                WHERE order_id = :order_id 
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            $stmt->bindParam(':order_id', $order_id, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        } catch (\PDOException $e) {
            error_log('Error retrieving audit logs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payment audit logs
     */
    public function getPaymentAuditLogs($order_id, $limit = 50): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM payment_audit_logs 
                WHERE order_id = :order_id 
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            $stmt->bindParam(':order_id', $order_id, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        } catch (\PDOException $e) {
            error_log('Error retrieving payment audit logs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's recent activities
     */
    public function getUserActivities($user_id, $limit = 100): array {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM audit_logs 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit
            ");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
        } catch (\PDOException $e) {
            error_log('Error retrieving user activities: ' . $e->getMessage());
            return [];
        }
    }
}
?>
