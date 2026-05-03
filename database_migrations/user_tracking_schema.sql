-- User Tracking System Database Schema for E-Commerce Analytics

USE universalstore_managementsystem;

-- Existing tables (assumed):
-- users: user_id, email, name, etc.
-- visitors: visitor_id, ip_address, user_agent, first_visit, etc.

-- New tables for tracking system:

-- 1. Sessions table (to group events by user/visitor sessions)
CREATE TABLE user_sessions (
    session_id VARCHAR(255) PRIMARY KEY,
    user_id INT NULL,  -- NULL for anonymous visitors
    visitor_id INT NULL,  -- Links to visitors table
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    device_type ENUM('desktop', 'mobile', 'tablet') DEFAULT 'desktop',
    browser VARCHAR(100),
    os VARCHAR(100),
    screen_resolution VARCHAR(20),
    referrer_url TEXT,
    landing_page TEXT,
    geolocation_country VARCHAR(50),  -- Anonymized
    geolocation_city VARCHAR(50),     -- Anonymized
    is_logged_in BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id) ON DELETE SET NULL,
    INDEX idx_user_session (user_id, session_id),
    INDEX idx_visitor_session (visitor_id, session_id)
);

-- 2. Main tracking events table
CREATE TABLE tracking_events (
    event_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id INT NULL,
    visitor_id INT NULL,
    event_type VARCHAR(100) NOT NULL,  -- e.g., 'page_view', 'add_to_cart', 'login_success'
    event_category ENUM('engagement', 'conversion', 'security', 'navigation', 'ai_insight') DEFAULT 'engagement',
    event_data JSON,  -- Flexible storage for event-specific data
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    page_url TEXT,
    ip_address_hash VARCHAR(64),  -- Anonymized (SHA-256 hash)
    user_agent TEXT,
    is_ajax BOOLEAN DEFAULT FALSE,
    response_time_ms INT NULL,  -- For performance tracking
    error_message TEXT NULL,     -- For error tracking
    FOREIGN KEY (session_id) REFERENCES user_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id) ON DELETE SET NULL,
    INDEX idx_event_type (event_type, timestamp),
    INDEX idx_user_event (user_id, event_type, timestamp),
    INDEX idx_session_event (session_id, timestamp),
    INDEX idx_timestamp (timestamp)
);

-- 3. Product interaction tracking (specific for e-commerce AI)
CREATE TABLE product_interactions (
    interaction_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    product_id INT NOT NULL,
    interaction_type ENUM('view', 'click', 'add_to_cart', 'remove_from_cart', 'wishlist_add', 'wishlist_remove', 'purchase') DEFAULT 'view',
    quantity INT DEFAULT 1,
    price_at_time DECIMAL(10,2) NULL,
    variant_selected VARCHAR(100) NULL,
    time_spent_seconds INT NULL,
    scroll_depth_percent INT NULL,
    FOREIGN KEY (event_id) REFERENCES tracking_events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    INDEX idx_product_interaction (product_id, interaction_type, timestamp),
    INDEX idx_user_product (user_id, product_id, interaction_type)
);

-- 4. Search and navigation tracking
CREATE TABLE search_queries (
    query_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT NOT NULL,
    search_query TEXT NOT NULL,
    search_results_count INT DEFAULT 0,
    clicked_product_id INT NULL,
    search_category VARCHAR(100) NULL,
    search_filters JSON NULL,  -- e.g., {"price_range": "100-500", "brand": "Apple"}
    FOREIGN KEY (event_id) REFERENCES tracking_events(event_id) ON DELETE CASCADE,
    FOREIGN KEY (clicked_product_id) REFERENCES products(product_id) ON DELETE SET NULL,
    INDEX idx_search_query (search_query(50), timestamp),
    INDEX idx_search_results (search_results_count)
);

-- 5. Cart and checkout funnel tracking
CREATE TABLE cart_funnel (
    funnel_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id INT NULL,
    visitor_id INT NULL,
    step ENUM('cart_view', 'checkout_start', 'payment_info', 'shipping_info', 'order_complete', 'abandoned') DEFAULT 'cart_view',
    step_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cart_value DECIMAL(10,2) DEFAULT 0,
    items_count INT DEFAULT 0,
    abandoned_reason VARCHAR(100) NULL,
    FOREIGN KEY (session_id) REFERENCES user_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id) ON DELETE SET NULL,
    INDEX idx_funnel_step (session_id, step, step_timestamp)
);

-- 6. AI Recommendations tracking (for measuring recommendation effectiveness)
CREATE TABLE ai_recommendations (
    recommendation_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id INT NULL,
    visitor_id INT NULL,
    recommendation_type ENUM('product_suggestion', 'content_insight', 'best_seller', 'personalized_offer', 'related_product') DEFAULT 'product_suggestion',
    recommended_items JSON,  -- Array of product_ids or content_ids
    algorithm_used VARCHAR(100),  -- e.g., 'collaborative_filtering', 'content_based'
    shown_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    clicked_item_id INT NULL,
    purchased_item_id INT NULL,
    effectiveness_score DECIMAL(3,2) NULL,  -- 0.00 to 1.00
    FOREIGN KEY (session_id) REFERENCES user_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id) ON DELETE SET NULL,
    FOREIGN KEY (clicked_item_id) REFERENCES products(product_id) ON DELETE SET NULL,
    FOREIGN KEY (purchased_item_id) REFERENCES products(product_id) ON DELETE SET NULL,
    INDEX idx_recommendation_type (recommendation_type, shown_at),
    INDEX idx_recommendation_effective (effectiveness_score DESC)
);

-- 7. Performance and error tracking
CREATE TABLE performance_metrics (
    metric_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL,
    user_id INT NULL,
    visitor_id INT NULL,
    page_url TEXT NOT NULL,
    load_time_ms INT NULL,
    dom_ready_time_ms INT NULL,
    first_paint_ms INT NULL,
    largest_contentful_paint_ms INT NULL,
    cumulative_layout_shift DECIMAL(5,4) NULL,
    first_input_delay_ms INT NULL,
    error_type VARCHAR(100) NULL,
    error_message TEXT NULL,
    error_stack TEXT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES user_sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id) ON DELETE SET NULL,
    INDEX idx_performance_page (page_url(100), timestamp),
    INDEX idx_performance_load (load_time_ms)
);

-- Update existing visitors table to link to users when they login
ALTER TABLE visitors ADD COLUMN merged_to_user_id INT NULL;
ALTER TABLE visitors ADD FOREIGN KEY (merged_to_user_id) REFERENCES users(user_id) ON DELETE SET NULL;
ALTER TABLE visitors ADD INDEX idx_merged_user (merged_to_user_id);

-- Update users table to store visitor origin
ALTER TABLE users ADD COLUMN original_visitor_id INT NULL;
ALTER TABLE users ADD FOREIGN KEY (original_visitor_id) REFERENCES visitors(visitor_id) ON DELETE SET NULL;</content>
<parameter name="filePath">c:\xampp\htdocs\PHPPROJECT\database_migrations\user_tracking_schema.sql