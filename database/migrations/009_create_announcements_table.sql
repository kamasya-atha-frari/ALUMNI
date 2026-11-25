-- Migration 009: Create announcements table for Admin Panel department notices & posts
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    category ENUM('general', 'career', 'event', 'accreditation') NOT NULL DEFAULT 'general',
    status ENUM('published', 'draft') NOT NULL DEFAULT 'published',
    is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
    author_user_id INT NULL,
    author_name VARCHAR(150) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_announcements_status (status),
    INDEX idx_announcements_category (category),
    INDEX idx_announcements_pinned (is_pinned),
    CONSTRAINT fk_announcements_author FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
