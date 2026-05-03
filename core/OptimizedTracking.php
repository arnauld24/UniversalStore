<?php
// OptimizedTracking.php - Efficient user tracking with queue and configuration
namespace core;

require_once __DIR__ . '/../internal_logic/tracking_queue.php';
use external_server\Connect;
use internal_logic\TrackingQueue;

class OptimizedTracking {
    private static $config;
    private static $queue;

    public static function init() {
        self::$config = require __DIR__ . '/../config/tracking_config.php';
        self::$queue = new TrackingQueue();
    }

    public static function trackEvent($eventType, $eventData = [], $userId = null, $pageUrl = null) {
        if (!self::$config['enabled']) {
            return;
        }

        // Check if this event type should be tracked
        if (!isset(self::$config['events_to_track'][$eventType]) || 
            !self::$config['events_to_track'][$eventType]) {
            return;
        }

        // Apply sampling
        if (mt_rand(1, 100) / 100 > self::$config['sampling_rate']) {
            return;
        }

        $event = [
            'user_id' => $userId,
            'event_type' => $eventType,
            'event_data' => json_encode($eventData),
            'page_url' => $pageUrl ?: $_SERVER['REQUEST_URI']
        ];

        if (self::$config['async_tracking']) {
            // For AJAX requests, queue immediately
            self::$queue->addEvent($event);
        } else {
            // For synchronous requests, still queue to avoid blocking
            self::$queue->addEvent($event);
        }
    }

    public static function trackProductView($productId, $productName, $category, $price, $userId = null) {
        self::trackEvent('product_view', [
            'product_id' => $productId,
            'product_name' => $productName,
            'category' => $category,
            'price' => $price
        ], $userId);
    }

    public static function trackCartEvent($eventType, $total = 0, $itemCount = 0, $productIds = []) {
        self::trackEvent('cart_' . $eventType, [
            'total' => $total,
            'item_count' => $itemCount,
            'product_ids' => $productIds
        ]);
    }

    public static function trackFormSubmission($formType, $formData = []) {
        self::trackEvent('form_submit', [
            'form_type' => $formType,
            'data' => $formData
        ]);
    }

    // JavaScript helper for async tracking
    public static function getAsyncTrackingScript() {
        if (!self::$config['async_tracking']) {
            return '';
        }

        return "
        <script>
        window.trackEvent = function(eventType, eventData) {
            fetch('/track-event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    event_type: eventType,
                    event_data: eventData,
                    page_url: window.location.href
                })
            }).catch(err => console.error('Tracking failed:', err));
        };
        </script>
        ";
    }
}

// Initialize tracking
OptimizedTracking::init();
?>