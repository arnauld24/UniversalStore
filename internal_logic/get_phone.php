<?php
require_once __DIR__ . '/../config/config.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use external_server\Connect;
$conn = new Connect();

$stmt = $conn->prepare("SELECT * FROM products WHERE product_category = 'samsung' ORDER BY product_id DESC LIMIT 4");
$stmt->execute();

?>