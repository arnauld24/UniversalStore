<?php

/**
 * Generate wishlist button HTML for products
 * @param int $product_id The product ID
 * @param bool $is_in_wishlist Whether the product is in user's wishlist
 * @param string $css_class Additional CSS classes
 * @return string HTML for wishlist button
 */
function generateWishlistButton($product_id, $is_in_wishlist = false, $css_class = '') {
    $heart_class = $is_in_wishlist ? 'fas text-red-500' : 'far text-gray-400 hover:text-red-500';
    $title = $is_in_wishlist ? 'Remove from wishlist' : 'Add to wishlist';

    return '<button class="wishlist-btn ' . $css_class . '" ' .
           'data-product-id="' . htmlspecialchars($product_id, ENT_QUOTES, 'UTF-8') . '" ' .
           'data-in-wishlist="' . ($is_in_wishlist ? '1' : '0') . '" ' .
           'title="' . $title . '" ' .
           'onclick="toggleWishlist(' . htmlspecialchars($product_id, ENT_QUOTES, 'UTF-8') . ', this)">' .
           '<i class="' . $heart_class . ' fa-heart"></i>' .
           '</button>';
}

/**
 * Check if product is in user's wishlist (only if user is logged in)
 * @param string $user_id User ID
 * @param int $product_id Product ID
 * @return bool Whether product is in wishlist
 */
function isProductInWishlist($user_id, $product_id) {
    if (!$user_id) return false;

    static $wishlist_manager = null;
    if ($wishlist_manager === null) {
        require_once __DIR__ . '/../internal_logic/WishlistManager.php';
        $wishlist_manager = new \internal_logic\WishlistManager();
    }

    return $wishlist_manager->isInWishlist($user_id, $product_id);
}
