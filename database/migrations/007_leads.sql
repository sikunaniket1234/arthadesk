-- Migration 007: Marketing leads captured from the public landing page
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    shop_name VARCHAR(150) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    city VARCHAR(100) NULL,
    message TEXT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'landing',
    status ENUM('new', 'contacted', 'onboarded', 'closed') NOT NULL DEFAULT 'new',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_leads_status (status),
    INDEX idx_leads_created (created_at),
    INDEX idx_leads_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
