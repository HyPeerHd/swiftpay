-- database.sql - Estructura de la base de datos
CREATE DATABASE IF NOT EXISTS swiftpay 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE swiftpay;

-- Tabla principal de órdenes
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(20) UNIQUE NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    delivery_details TEXT NOT NULL,
    payment_method ENUM('bitcoin', 'monero') NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    crypto_amount DECIMAL(20, 8) NOT NULL,
    items TEXT NOT NULL,
    status ENUM('pending', 'paid', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
    txid VARCHAR(100) NULL,
    paid_amount DECIMAL(20, 8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    confirmed_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para optimización
    INDEX idx_order_id (order_id),
    INDEX idx_status (status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_created_at (created_at),
    INDEX idx_email (customer_email),
    
    -- Índice compuesto para consultas frecuentes
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para logs de transacciones (opcional, para debugging)
CREATE TABLE IF NOT EXISTS transaction_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(20),
    message TEXT,
    level ENUM('INFO', 'WARNING', 'ERROR') DEFAULT 'INFO',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_order_id (order_id),
    INDEX idx_level (level),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar datos de ejemplo (opcional)
-- INSERT INTO orders (order_id, customer_email, delivery_details, payment_method, total_amount, crypto_amount, items, status) 
-- VALUES ('SP1234567890', 'test@example.com', 'Test delivery', 'bitcoin', 55.00, 0.00042600, '[]', 'pending');