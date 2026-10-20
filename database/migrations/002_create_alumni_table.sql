-- Migration 002: Create alumni table
CREATE TABLE IF NOT EXISTS alumni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    cohort INT NOT NULL,
    department_id INT NULL,
    company VARCHAR(150) NULL,
    role VARCHAR(150) NULL,
    industry VARCHAR(150) NULL,
    location VARCHAR(100) NULL,
    skills JSON NULL,
    is_mentor BOOLEAN DEFAULT FALSE,
    mentor_capacity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cohort (cohort),
    INDEX idx_industry (industry),
    INDEX idx_mentor (is_mentor),
    CONSTRAINT fk_alumni_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
