<?php
require_once __DIR__ . '/external_server/Connect.php';
$conn = new \external_server\Connect();
$result = $conn->query("DESCRIBE payment_logs");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>