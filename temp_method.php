    /**
     * Send payment success email (called by webhook)
     */
    public function sendPaymentSuccessEmail(string $payment_id): bool {
        try {
            // Get payment and order details
            $stmt = $this->conn->prepare("
                SELECT p.*, o.user_email, o.user_fname, o.user_lname
                FROM payments p
                JOIN orders o ON p.order_id = o.order_id
                WHERE p.payment_id = :payment_id
            ");
            $stmt->execute([':payment_id' => $payment_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                throw new Exception('Payment data not found');
            }

            // Send email using InvoiceEmail class
            $invoiceEmail = new \internal_logic\InvoiceEmail();
            return $invoiceEmail->sendPaymentSuccessInvoice($data['order_id'], $payment_id);

        } catch (Exception $e) {
            error_log('Error sending payment success email: ' . $e->getMessage());
            return false;
        }
    }