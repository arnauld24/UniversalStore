<?php
// process_tracking_queue.php - Cron job script to process tracking queue
// Run this every 5-10 minutes via cron: */5 * * * * php /path/to/process_tracking_queue.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/internal_logic/tracking_queue.php';

use internal_logic\TrackingQueue;
require_once __DIR__ . '/../external_server/Connect.php';

echo "Processing tracking queue at " . date('Y-m-d H:i:s') . "\n";

$trackingQueue = new TrackingQueue();
$trackingQueue->processQueue();

echo "Tracking queue processed successfully.\n";
?>