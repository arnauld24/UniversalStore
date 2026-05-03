<?php

namespace internal_logic;

use core\utility;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use external_server\Connect;
use PDO;

const SMTP_HOST = 'smtp.gmail.com';
const SMTP_USER = 'fonkouaarnauld@gmail.com';
const SMTP_PASS = 'zwwkhteflwgpycyq';
const SMTP_SECURE = 'tls';
const SMTP_PORT = 587;

class InvoiceEmail {
    
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }

    /**
     * Setup PHPMailer configuration
     */
    private function setupMailer() {
        try {
            // Check if custom SMTP config exists
            if (defined('SMTP_HOST') && defined('SMTP_USER') && defined('SMTP_PASS')) {
                $this->mailer->isSMTP();
                $this->mailer->Host = SMTP_HOST;
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = SMTP_USER;
                $this->mailer->Password = SMTP_PASS;
                $this->mailer->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
                $this->mailer->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            } else {
                // Fallback to sendmail
                $this->mailer->isSendmail();
            }
            
            $this->mailer->setFrom('fonkouaarnauld@gmail.com', 'Universal Store');
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log("Mailer setup error: " . $e->getMessage());
        }
    }

    /**
     * Send invoice email on order placement
     */
    public function sendInitialInvoiceEmail($orderId, $userEmail, $userName) {
        try {
            $invoiceGenerator = new InvoiceGenerator();
            $html = $invoiceGenerator->generateHTML($invoiceGenerator->getInvoiceData($orderId));
            
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail, htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'));
            
            $this->mailer->Subject = 'Order Invoice #' . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8');
            
            $emailBody = '
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .email-container { max-width: 600px; margin: 0 auto; }
                    .banner { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f8fafc; }
                    .footer { background: #e2e8f0; padding: 15px; text-align: center; font-size: 12px; color: #64748b; }
                    img{ width: 40px; height:40px;}
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="banner">
                     <div style="position:relative; display:flex; gap:1rem">
                    <div class="logo"><img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo" style="border-radius: 4px;"></div>
                    <div>
                        <h1 style="margin: 0;">Order Received!</h1>
                        <p style="margin: 10px 0 0 0;">Thank you for your purchase</p>
                    </div>
                    </div>
                    <div class="content">
                        <p>Dear ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ',</p>
                        <p>Your order has been received and is being processed. Below is your invoice for reference.</p>
                        <p><strong>Order Number:</strong> ' . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . '</p>
                        <p>You can view and download your invoice by logging into your account and visiting the Orders section.</p>
                        <hr style="border: none; border-top: 1px solid #cbd5e1; margin: 20px 0;">
                        ' . $html . '
                        <hr style="border: none; border-top: 1px solid #cbd5e1; margin: 20px 0;">
                        <p>We will send you another invoice once your order is delivered.</p>
                    </div>
                    <div class="footer">
                        <p>Universal Store | Support: support@universalstore.com</p>
                    </div>
                </div>
            </body>
            </html>';
            
            $this->mailer->Body = $emailBody;
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Error sending initial invoice email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send payment success email
     */
    public function sendPaymentSuccessInvoice($orderId, $paymentId) {
        try {
            // Get order and payment details
            $conn = new Connect();
            // Initalize utility for data decrytion
            $utility_for_data_decryption = new utility();
            $stmt = $conn->prepare("
                SELECT o.*, p.payment_method, p.amount as payment_amount, p.completed_at
                FROM orders o
                JOIN payments p ON o.order_id = p.order_id
                WHERE o.order_id = :order_id AND p.payment_id = :payment_id
            ");
            $stmt->execute([':order_id' => $orderId, ':payment_id' => $paymentId]);
            $orderData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$orderData) {
                throw new Exception('Order or payment data not found');
            }

            $rawUerName = $orderData['user_fname'] . ' ' . $orderData['user_lname'];
            $rawUserEmail = $orderData['user_email'];
            $userName = $utility_for_data_decryption->decryptData($rawUerName);
            $userEmail = $utility_for_data_decryption->decryptData($rawUserEmail);
            $orderId = $utility_for_data_decryption->decryptData($orderId);

            $this->mailer->clearAddresses();
            $this->mailer->addAddress($userEmail, htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'));

            $this->mailer->Subject = 'Payment Confirmed - Order #' . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8');

            $emailBody = '
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .email-container { max-width: 600px; margin: 0 auto; }
                    .banner { background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f8fafc; }
                    .payment-details { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 15px; margin: 20px 0; }
                    .footer { background: #e2e8f0; padding: 15px; text-align: center; font-size: 12px; color: #64748b; }
                    .logo{ width: 40px; height:40px;}
                    img{ width: 40px; height:40px;}
                </style>
            </head>
            <body>
                <div class="email-container">
                    <div class="banner">
                    <div style="position:relative; display:flex; gap:1rem">
                    <div class="logo"><img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo" style="border-radius: 4px;"></div>
                    <div>
                        <h1 style="margin: 0;">Payment Successful!</h1>
                        <p style="margin: 10px 0 0 0;">Your payment has been confirmed</p>
                        </div>
                        </div>
                    </div>
                    <div class="content">
                        <p>Dear ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ',</p>

                        <p>Great news! Your payment has been successfully processed. Your order is now being prepared for delivery.</p>

                        <div class="payment-details">
                            <h4 style="margin-top: 0; color: #065f46;">Payment Details</h4>
                            <p><strong>Order Number:</strong> ' . htmlspecialchars($orderId, ENT_QUOTES, 'UTF-8') . '</p>
                            <p><strong>Payment ID:</strong> ' . htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8') . '</p>
                            <p><strong>Payment Method:</strong> ' . htmlspecialchars($orderData['payment_method'], ENT_QUOTES, 'UTF-8') . '</p>
                            <p><strong>Amount Paid:</strong> ' . number_format($orderData['payment_amount'], 0, ',', ' ') . ' FCFA</p>
                            <p><strong>Payment Date:</strong> ' . date('M j, Y g:i A', strtotime($orderData['completed_at'])) . '</p>
                        </div>

                        <p><strong>What happens next?</strong></p>
                        <ul>
                            <li>Your order will be prepared within the estimated timeframe</li>
                            <li>You will receive tracking updates via email</li>
                            <li>A delivery confirmation email will be sent when your order arrives</li>
                        </ul>

                        <p>You can track your order status anytime by logging into your account.</p>
    
                        <div style="text-align: center; margin: 30px 0;">
                            <a href="' . PROFILE_URL . '?tab=orders&order_id=   ' . urlencode($orderId) . '" style="background: #059669; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">
                                View Order Details
                            </a>
                        </div>
                    </div>
                    <div class="footer">
                        <p>Universal Store | Support: support@universalstore.com</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>';

            $this->mailer->Body = $emailBody;
            return $this->mailer->send();

        } catch (Exception $e) {
            error_log("Error sending payment success email: " . $e->getMessage());
            return false;
        }
    }
}
