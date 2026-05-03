<?php

use external_server\Connect;

include_once __DIR__ . '/../config/config.php';
include_once SERVER_PATH . '/secure_session_csrf_cookie.php';


//Retrieve related products based on category
function getRelatedProducts($category, $currentProductId) {
    try {
        $conn = new Connect();
        $stmt = $conn->prepare("SELECT * FROM products WHERE product_category = :category AND product_id != :product_id ORDER BY RAND() LIMIT 10");
        $stmt->execute([':category' => $category, ':product_id' => $currentProductId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error occurred while fetching related products: " . $e->getMessage());
        return [];
    }
}





?>