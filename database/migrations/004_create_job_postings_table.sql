-- Migration 004: Create companies and job_postings tables

CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    industry VARCHAR(150) NULL,
    location VARCHAR(100) NULL,
    website VARCHAR(255) NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company_industry (industry)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS job_postings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    company_id INT NULL,
    company_name VARCHAR(150) NOT NULL,
    user_id INT NULL,
    description TEXT NOT NULL,
    requirements TEXT NULL,
    location VARCHAR(150) NULL,
    job_type ENUM('full-time', 'part-time', 'remote', 'internship', 'contract') DEFAULT 'full-time',
    salary_range VARCHAR(100) NULL,
    experience_level VARCHAR(50) DEFAULT 'Mid-Level',
    status ENUM('active', 'closed', 'draft') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_job_status (status),
    INDEX idx_job_type (job_type),
    INDEX idx_job_company (company_id),
    INDEX idx_job_user (user_id),
    INDEX idx_job_created (created_at),
    CONSTRAINT fk_job_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    CONSTRAINT fk_job_user FOREIGN KEY (user_id) REFERENCES alumni(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
