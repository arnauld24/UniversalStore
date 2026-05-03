<?php

namespace internal_logic;

use external_server\Connect;
use Exception;

class OrderStatusManager {
    
    private $conn;
    private $invoiceEmail;
    
    public function __construct() {
        try {
            $this->conn = new Connect();
            $this->invoiceEmail = new InvoiceEmail();
        } catch (Exception $e) {
            error_log("OrderStatusManager initialization failed: " . $e->getMessage());
            throw new Exception("Unable to initialize OrderStatusManager.");
        }
    }

    /**
     * Update order status and send appropriate email notifications
     * 
     * @param string $orderId The order ID to update
     * @param string $newStatus The new status (on_hold, shipped, delivered, cancelled)
     * @return bool True if successful, false otherwise
     */
    public function updateOrderStatus($orderId, $newStatus) {
        try {
            // Validate status
            $validStatuses = ['on_hold', 'shipped', 'delivered', 'cancelled'];
            if (!in_array($newStatus, $validStatuses)) {
                error_log("Invalid order status: " . htmlspecialchars($newStatus));
                return false;
            }

            // Get current order details before updating
            $stmt = $this->conn->prepare("
                SELECT o.order_id, o.order_status, o.user_email, o.user_fname, o.user_lname 
                FROM orders o 
                WHERE o.order_id = :order_id 
                LIMIT 1
            ");
            
            $stmt->execute([':order_id' => $orderId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$order) {
                error_log("Order not found: " . htmlspecialchars($orderId));
                return false;
            }

            $currentStatus = $order['order_status'];
            
            // Only update if status is actually changing
            if ($currentStatus === $newStatus) {
                error_log("Order status unchanged for: " . htmlspecialchars($orderId));
                return true; // Not an error, just no change needed
            }

            // Update the order status in database
            $updateStmt = $this->conn->prepare("
                UPDATE orders 
                SET order_status = :new_status, 
                    updated_at = NOW() 
                WHERE order_id = :order_id
            ");
            
            $updateSuccess = $updateStmt->execute([
                ':new_status' => $newStatus,
                ':order_id' => $orderId
            ]);

            if (!$updateSuccess) {
                error_log("Failed to update order status for: " . htmlspecialchars($orderId));
                return false;
            }

            // Send appropriate email notification based on status change
            try {
                $this->sendStatusChangeEmail($orderId, $currentStatus, $newStatus, $order);
            } catch (Exception $e) {
                error_log("Error sending status change email for order " . htmlspecialchars($orderId) . ": " . $e->getMessage());
                // Don't fail the status update because of email error
            }

            return true;

        } catch (Exception $e) {
            error_log("Error updating order status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send appropriate email based on status transition
     */
    private function sendStatusChangeEmail($orderId, $oldStatus, $newStatus, $orderData) {
        $userEmail = $orderData['user_email'] ?? '';
        $userName = ($orderData['user_fname'] ?? '') . ' ' . ($orderData['user_lname'] ?? '');

        if (empty($userEmail)) {
            throw new Exception("User email not found for order: " . $orderId);
        }

        // Send final invoice email when order is delivered
        if ($newStatus === 'delivered' && $oldStatus !== 'delivered') {
            error_log("Sending delivery invoice email for order: " . htmlspecialchars($orderId));
            return $this->invoiceEmail->sendFinalInvoiceEmail($orderId, $userEmail, $userName);
        }

        // Additional emails for other status changes can be added here
        // e.g., shipped notification, cancelled notification, etc.

        return true;
    }

    /**
     * Get order details
     */
    public function getOrderDetails($orderId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM orders 
                WHERE order_id = :order_id 
                LIMIT 1
            ");
            
            $stmt->execute([':order_id' => $orderId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching order details: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all orders by user ID
     */
    public function getUserOrders($userId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM orders 
                WHERE user_id = :user_id 
                ORDER BY order_date DESC
            ");
            
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error fetching user orders: " . $e->getMessage());
            throw $e;
        }
    }
}
