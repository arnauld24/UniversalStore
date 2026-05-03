<?php
require_once __DIR__ . '/external_server/Connect.php';
$conn = new \external_server\Connect();
$conn->exec("ALTER TABLE payment_logs DROP FOREIGN KEY fk_log_payment");
echo "Foreign key dropped";
?>