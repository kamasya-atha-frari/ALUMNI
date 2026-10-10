-- =====================================================================
-- AlumniNexus - Next-Gen Alumni Intelligence Engine
-- Database Schema (MySQL 8.0 / InnoDB / utf8mb4)
-- Target: alumni_nexus
-- =====================================================================

CREATE DATABASE IF NOT EXISTS alumni_nexus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE alumni_nexus;

-- 1. Departments & Faculties Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    faculty VARCHAR(150) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Core Alumni Directory Table
CREATE TABLE IF NOT EXISTS alumni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    cohort INT NOT NULL,
    department_id INT,
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

-- =====================================================================
-- Seed Fixtures
-- =====================================================================

INSERT INTO departments (id, name, faculty, code) VALUES
(1, 'Management Information Systems (YBS)', 'Faculty of Economics', 'MIS-YBS'),
(2, 'Computer Engineering', 'Faculty of Engineering', 'CENG')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO alumni (id, student_id, full_name, email, cohort, department_id, company, role, industry, location, skills, is_mentor, mentor_capacity) VALUES
(1, '130120001', 'Emre Arslan', 'emre.arslan@ogr.iu.edu.tr', 2023, 1, 'Turkcell Tech', 'Lead Data Engineer', 'Telecommunications & Cloud', 'Istanbul, Turkey', '["Python", "SQL", "Apache Spark", "Databricks"]', TRUE, 3),
(2, '130120002', 'Selin Demir', 'selin.demir@ogr.iu.edu.tr', 2022, 1, 'Trendyol Group', 'Senior Product Manager', 'E-Commerce & Logistics', 'Istanbul, Turkey', '["Product Strategy", "Agile/Scrum", "Data Analytics", "A/B Testing"]', TRUE, 2),
(3, '130120003', 'Burak Celik', 'burak.celik@ogr.iu.edu.tr', 2021, 1, 'Getir Global', 'DevOps & Site Reliability Engineer', 'FinTech & Rapid Delivery', 'Amsterdam, Netherlands', '["Docker", "Kubernetes", "AWS", "Terraform", "CI/CD"]', FALSE, 0),
(4, '130120004', 'Aylin Yildiz', 'aylin.yildiz@ogr.iu.edu.tr', 2024, 1, 'Garanti BBVA Technology', 'Cybersecurity Analyst', 'Banking & Digital Security', 'Istanbul, Turkey', '["SIEM", "Network Security", "ISO 27001", "Penetration Testing"]', TRUE, 2)
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);
