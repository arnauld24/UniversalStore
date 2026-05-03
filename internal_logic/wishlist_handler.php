<?php

namespace internal_logic;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use internal_logic\WishlistManager;

// Set error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Ensure user is authenticated
$authenticatedUserId = \core\Controller::getAuthenticatedUserId();
if (!$authenticatedUserId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$action = $input['action'] ?? '';
$product_id = $input['product_id'] ?? null;

if (!$action || !$product_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

try {
    $wishlistManager = new WishlistManager();

    switch ($action) {
        case 'add':
            $result = $wishlistManager->addToWishlist($authenticatedUserId, $product_id);
            break;

        case 'remove':
            $result = $wishlistManager->removeFromWishlist($authenticatedUserId, $product_id);
            break;

        case 'toggle':
            $result = $wishlistManager->toggleWishlist($authenticatedUserId, $product_id);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }

    // Return the result
    header('Content-Type: application/json');
    echo json_encode($result);

} catch (\Exception $e) {
    error_log("Wishlist handler exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error: ' . $e->getMessage()]);
}
