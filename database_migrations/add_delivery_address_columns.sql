-- Migration: Add delivery address columns to orders table
-- Description: Stores delivery address information separately from billing address
-- Run this migration in phpMyAdmin or via MySQL command line

ALTER TABLE orders ADD COLUMN (
    delivery_fname VARCHAR(255) DEFAULT NULL COMMENT 'Delivery first name',
    delivery_lname VARCHAR(255) DEFAULT NULL COMMENT 'Delivery last name',
    delivery_country VARCHAR(255) DEFAULT NULL COMMENT 'Delivery country',
    delivery_address VARCHAR(500) DEFAULT NULL COMMENT 'Delivery street address',
    delivery_city VARCHAR(255) DEFAULT NULL COMMENT 'Delivery city',
    delivery_postcode VARCHAR(50) DEFAULT NULL COMMENT 'Delivery postal code',
    delivery_phone VARCHAR(20) DEFAULT NULL COMMENT 'Delivery phone number',
    delivery_email VARCHAR(255) DEFAULT NULL COMMENT 'Delivery email address'
);

-- Create indexes on new columns for faster queries
CREATE INDEX idx_delivery_email ON orders(delivery_email);
CREATE INDEX idx_delivery_phone ON orders(delivery_phone);
