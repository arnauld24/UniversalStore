<?php

include __DIR__ . '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use external_server\Connect;
$conn = new Connect();

/**
 * Logic to select the latest single product from specific categories
 */

// category variables
$categories = [
    "redmi"  => "redmi",
    "iphone" => "iphone",
    "ipad"   => "ipad",
    "pixel"  => "pixel",
    "samsung" => "samsung"
];

$best_products = [];

// Loop through each category to get the newest item (LIMIT 2)
foreach ($categories as $key => $cat_name) {
    try {
        $stmt = $conn->prepare("SELECT * FROM products 
                                WHERE product_category = :product_category 
                                ORDER BY product_id DESC 
                                LIMIT 2");
        
        $stmt->bindParam(':product_category', $cat_name, PDO::PARAM_STR);
        $stmt->execute();
        
        $product = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Only add to array if a product was actually found
        // we use array_merge to add the 1 or 2 new products to the total list.
        if ($product) {
            $best_products = array_merge($best_products, $product);
        }
    } catch (PDOException $e) {
        error_log("Database error while fetching Best product for category '$cat_name': " . $e->getMessage());
        continue;
    }
}


?>