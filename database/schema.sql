-- =====================================================================
-- Istanbul University MIS Alumni Management System
-- Relational Database Schema & Initial Data Fixtures
-- =====================================================================

CREATE DATABASE IF NOT EXISTS alumni_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE alumni_db;

-- 1. Departments / Faculties Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    faculty VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users / Alumni Profiles Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    graduation_year INT NOT NULL,
    department_id INT,
    company VARCHAR(150) NULL,
    job_title VARCHAR(150) NULL,
    is_mentor BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Seed Data Fixtures
-- =====================================================================

INSERT INTO departments (id, name, faculty) VALUES
(1, 'Management Information Systems (YBS)', 'Faculty of Economics'),
(2, 'Computer Engineering', 'Faculty of Engineering')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO users (id, student_id, full_name, email, graduation_year, department_id, company, job_title, is_mentor) VALUES
(1, '130120001', 'Ahmet Yilmaz', 'ahmet.yilmaz@ogr.iu.edu.tr', 2023, 1, 'Turkcell', 'Business Intelligence Specialist', TRUE),
(2, '130120002', 'Zeynep Kaya', 'zeynep.kaya@ogr.iu.edu.tr', 2022, 1, 'Trendyol', 'Software Engineer', TRUE),
(3, '130120003', 'Can Demir', 'can.demir@ogr.iu.edu.tr', 2024, 1, 'Getir', 'Cloud Infrastructure Analyst', FALSE),
(4, '130120004', 'Elif Ozturk', 'elif.ozturk@ogr.iu.edu.tr', 2021, 1, 'Vodafone', 'Product Manager', TRUE)
ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);
