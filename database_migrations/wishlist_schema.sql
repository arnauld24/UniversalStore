-- Wishlist System Database Schema for UniversalStore

-- Create wishlists table
CREATE TABLE IF NOT EXISTS wishlists (
    wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    product_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,

    -- Indexes for performance
    INDEX idx_user_wishlist (user_id, added_at DESC),
    INDEX idx_product_wishlist (product_id),
    INDEX idx_user_product (user_id, product_id),

    -- Ensure no duplicate user-product combinations
    UNIQUE KEY unique_user_product (user_id, product_id)
);

-- Create wishlist_shares table (optional - for sharing wishlists)
CREATE TABLE IF NOT EXISTS wishlist_shares (
    share_id INT AUTO_INCREMENT PRIMARY KEY,
    wishlist_id INT NOT NULL,
    shared_by_user_id VARCHAR(255) NOT NULL,
    shared_with_user_id VARCHAR(255) NULL, -- NULL for public shares
    share_token VARCHAR(255) UNIQUE NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (wishlist_id) REFERENCES wishlists(wishlist_id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(user_id) ON DELETE CASCADE,

    INDEX idx_share_token (share_token),
    INDEX idx_shared_by (shared_by_user_id),
    INDEX idx_shared_with (shared_with_user_id)
);

-- Add wishlist tracking to audit_logs (for activity history)
-- This will be handled by the application code when wishlist items are added/removed

-- Sample data insertion (optional - for testing)
-- INSERT INTO wishlists (user_id, product_id) VALUES
-- ('user123', 1),
-- ('user123', 5),
-- ('user456', 2);