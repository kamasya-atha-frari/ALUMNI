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

-- 3. Corporate Companies Table
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

-- 4. Job Postings Table
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

-- 5. Applications Table
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_posting_id INT NOT NULL,
    user_id INT NULL,
    applicant_name VARCHAR(150) NOT NULL,
    applicant_email VARCHAR(150) NOT NULL,
    resume_url VARCHAR(255) NULL,
    cover_letter TEXT NULL,
    status ENUM('submitted', 'under_review', 'interview', 'accepted', 'rejected') DEFAULT 'submitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_application_status (status),
    INDEX idx_app_job_id (job_posting_id),
    INDEX idx_app_user_id (user_id),
    INDEX idx_app_created (created_at),
    CONSTRAINT fk_application_job FOREIGN KEY (job_posting_id) REFERENCES job_postings(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_user FOREIGN KEY (user_id) REFERENCES alumni(id) ON DELETE SET NULL
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

INSERT INTO companies (id, name, industry, location, website, description) VALUES
(1, 'Trendyol Group', 'E-Commerce & Logistics', 'Istanbul, Turkey', 'https://trendyol.com', 'Leading e-commerce and logistics platform in Turkey and Europe.'),
(2, 'Turkcell Tech', 'Telecommunications & Cloud', 'Istanbul, Turkey', 'https://turkcell.com.tr', 'Pioneering telecommunications provider and cloud technology developer.'),
(3, 'Getir Global', 'FinTech & Rapid Delivery', 'Amsterdam, Netherlands', 'https://getir.com', 'Ultra-fast grocery delivery pioneer expanding fintech solutions.')
ON DUPLICATE KEY UPDATE name=VALUES(name);

INSERT INTO job_postings (id, title, company_id, company_name, user_id, description, requirements, location, job_type, salary_range, experience_level, status) VALUES
(1, 'Senior Cloud & Data Engineer', 2, 'Turkcell Tech', 1, 'Design scalable real-time streaming architectures using Spark and Kafka.', 'Strong proficiency in Python, SQL, Docker, Kafka, and Databricks.', 'Istanbul, Turkey (Hybrid)', 'full-time', '₺95,000 - ₺130,000 / month', 'Senior', 'active'),
(2, 'Associate Product Manager', 1, 'Trendyol Group', 2, 'Drive next-generation e-commerce search, recommendation, and checkout experiences.', 'Bachelor in MIS or Computer Engineering. Strong analytical and SQL skills.', 'Istanbul, Turkey (On-site)', 'full-time', '₺60,000 - ₺85,000 / month', 'Entry', 'active'),
(3, 'Graduate DevOps Intern', 3, 'Getir Global', 3, 'Automate multi-region AWS infrastructure and container pipelines.', 'Basic knowledge of Docker, Linux, CI/CD, and Git.', 'Remote', 'internship', '₺25,000 - ₺35,000 / month', 'Entry', 'active')
ON DUPLICATE KEY UPDATE title=VALUES(title);

INSERT INTO applications (id, job_posting_id, user_id, applicant_name, applicant_email, resume_url, cover_letter, status) VALUES
(1, 1, 4, 'Aylin Yildiz', 'aylin.yildiz@ogr.iu.edu.tr', 'https://drive.google.com/file/d/aylin-cv', 'Passionate about secure cloud and big data engineering pipelines.', 'under_review'),
(2, 2, NULL, 'Caner Erkin', 'caner.erkin@ogr.iu.edu.tr', 'https://linkedin.com/in/caner-erkin', 'Recent MIS graduate with proven product analytics and SQL experience.', 'submitted')
ON DUPLICATE KEY UPDATE applicant_name=VALUES(applicant_name);
