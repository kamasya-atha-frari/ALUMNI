-- =====================================================================
-- Istanbul University MIS Alumni Portal
-- Relational Database Schema & Data Fixtures
-- Target Database: iu_mis_alumni
-- =====================================================================

CREATE DATABASE IF NOT EXISTS iu_mis_alumni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iu_mis_alumni;

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    faculty VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role ENUM('alumni', 'student', 'faculty_admin', 'super_admin') DEFAULT 'alumni',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Alumni Profiles Table
CREATE TABLE IF NOT EXISTS alumni_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    graduation_year INT NOT NULL,
    department VARCHAR(150) DEFAULT 'Management Information Systems (YBS)',
    company VARCHAR(150) NULL,
    job_title VARCHAR(150) NULL,
    is_mentor BOOLEAN DEFAULT FALSE,
    bio TEXT NULL,
    linkedin_url VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Seed Data
-- =====================================================================

INSERT INTO departments (id, name, faculty) VALUES
(1, 'Management Information Systems (YBS)', 'Faculty of Economics'),
(2, 'Computer Engineering', 'Faculty of Engineering')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO users (id, student_id, first_name, last_name, email, role) VALUES
(1, '130120001', 'Ahmet', 'Yilmaz', 'ahmet.yilmaz@ogr.iu.edu.tr', 'alumni'),
(2, '130120002', 'Zeynep', 'Kaya', 'zeynep.kaya@ogr.iu.edu.tr', 'alumni'),
(3, '130120003', 'Can', 'Demir', 'can.demir@ogr.iu.edu.tr', 'student')
ON DUPLICATE KEY UPDATE first_name=VALUES(first_name);

INSERT INTO alumni_profiles (user_id, graduation_year, department, company, job_title, is_mentor) VALUES
(1, 2023, 'Management Information Systems (YBS)', 'Turkcell', 'Business Intelligence Specialist', TRUE),
(2, 2022, 'Management Information Systems (YBS)', 'Trendyol', 'Software Engineer', TRUE),
(3, 2025, 'Management Information Systems (YBS)', 'Getir', 'Cloud Infrastructure Intern', FALSE)
ON DUPLICATE KEY UPDATE graduation_year=VALUES(graduation_year);
