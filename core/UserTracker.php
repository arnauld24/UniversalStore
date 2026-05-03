<?php

// User Tracking Utility Functions
//To be Included this in files that need tracking
namespace core;
require_once __DIR__ . '/../config/config.php';

use external_server\Connect;

class UserTracker {

    private static $conn = null;
    private static $sessionId = null;

    public static function init() {
        if (self::$conn === null) {
            self::$conn = new Connect();
        }

        // Get or create session ID
        self::$sessionId = $_COOKIE['tracking_session'] ?? self::generateSessionId();

        // Set session cookie (30 days)
        // Respect secure when available; make this cookie accessible to JS
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        if (PHP_VERSION_ID >= 70300) {
            setcookie('tracking_session', self::$sessionId, [
                'expires' => time() + 86400 * 30,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax'
            ]);
        } else {
            // Fallback for older PHP: secure flag only, httponly set to false so JS can read it
            setcookie('tracking_session', self::$sessionId, time() + 86400 * 30, '/', '', $secure, false);
        }

        // Initialize or update session in database
        self::initializeSession();
    }

    public static function trackEvent($eventType, $eventData = [], $category = null) {
        if (self::$sessionId === null) {
            self::init();
        }

        global $authenticatedUserId;

        // Get visitor ID
        $visitorId = self::getVisitorId();

        // Determine category if not provided
        if ($category === null) {
            $category = self::determineEventCategory($eventType);
        }

        // Anonymize IP
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        $stmt = self::$conn->prepare("
            INSERT INTO tracking_events
            (session_id, user_id, visitor_id, event_type, event_category, event_data, page_url, ip_address_hash, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            self::$sessionId,
            $authenticatedUserId ?? null,
            $visitorId,
            $eventType,
            $category,
            json_encode($eventData),
            $_SERVER['REQUEST_URI'] ?? '',
            $ipHash,
            $userAgent
        ]);

        return true;
    }

    public static function trackProductInteraction($productId, $interactionType, $additionalData = []) {
        $data = array_merge([
            'product_id' => $productId,
            'interaction_type' => $interactionType
        ], $additionalData);

        return self::trackEvent('product_interaction', $data, 'ai_insight');
    }

    public static function trackSearchQuery($query, $resultsCount = 0, $category = null, $filters = []) {
        $data = [
            'search_query' => $query,
            'results_count' => $resultsCount,
            'category' => $category,
            'filters' => $filters
        ];

        return self::trackEvent('search_query', $data, 'navigation');
    }

    public static function trackCartEvent($step, $cartValue = 0, $itemsCount = 0, $products = []) {
        $data = [
            'step' => $step,
            'cart_value' => $cartValue,
            'items_count' => $itemsCount,
            'products' => $products
        ];

        return self::trackEvent('cart_' . $step, $data, 'conversion');
    }

    public static function mergeVisitorToUser($userId, $visitorId) {
        // Ensure connection is initialized
        if (self::$conn === null) {
            self::$conn = new Connect();
        }

        // Update visitor record
        $stmt = self::$conn->prepare("UPDATE visitors SET merged_to_user_id = ? WHERE visitor_id = ?");
        $stmt->execute([$userId, $visitorId]);

        // // Update user record
        // $stmt = self::$conn->prepare("UPDATE users SET original_visitor_id = ? WHERE user_id = ?");
        // $stmt->execute([$visitorId, $userId]);

        // Update all existing sessions and events
        $stmt = self::$conn->prepare("UPDATE user_sessions SET user_id = ? WHERE visitor_id = ?");
        $stmt->execute([$userId, $visitorId]);

        $stmt = self::$conn->prepare("UPDATE tracking_events SET user_id = ? WHERE visitor_id = ?");
        $stmt->execute([$userId, $visitorId]);
    }

