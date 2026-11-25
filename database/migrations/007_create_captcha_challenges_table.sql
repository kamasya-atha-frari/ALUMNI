-- Migration 007: Create captcha_challenges table for anti-bot registration verification
CREATE TABLE IF NOT EXISTS captcha_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    captcha_id VARCHAR(64) NOT NULL UNIQUE,
    answer_hash VARCHAR(64) NOT NULL,
    challenge_type VARCHAR(30) NOT NULL DEFAULT 'alphanumeric',
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    ip_address VARCHAR(45) NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_captcha_expires (expires_at),
    INDEX idx_captcha_used (used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
