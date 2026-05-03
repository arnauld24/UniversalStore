<?php

// API endpoint for client-side tracking events
// /api/track

require_once __DIR__ . '/../../config/config.php';
require_once INIT_PATH . '/init.php';
require_once SERVER_PATH . '/secure_session_csrf_cookie.php';

use external_server\Connect;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Basic security check - only allow from same domain
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$baseDomain = parse_url(BASE_URL, PHP_URL_HOST);
$refererDomain = parse_url($referer, PHP_URL_HOST);

// Allow requests from the same domain or from XHR/fetch without referer
if ($referer && $refererDomain !== $baseDomain) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied: invalid referer']);
    exit;
}

header('Content-Type: application/json');

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE ||  !is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// SANITIZE AND VALIDATE DATA
$sessionId = isset($input['session_id']) ? preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$input['session_id']) : null;
$eventType = isset($input['event_type']) ? filter_var($input['event_type'], FILTER_SANITIZE_SPECIAL_CHARS) : 'unknown';
// Since this is for AI, we keep the array structure but escape strings within it.
$eventData = is_array($input['event_data'] ?? null) ? $input['event_data'] : [];
array_walk_recursive($eventData, function(&$value){
    if (is_string($value)){
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
});
$rawUrl = $input['page_url'] ?? ($_SERVER['REQUEST_URI'] ?? '/');
$pageUrl = filter_var($rawUrl,FILTER_SANITIZE_URL);
if(!filter_var($pageUrl,FILTER_SANITIZE_URL ) && !str_starts_with($pageUrl, '/')){
    $pageUrl = '/'; // Fallback for invalid paths
}

$inputTime = $input['timestamp'] ?? null;
$timestamp = date('Y-m-d H:i:s'); // Default to current server time

if ($inputTime) {
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $inputTime);
    if ($d && $d->format('Y-m-d H:i:s') === $inputTime) {
        $timestamp = $inputTime;
    }
}

if (!$sessionId || !$eventType) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate session exists
$conn = new Connect();
$stmt = $conn->prepare("SELECT user_id, visitor_id FROM user_sessions WHERE session_id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    // Create session if it doesn't exist (for anonymous visitors)
    $visitorId = getVisitorId();
    $stmt = $conn->prepare("
        INSERT INTO user_sessions (session_id, visitor_id, device_type, browser, os, referrer_url, landing_page)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $sessionId,
        $visitorId,
        getDeviceType(),
        getBrowser(),
        getOS(),
        $_SERVER['HTTP_REFERER'] ?? null,
        $_SERVER['REQUEST_URI']
    ]);
    $userId = null;
} else {
    $userId = $session['user_id'];
    $visitorId = $session['visitor_id'];
}

// Insert tracking event
$ipHash = hash('sha256', $_SERVER['REMOTE_ADDR']);
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

$stmt = $conn->prepare("
    INSERT INTO tracking_events
    (session_id, user_id, visitor_id, event_type, event_category, event_data, timestamp, page_url, ip_address_hash, user_agent, is_ajax)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$eventCategory = determineEventCategory($eventType);

try {
    $stmt->execute([
        $sessionId,
        $userId,
        $visitorId,
        $eventType,
        $eventCategory,
        json_encode($eventData),
        $timestamp,
        $pageUrl,
        $ipHash,
        $userAgent,
        true
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

// Helper functions
function getDeviceType() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/mobile/i', $userAgent)) return 'mobile';
    if (preg_match('/tablet/i', $userAgent)) return 'tablet';
    return 'desktop';
}

function getBrowser() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/Chrome/i', $userAgent)) return 'Chrome';
    if (preg_match('/Firefox/i', $userAgent)) return 'Firefox';
    if (preg_match('/Safari/i', $userAgent)) return 'Safari';
    if (preg_match('/Edge/i', $userAgent)) return 'Edge';
    return 'Unknown';
}

function getOS() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/Windows/i', $userAgent)) return 'Windows';
    if (preg_match('/Mac/i', $userAgent)) return 'macOS';
    if (preg_match('/Linux/i', $userAgent)) return 'Linux';
    if (preg_match('/Android/i', $userAgent)) return 'Android';
    if (preg_match('/iOS/i', $userAgent)) return 'iOS';
    return 'Unknown';
}

function determineEventCategory($eventType) {
    $categories = [
        'engagement' => ['page_view', 'page_unload', 'scroll', 'video_play', 'video_pause', 'image_zoom'],
        'conversion' => ['add_to_cart', 'checkout_start', 'purchase_complete', 'account_created'],
        'security' => ['login_success', 'login_failure', 'password_change'],
        'navigation' => ['search_query', 'outbound_click', 'menu_click'],
        'ai_insight' => ['product_view', 'product_click', 'wishlist_add', 'cart_abandon']
    ];

    foreach ($categories as $category => $events) {
        if (in_array($eventType, $events)) {
            return $category;
        }
    }
    return 'engagement';
}

function getVisitorId() {
    $conn = new Connect();
    // Check if visitor cookie exists
    $visitorId = $_COOKIE['visitor_id'] ?? null;
    if(isset($visitorId)){
        $stmt = $conn->prepare("SELECT * FROM visitors WHERE visitor_id = ?");
        $stmt->execute([$visitorId]);
        // if session[visitor id] match get the one from the db for more security
        if($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            $visitorId = $row['visitor_id'];  // this is the visitor_id that will be use 
            return (string)$visitorId;
        }
    }

    // Create new visitor
    
    $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR']);
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $stmt = $conn->prepare("INSERT INTO visitors (ip_address_hash, user_agent, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$ipHash, $userAgent]);
    $visitorId = $conn->lastInsertId();

    // Set cookie for 1 year
    setcookie('visitor_id', $visitorId, time() + 31536000, '/', '', true, true);

    return $visitorId;
}

?></content>
<parameter name="filePath">c:\xampp\htdocs\PHPPROJECT\universalstore_managementsystem\api\track.php</parameter>
