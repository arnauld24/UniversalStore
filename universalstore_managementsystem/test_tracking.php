<?php
// test_tracking.php - Test script to verify tracking is working

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/internal_logic/tracking_queue.php';

use internal_logic\TrackingQueue;

echo "Testing TrackingQueue...\n";

// Create queue instance
$queue = new TrackingQueue();

// Test adding an event
echo "Adding test event...\n";
$queue->addEvent([
    'user_id' => 1,
    'event_type' => 'test_event',
    'event_data' => json_encode(['test' => 'data']),
    'page_url' => 'http://test.com'
]);

echo "Test event added. Checking queue file...\n";

// Check if queue file exists
$queueFile = __DIR__ . '/logs/tracking_queue.json';
if (file_exists($queueFile)) {
    $queueData = json_decode(file_get_contents($queueFile), true);
    echo "Queue file exists. Events in queue: " . count($queueData) . "\n";
    if (!empty($queueData)) {
        echo "Last event: " . json_encode(end($queueData)) . "\n";
    }
} else {
    echo "Queue file does not exist at: $queueFile\n";
}

echo "Test complete.\n";
?></content>
<parameter name="filePath">c:\xampp\htdocs\PHPPROJECT\universalstore_managementsystem\test_tracking.php