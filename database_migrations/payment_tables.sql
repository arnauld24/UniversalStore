-- Payment-related database tables for Mesomb payment gateway integration

-- Table for storing payment transactions
CREATE TABLE IF NOT EXISTS payments (
    payment_id VARCHAR(255) PRIMARY KEY,
    order_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    payment_method ENUM('ORANGE', 'MTN', 'CAMTEL', 'YOOMEE') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'XAF',
    payer VARCHAR(20) NOT NULL, -- Phone number
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    transaction_id VARCHAR(255) NULL, -- Mesomb transaction ID
    external_reference VARCHAR(255) NULL, -- Mesomb external reference
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    failure_reason TEXT NULL,
    encrypted_payment_data TEXT NULL, -- Encrypted sensitive payment data
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    INDEX idx_order_id (order_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id)
);

-- Table for storing payment logs/audit trail
CREATE TABLE IF NOT EXISTS payment_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id VARCHAR(255) NOT NULL,
    order_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    action VARCHAR(100) NOT NULL, -- 'initiated', 'processing', 'completed', 'failed', etc.
    message TEXT NOT NULL,
    response_data JSON NULL, -- Store API responses
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE,
    INDEX idx_payment_id (payment_id),
    INDEX idx_order_id (order_id),
    INDEX idx_action (action)
);

-- Table for storing payment method configurations
CREATE TABLE IF NOT EXISTS payment_methods (
    method_id INT AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    max_amount DECIMAL(10,2) NULL,
    min_amount DECIMAL(10,2) DEFAULT 100,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default payment methods
INSERT INTO payment_methods (method_name, display_name, description) VALUES
('ORANGE', 'Orange Money', 'Pay with Orange Money mobile wallet'),
('MTN', 'MTN Mobile Money', 'Pay with MTN Mobile Money'),
('CAMTEL', 'Camtel Mobile', 'Pay with Camtel mobile services'),
('YOOMEE', 'YooMee', 'Pay with YooMee mobile services')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), description = VALUES(description);

-- Add payment_status column to orders table if it doesn't exist
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending' AFTER order_status;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_id VARCHAR(255) NULL AFTER payment_status;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_date TIMESTAMP NULL AFTER payment_id;

-- Add indexes for better performance
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_payment_status (payment_status);
ALTER TABLE orders ADD INDEX IF NOT EXISTS idx_payment_id (payment_id);