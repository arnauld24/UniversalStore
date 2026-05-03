<?php 

require_once __DIR__ . '../../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use external_server\Connect;

//Initialize class
$conn = new Connect();

// Retrieve from DB new products

$categories = [
    "redmi"  => "redmi",
    "iphone" => "iphone",
    "ipad"   => "ipad",
    "pixel"  => "pixel",
    "samsung" => "samsung",
    "Hp" => "Hp",
    "Dell" => "Dell",
    "MacBooks"  => "MacBooks",
    "xbox" => "xbox",
    "ps" => "ps",
    "Asus"  => "Asus",
    "accessories" => "accessories"
    ];

$new_arrivals = [];

//loop through each category to get the newest item 

foreach($categories as $key => $cat_name){
    try{
        $stmt = $conn->prepare("SELECT * FROM products 
        WHERE product_category = :product_category ORDER BY product_created_at DESC LIMIT 2");
        $stmt->bindParam(':product_category', $cat_name, PDO::PARAM_STR);
        $stmt->execute();

        $product = $stmt->fetchAll(PDO::FETCH_ASSOC);

         // Only add to array if a product was actually found
        // we use array_merge to add the 1 or 2 new products to the total list.
        if($product){
            $new_arrivals = array_merge($new_arrivals, $product);
        }
    }
    catch(PDOException $e){
        error_log("Database error while fetching the Newest product for category '$cat_name': " . $e->getMessage());
        continue;
    }
}
?>