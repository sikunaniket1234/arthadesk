-- Migration 005: Authentication security, audit hardening, idempotent API writes
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    email VARCHAR(150) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    reason VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_email (email, created_at),
    INDEX idx_login_ip (ip, created_at),
    INDEX idx_login_tenant (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS idempotency_keys (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NULL,
    idem_key VARCHAR(100) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    status ENUM('processing', 'completed', 'failed') NOT NULL DEFAULT 'processing',
    response_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_idem_tenant_key (tenant_id, idem_key),
    INDEX idx_idem_updated (updated_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pwreset_token (token_hash),
    INDEX idx_pwreset_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD COLUMN last_login_at DATETIME NULL AFTER is_active,
    ADD COLUMN login_count INT NOT NULL DEFAULT 0 AFTER last_login_at;

SET FOREIGN_KEY_CHECKS = 1;
