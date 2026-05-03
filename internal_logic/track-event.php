<?php
// track-event.php - AJAX endpoint for async tracking

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/OptimizedTracking.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['event_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    OptimizedTracking::trackEvent(
        $input['event_type'],
        $input['event_data'] ?? [],
        $authenticatedUserId ?? null,
        $input['page_url'] ?? null
    );
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Async tracking failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Tracking failed']);
}
?></content>
<parameter name="filePath">c:\xampp\htdocs\PHPPROJECT\universalstore_managementsystem\track-event.php