    private static function initializeSession() {
        global $authenticatedUserId;

        $visitorId = self::getVisitorId();
        $userId = $authenticatedUserId ?? null;

        // Check if session exists
        $stmt = self::$conn->prepare("SELECT session_id FROM user_sessions WHERE session_id = ?");
        $stmt->execute([self::$sessionId]);

        if (!$stmt->fetch()) {
            // Create new session
            $stmt = self::$conn->prepare("
                INSERT INTO user_sessions
                (session_id, user_id, visitor_id, device_type, browser, os, referrer_url, landing_page, is_logged_in)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                self::$sessionId,
                $userId,
                $visitorId,
                self::getDeviceType(),
                self::getBrowser(),
                self::getOS(),
                $_SERVER['HTTP_REFERER'] ?? null,
                $_SERVER['REQUEST_URI'] ?? '',
                $userId !== null
            ]);
        } else {
            // Update existing session
            $stmt = self::$conn->prepare("
                UPDATE user_sessions SET
                user_id = ?,
                is_logged_in = ?,
                end_time = NULL
                WHERE session_id = ?
            ");
            $stmt->execute([$userId, $userId !== null, self::$sessionId]);
        }
    }

    private static function getVisitorId() {
        $visitorId = $_COOKIE['visitor_id'] ?? null;
        if ($visitorId) {
            return $visitorId;
        }

        // Create new visitor
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

        $stmt = self::$conn->prepare("INSERT INTO visitors (ip_address_hash, user_agent, first_visit) VALUES (?, ?, NOW())");
        $stmt->execute([$ipHash, $userAgent]);
        $visitorId = self::$conn->lastInsertId();

        setcookie('visitor_id', $visitorId, time() + 31536000, '/', '', true, true);

        return $visitorId;
    }

    private static function generateSessionId() {
        return bin2hex(random_bytes(16));
    }

    private static function getDeviceType() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/mobile/i', $userAgent)) return 'mobile';
        if (preg_match('/tablet/i', $userAgent)) return 'tablet';
        return 'desktop';
    }

    private static function getBrowser() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/Chrome/i', $userAgent)) return 'Chrome';
        if (preg_match('/Firefox/i', $userAgent)) return 'Firefox';
        if (preg_match('/Safari/i', $userAgent)) return 'Safari';
        if (preg_match('/Edge/i', $userAgent)) return 'Edge';
        return 'Unknown';
    }

    private static function getOS() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (preg_match('/Windows/i', $userAgent)) return 'Windows';
        if (preg_match('/Mac/i', $userAgent)) return 'macOS';
        if (preg_match('/Linux/i', $userAgent)) return 'Linux';
        if (preg_match('/Android/i', $userAgent)) return 'Android';
        if (preg_match('/iOS/i', $userAgent)) return 'iOS';
        return 'Unknown';
    }

    private static function determineEventCategory($eventType) {
        $categories = [
            'engagement' => ['page_view', 'page_unload', 'scroll', 'video_play', 'video_pause', 'image_zoom'],
            'conversion' => ['add_to_cart', 'checkout_start', 'purchase_complete', 'account_created', 'cart_view', 'cart_checkout_start', 'cart_payment_info', 'cart_shipping_info', 'cart_order_complete'],
            'security' => ['login_success', 'login_failure', 'password_change', 'email_change'],
            'navigation' => ['search_query', 'outbound_click', 'menu_click'],
            'ai_insight' => ['product_interaction', 'product_view', 'product_click', 'wishlist_add', 'wishlist_remove']
        ];

        foreach ($categories as $category => $events) {
            if (in_array($eventType, $events)) {
                return $category;
            }
        }
        return 'engagement';
    }

    public static function trackLoginEvent(string $status, array $details = []) {
        $eventType = $status === 'success' ? 'login_success' : 'login_failure';
        $category = 'security';

        return self::trackEvent($eventType, array_merge(['status' => $status], $details), $category);
    }

    public static function trackOrderEvent(string $order_id, float $order_value, int $item_count, string $status = 'placed/onhold') {
        return self::trackEvent('order_' . $status, [
            'order_id' => $order_id,
            'order_value' => $order_value,
            'items_count' => $item_count
        ], 'conversion');
    }
}

// Do not auto-initialize on include; initialization is explicit and
// should be performed by bootstrap (config/init.php) after visitor
// setup completes to avoid ordering and DB schema issues.

?>