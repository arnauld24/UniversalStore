<?php

namespace internal_logic;

use Mpdf\Mpdf;
use Exception;
use core\utility;

class InvoiceGenerator {
    
    private $conn;
    
    public function __construct() {
        try {
            $this->conn = new \external_server\Connect();
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
             $redirect_url =  ERROR_URL . "?code=404&message=" . urlencode('ERROR: Internal server error. We encountered a problem, Come later.');
            header("Location: " . $redirect_url);
            exit();
        }
    }

    /**
     * Get invoice data for a specific order
     */
    public function getInvoiceData($orderId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT o.*, 
                       GROUP_CONCAT(CONCAT(oi.product_name, '|', oi.product_quantity, '|', oi.product_price, '|', oi.product_color, '|', oi.product_variant) SEPARATOR ';;;') as products
                FROM orders o
                LEFT JOIN order_items oi ON o.order_id = oi.order_id
                WHERE o.order_id = :order_id
                GROUP BY o.order_id
            ");
            
            $stmt->execute([':order_id' => $orderId]);
            $order = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Parse products
            $products = [];
            if ($order['products']) {
                $productList = explode(';;;', $order['products']);
                foreach ($productList as $product) {
                    $parts = explode('|', $product);
                    $products[] = [
                        'name' => $parts[0],
                        'quantity' => $parts[1],
                        'price' => $parts[2],
                        'color' => $parts[3],
                        'variant' => $parts[4]
                    ];
                }
            }
            
            $order['products'] = $products;
            return $order;
            
        } catch (Exception $e) {
            error_log("Error fetching invoice data: " . $e->getMessage());
             http_response_code(404);
        }
    }

    /**
     * Generate PDF invoice
     */
    public function generatePDF($orderId) {
        try {
            $invoiceData = $this->getInvoiceData($orderId);
            $html = $this->generateHTML($invoiceData);
            
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
            ]);
            
            $mpdf->WriteHTML($html);
            return $mpdf;
            
        } catch (Exception $e) {
            error_log("Error generating PDF: " . $e->getMessage());
             http_response_code(404);
             $redirect_url =  ERROR_URL . "?code=404&message=" . urlencode('ERROR: An error occured. We are repairing the Problem!!!.');
             header("Location: " . $redirect_url);
           exit();
        }
    }

    /**
     * Generate HTML invoice content
     */
    public function generateHTML($orderData) {
        $utility_for_data_decryption = new utility();
        $orderId = htmlspecialchars($orderData['order_id'], ENT_QUOTES, 'UTF-8');
        $FormattedorderId = substr($orderId,0,10) . ' ' . substr($orderId,10,10) . ' ' . substr($orderId,20,10);
        $orderDate = htmlspecialchars($orderData['order_date'], ENT_QUOTES, 'UTF-8');
        $orderStatus = htmlspecialchars($orderData['order_status'], ENT_QUOTES, 'UTF-8');
        $userFname = $utility_for_data_decryption->decryptData(htmlspecialchars($orderData['user_fname'], ENT_QUOTES, 'UTF-8'));
        $userLname = $utility_for_data_decryption->decryptData(htmlspecialchars($orderData['user_lname'], ENT_QUOTES, 'UTF-8'));
        $userEmail = $utility_for_data_decryption->decryptData(htmlspecialchars($orderData['user_email'], ENT_QUOTES, 'UTF-8'));
        $userPhone = $utility_for_data_decryption->decryptData(htmlspecialchars($orderData['user_phone'], ENT_QUOTES, 'UTF-8'));
        $userAddress = $utility_for_data_decryption->decryptData(htmlspecialchars($orderData['user_address'], ENT_QUOTES, 'UTF-8'));
        $userCity = htmlspecialchars($orderData['user_city'], ENT_QUOTES, 'UTF-8');
        $userPostcode = htmlspecialchars($orderData['user_postcode'], ENT_QUOTES, 'UTF-8');
        $userCountry = htmlspecialchars($orderData['user_country'], ENT_QUOTES, 'UTF-8');
        $orderCost = htmlspecialchars($orderData['order_cost'], ENT_QUOTES, 'UTF-8');
        $additionalInfo = htmlspecialchars($orderData['additional_info'], ENT_QUOTES, 'UTF-8');
        
        $paymentStatus = htmlspecialchars($orderData['payment_status'],ENT_QUOTES, 'UTF-8');
        $formattedDate = date('F j, Y \a\t H:i', strtotime($orderDate));
        
        $productsHTML = '';
        $subtotal = 0;
        foreach ($orderData['products'] as $product) {
            $productTotal = (float)$product['price'] * (int)$product['quantity'];
            $subtotal += $productTotal;
            $productsHTML .= '
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 12px 8px; font-size: 12px;">' . htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($product['color'], ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($product['variant'], ENT_QUOTES, 'UTF-8') . '</td>
                <td style="padding: 12px 8px; text-align: center; font-size: 12px;">' . (int)$product['quantity'] . '</td>
                <td style="padding: 12px 8px; text-align: right; font-size: 12px;">' . number_format((float)$product['price'], 0, '.', ',') . ' XAF</td>
                <td style="padding: 12px 8px; text-align: right; font-size: 12px; font-weight: bold;">' . number_format($productTotal, 0, '.', ',') . ' XAF</td>
            </tr>';
        }

        $footerInfo = '';
        if (strtolower($paymentStatus) === 'cancelled' ?? 'failed' ) {
            $footerInfo = '
                    <p style="color:red;"><strong>Payment Status:</strong> ' . ucfirst(str_replace('_', ' ', $paymentStatus)) . '</p>
                    <p><strong>Estimated Delivery:</strong> No Delivery.</p>
                    <p style="margin-top: 15px; border-top: 1px solid #cbd5e1; padding-top: 15px;">Thank you for your purchase! If you have any questions, please contact us at support@universalstore.com</p>';
        } else {
            $footerInfo = '
                    <p><strong>Payment Status:</strong> ' . ucfirst(str_replace('_', ' ', $paymentStatus)) . '</p>
                    <p><strong>Estimated Delivery:</strong> ' . htmlspecialchars($orderData['estimated_delivery_start_date'], ENT_QUOTES, 'UTF-8') . ' to ' . htmlspecialchars($orderData['estimated_delivery_end_date'], ENT_QUOTES, 'UTF-8') . '</p>
                    <p style="margin-top: 15px; border-top: 1px solid #cbd5e1; padding-top: 15px;">Thank you for your purchase! If you have any questions, please contact us at support@universalstore.com</p>';
        }

            $statusInfo = '';
            if(strtolower($orderStatus) === 'on_hold') {
                $statusInfo = '<span class="status-badge status-on_hold">On Hold</span>';
            } elseif(strtolower($orderStatus) === 'shipped') {
                $statusInfo = '<span class="status-badge status-shipped">Shipped</span>';
            } elseif(strtolower($orderStatus) === 'delivered') {
                $statusInfo = '<span class="status-badge status-delivered">Delivered</span>';
            } elseif(strtolower($orderStatus) === 'cancelled') {
                $statusInfo = '<span class="status-badge status-cancelled">Cancelled</span>';
            } else {
                $statusInfo = '<span class="status-badge" style="background: #e2e8f0; color: #64748b;">' . ucfirst(htmlspecialchars($orderStatus, ENT_QUOTES, 'UTF-8')) . '</span>';
            }

        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; color: #1e293b; }
                .invoice-container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); color: white; padding: 30px; border-radius: 8px 8px 0 0; margin-bottom: 30px; }
                .header h1 { margin: 0; font-size: 28px; }
                .header p { margin: 8px 0 0 0; font-size: 14px; opacity: 0.9; }
                .invoice-meta { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .meta-box { flex: 1; }
                .meta-box h3 { margin: 0 0 8px 0; font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: bold; }
                .meta-box p { margin: 0; font-size: 14px; }
                .section-title { font-size: 14px; font-weight: bold; color: #1e293b; margin: 20px 0 12px 0; border-bottom: 2px solid #3b82f6; padding-bottom: 8px; }
                .shipping-info { background: #f8fafc; padding: 15px; border-radius: 6px; margin-bottom: 20px; }
                .shipping-info p { margin: 6px 0; font-size: 13px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                table th { background: #3b82f6; color: white; padding: 12px 8px; text-align: left; font-size: 12px; font-weight: bold; }
                table td { padding: 12px 8px; font-size: 12px; }
                .summary-box { display: flex; justify-content: flex-end; margin-bottom: 30px; }
                .summary-table { width: 300px; }
                .summary-table tr td:first-child { text-align: right; padding-right: 20px; font-size: 12px; color: #64748b; }
                .summary-table tr td:last-child { text-align: right; font-size: 12px; }
                .summary-table tr.total td { border-top: 2px solid #3b82f6; padding-top: 12px; font-weight: bold; font-size: 14px; color: #1e293b; }
                .footer { background: #f1f5f9; padding: 15px; border-radius: 6px; margin-top: 30px; font-size: 11px; color: #64748b; line-height: 1.6; }
                .status-badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
                .status-on_hold { background: #dbeafe; color: #1e40af; }
                .status-shipped { background: #fef3c7; color: #92400e; }
                .status-delivered { background: #dcfce7; color: #166534; }
                .status-cancelled { background: #fee2e2; color: #991b1b; }
                .logo{ width: 40px; height:40px;}
            </style>
        </head>
        <body>
            <div class="invoice-container">
                <!-- Header -->
                
                <div class="header">
                <div style="position:relative; display:flex; gap:1rem">
                <div class="logo"><img src="https://ik.imagekit.io/univ35tore/universal_store/imgs/UNIVERSAL%20STORE.jpg" alt="Universal Store logo" style="border-radius: 4px;"></div>
                <div>    
                <h1>INVOICE</h1>
                <p>Universal Store - Order Receipt & Invoice</p>
                </div>
                </div>
                </div>

                <!-- Invoice Meta -->
                <div class="invoice-meta">
                    <div class="meta-box">
                        <h3>Invoice Number</h3>
                        <p>' . $FormattedorderId . '</p>
                    </div>
                    <div class="meta-box">
                        <h3>Invoice Date</h3>
                        <p>' . $formattedDate . '</p>
                    </div>
                    <div class="meta-box">
                        <h3>Order Status</h3>
                        ' . $statusInfo . '
                         </div>
                </div>

                <!-- Billing & Shipping Info -->
                <div style="display: flex; gap: 30px; margin-bottom: 30px;">
                    <div style="flex: 1;">
                        <h3 class="section-title">Bill To</h3>
                        <p style="margin: 8px 0; font-size: 13px; font-weight: bold;">' . $userFname . ' ' . $userLname . '</p>
                        <p style="margin: 4px 0; font-size: 12px;">' . $userAddress . '</p>
                        <p style="margin: 4px 0; font-size: 12px;">' . $userCity . ', ' . $userPostcode . '</p>
                        <p style="margin: 4px 0; font-size: 12px;">' . $userCountry . '</p>
                        <p style="margin: 8px 0 0 0; font-size: 12px;"><strong>Email:</strong> ' . $userEmail . '</p>
                        <p style="margin: 4px 0; font-size: 12px;"><strong>Phone:</strong> ' . $userPhone . '</p>
                    </div>
                    <div style="flex: 1;">
                        <h3 class="section-title">Shipping Address</h3>
                        <p style="margin: 8px 0; font-size: 13px; font-weight: bold;">' . $userFname . ' ' . $userLname . '</p>
                        <p style="margin: 4px 0; font-size: 12px;">' . $userAddress . '</p>
                        <p style="margin: 4px 0; font-size: 12px;">' . $userCity . ', ' . $userPostcode . '</p>
                        <p style="margin: 4px 0; font-size: 12px;">' . $userCountry . '</p>
                    </div>
                </div>

                <!-- Order Items -->
                <h3 class="section-title">Order Details</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th style="text-align: center;">Quantity</th>
                            <th style="text-align: right;">Unit Price</th>
                            <th style="text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $productsHTML . '
                    </tbody>
                </table>

                <!-- Summary -->
                <div class="summary-box">
                    <table class="summary-table">
                        <tr>
                            <td>Subtotal:</td>
                            <td>' . number_format($subtotal, 0, '.', ',') . ' XAF</td>
                        </tr>
                        <tr>
                            <td>Delivery Fee:</td>
                            <td>0 XAF</td>
                        </tr>
                        <tr class="total">
                            <td>Total Amount:</td>
                            <td>' . number_format((float)$orderCost, 0, '.', ',') . ' XAF</td>
                        </tr>
                    </table>
                </div>

                <!-- Additional Info -->
                ' . (!empty($additionalInfo) ? '<div class="shipping-info"><strong>Special Instructions:</strong><p>' . $additionalInfo . '</p></div>' : '') . '

                <!-- Footer -->
                <div class="footer">
                    ' . $footerInfo . '
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }

}
