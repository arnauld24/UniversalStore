<?php

namespace internal_logic;

use external_server\Connect;
use PDOException;
use Exception;
use PDO;
use Throwable;

class OrderDisplayLogic{

    private $conn;
    private $authenticatedUserId;

    public function __construct($authenticatedUserId) {
        $this->authenticatedUserId = $authenticatedUserId;
        try {
            $this->conn = new Connect();
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Unable to connect to the database. Please try again later.");
        } catch (Throwable $t) {
            error_log("Unexpected error during database connection: " . $t->getMessage());
            throw new Exception("An unexpected error occurred. Please try again later.");
        }
    }


    public function isLoggedIn(): bool{
        return !empty($this->authenticatedUserId);
    }
    public function getAuthenticatedUserId(): string{
        return $this->authenticatedUserId;
    }

    public function getOrderId(string $userId){
            // retieve orderId
            $stmt = $this->conn->prepare("SELECT order_id FROM orders WHERE user_id = :user_id ORDER BY order_date DESC LIMIT 1");
            $stmt->execute([':user_id' => $userId]);
            $orderId = $stmt->fetchcolumn();
           if($orderId) return $orderId;
       
    }
    public function getOrderDetails(): array{
        try{
            // Fetch user orders
            $displayOrders = [];
            $orderId = $this->getOrderId($this->authenticatedUserId);
            $stmt = $this->conn->prepare('SELECT o.order_id, o.order_date, o.order_cost, o.order_status, o.user_fname, o.user_lname, o.user_city, o.user_address, o.user_country, o.user_postcode, o.user_phone, o.user_email, o.additional_info,o.estimated_delivery_start_date,o.estimated_delivery_end_date, o.payment_id, oi.product_id, oi.product_quantity, oi.product_name, oi.product_price, oi.product_image, oi.product_color, oi.product_variant, oi.item_prep_day_min, oi.item_prep_day_max, p.product_category, p.product_name FROM orders o JOIN order_items oi ON o.order_id = oi.order_id JOIN products p ON oi.product_id = p.product_id WHERE o.user_id = :user_id ORDER BY o.order_date DESC');
            $stmt->execute([':user_id' => $this->authenticatedUserId]);
            
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                $currentOrderId = (string)$row['order_id'];
                
                // Check if this order already exists in displayOrders
                $orderExists = false;
                foreach($displayOrders as &$order) {
                    if($order['order_id'] === $currentOrderId) {
                        // Add product to existing order
                        $order['products'][] = [
                            'product_id' => (int)$row['product_id'],
                            'product_category' => (string)$row['product_category'],
                            'product_name' => (string)$row['product_name'],
                            'product_price' => (int)$row['product_price'],
                            'product_image' => (string)$row['product_image'],
                            'product_color' => (string)$row['product_color'],
                            'product_variant' => (string)$row['product_variant'],
                            'product_quantity' => (int)$row['product_quantity'],
                            'item_prep_day_min' => (int)$row['item_prep_day_min'],
                            'item_prep_day_max' => (int)$row['item_prep_day_max'],
                            'payment_id' => (string)$row['payment_id']
                        ];
                        $orderExists = true;
                        break;
                    }
                }
                unset($order);
                
                // If order doesn't exist, create it
                if(!$orderExists) {
                    $displayOrders[] = [
                        'order_id' => $currentOrderId,
                        'order_date' => $row['order_date'],
                        'order_cost' => (string)$row['order_cost'],
                        'order_status' => (string)$row['order_status'],
                        'user_fname' => (string)$row['user_fname'],
                        'user_lname' => (string)$row['user_lname'],
                        'user_country' => (string)$row['user_country'],
                        'user_address' => (string)$row['user_address'],
                        'user_city' => (string)$row['user_city'],
                        'user_postcode' => (string)$row['user_postcode'],
                        'user_phone' => (string)$row['user_phone'],
                        'user_email' => (string)$row['user_email'],
                        'additional_info' => (string)$row['additional_info'],
                        'estimated_delivery_start_date' => $row['estimated_delivery_start_date'],
                        'estimated_delivery_end_date' => $row['estimated_delivery_end_date'],
                        'payment_id' => $row['payment_id'],
                        'products' => [
                            [
                                'product_id' => (int)$row['product_id'],
                                'product_category' => (string)$row['product_category'],
                                'product_name' => (string)$row['product_name'],
                                'product_price' => (int)$row['product_price'],
                                'product_image' => (string)$row['product_image'],
                                'product_color' => (string)$row['product_color'],
                                'product_variant' => (string)$row['product_variant'],
                                'product_quantity' => (int)$row['product_quantity'],
                                'item_prep_day_min' => (int)$row['item_prep_day_min'],
                                'item_prep_day_max' => (int)$row['item_prep_day_max'],
                                'payment_id' => (string)$row['payment_id']
                            ]
                        ]
                    ];
                }
            }

        }catch(Exception $e){
            error_log("Error fetching order details for user $this->authenticatedUserId: " . $e->getMessage() . $e->getTraceAsString());
        }
    return $displayOrders;
    }

}




?>