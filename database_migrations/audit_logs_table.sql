-- Create audit_logs table for comprehensive audit trail
CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    action_type VARCHAR(50) NOT NULL, -- 'ORDER_PLACED', 'PAYMENT_INITIATED', 'PAYMENT_COMPLETED', 'PAYMENT_FAILED', etc.
    order_id VARCHAR(255),
    payment_id VARCHAR(255),
    description TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    status VARCHAR(50), -- 'SUCCESS', 'FAILED', 'PENDING'
    error_details TEXT, -- Store any error messages for failed actions
    metadata JSON, -- Store additional data as JSON (amounts, payment methods, etc.)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    INDEX idx_order_id (order_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Create payment_audit_logs table for detailed payment tracking
CREATE TABLE IF NOT EXISTS payment_audit_logs (
    payment_audit_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    payment_id VARCHAR(255),
    payment_method VARCHAR(50),
    amount DECIMAL(15, 2),
    currency VARCHAR(3),
    status VARCHAR(50), -- 'PENDING', 'COMPLETED', 'FAILED', 'CANCELLED'
    payment_gateway VARCHAR(100), -- 'MESOMB', 'STRIPE', etc.
    gateway_response TEXT, -- Store full gateway response
    attempt_number INT DEFAULT 1,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_user_id (user_id),
    INDEX idx_payment_id (payment_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
