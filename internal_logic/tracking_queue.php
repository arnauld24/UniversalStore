<?php
// tracking_queue.php - Simple queue handler for user tracking
namespace internal_logic;

use external_server\Connect;

class TrackingQueue {
    private $queueFile = __DIR__ . '/../logs/tracking_queue.json';
    private $batchSize = 10; // Process 10 events at a time
    private $maxQueueSize = 1000; // Prevent queue from growing too large

    public function addEvent($event) {
        $queue = $this->loadQueue();
        $queue[] = array_merge($event, ['timestamp' => time()]);
        
        // Prevent queue from growing too large
        if (count($queue) > $this->maxQueueSize) {
            array_shift($queue); // Remove oldest event
        }
        
        $this->saveQueue($queue);
    }

    public function processQueue() {
        $queue = $this->loadQueue();
        if (empty($queue)) return;

        $batch = array_slice($queue, 0, $this->batchSize);
        $remaining = array_slice($queue, $this->batchSize);

        try {
            $this->insertBatchToDatabase($batch);
            $this->saveQueue($remaining);
        } catch (\Exception $e) {
            error_log('Tracking queue processing failed: ' . $e->getMessage());
        }
    }

    private function loadQueue() {
        if (!file_exists($this->queueFile)) {
            return [];
        }
        $data = file_get_contents($this->queueFile);
        return json_decode($data, true) ?: [];
    }

    private function saveQueue($queue) {
        file_put_contents($this->queueFile, json_encode($queue));
    }

    private function insertBatchToDatabase($batch) {
        if (empty($batch)) {
            echo "No events to process\n";
            return;
        }

        echo "Processing " . count($batch) . " events\n";
        
        $conn = new Connect(); // Assuming your DB connection class
        
        $values = [];
        $params = [];
        $paramIndex = 1;
        
        foreach ($batch as $event) {
            $values[] = "(?, ?, ?, ?, ?)";
            $params[] = $event['user_id'] ?? null;
            $params[] = $event['event_type'];
            $params[] = $event['event_data'];
            $params[] = $event['page_url'] ?? '';
            $params[] = date('Y-m-d H:i:s', $event['timestamp']);
        }
        
        $sql = "INSERT INTO user_tracking (user_id, event_type, event_data, page_url, created_at) 
                VALUES " . implode(', ', $values);
        
        try {
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute($params);
            echo "Database insert result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
            echo "Rows affected: " . $stmt->rowCount() . "\n";
        } catch (\Exception $e) {
            echo "Database error: " . $e->getMessage() . "\n";
            error_log('Tracking queue processing failed: ' . $e->getMessage());
        }
    }
}
?>