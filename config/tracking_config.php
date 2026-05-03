<?php
// tracking_config.php - Configuration for user tracking

return [
    'enabled' => true, // Master switch for tracking
    'sampling_rate' => 1.0, // Track 100% of events (0.1 = 10%, 1.0 = 100%)
    'events_to_track' => [
        'product_view' => true,
        'cart_add' => true,
        'cart_remove' => true,
        'cart_update' => true,
        'checkout_start' => true,
        'form_submit' => false, // Disable form submission tracking
        'page_scroll' => false, // Disable scroll tracking
        'time_on_page' => false, // Disable time tracking
    ],
    'queue_batch_size' => 10,
    'max_queue_size' => 1000,
    'async_tracking' => true, // Use AJAX for non-blocking tracking
];
?></content>
<parameter name="filePath">c:\xampp\htdocs\PHPPROJECT\universalstore_managementsystem\config\tracking_config.php