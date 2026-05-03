<?php
// Ensure autoloader and session helpers are available
require_once __DIR__ . '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use external_server\Connect ;
// Use autoloaded Connect class
$conn = new Connect();

$stmt = $conn->prepare("SELECT * FROM products LIMIT 8");
$featured_products = $stmt->execute();

?>