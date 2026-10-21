<?php
declare(strict_types=1);

namespace AlumniNexus\Database\Seeders;

use AlumniNexus\Core\Database;
use AlumniNexus\Database\Factories\AlumniFactory;
use AlumniNexus\Database\Factories\JobPostingFactory;
use AlumniNexus\Database\Factories\ApplicationFactory;
use PDO;

class DatabaseSeeder {
    public static function run(int $generatedCount = 15): void {
        $pdo = Database::getConnection();
        if (!$pdo) {
            echo "\033[31m[ERROR] Cannot seed: No database connection.\033[0m\n";
            return;
        }

        // 1. Seed departments
        echo "\033[1;34m[Seeder]\033[0m Seeding departments...\n";
        $deptStmt = $pdo->prepare("
            INSERT INTO departments (id, name, faculty, code) VALUES
            (1, 'Management Information Systems (YBS)', 'Faculty of Economics', 'MIS-YBS'),
            (2, 'Computer Engineering', 'Faculty of Engineering', 'CENG'),
            (3, 'Industrial Engineering', 'Faculty of Engineering', 'IE'),
            (4, 'Software Engineering', 'Faculty of Engineering', 'SE')
            ON DUPLICATE KEY UPDATE name=VALUES(name), faculty=VALUES(faculty);
        ");
        $deptStmt->execute();
        echo "  \033[32m✔ Departments seeded successfully.\033[0m\n";

        // 2. Seed baseline alumni fixtures
        echo "\033[1;34m[Seeder]\033[0m Seeding baseline alumni fixtures...\n";
        $alumniStmt = $pdo->prepare("
            INSERT INTO alumni (id, student_id, full_name, email, cohort, department_id, company, role, industry, location, skills, is_mentor, mentor_capacity) VALUES
            (:id, :student_id, :full_name, :email, :cohort, :department_id, :company, :role, :industry, :location, :skills, :is_mentor, :mentor_capacity)
            ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), role=VALUES(role), company=VALUES(company), skills=VALUES(skills);
        ");

        $baselineAlumni = [
            [
                'id' => 1,
                'student_id' => '130120001',
                'full_name' => 'Emre Arslan',
                'email' => 'emre.arslan@ogr.iu.edu.tr',
                'cohort' => 2023,
                'department_id' => 1,
                'company' => 'Turkcell Tech',
                'role' => 'Lead Data Engineer',
                'industry' => 'Telecommunications & Cloud',
                'location' => 'Istanbul, Turkey',
                'skills' => json_encode(['Python', 'SQL', 'Apache Spark', 'Databricks']),
                'is_mentor' => 1,
                'mentor_capacity' => 3
            ],
            [
                'id' => 2,
                'student_id' => '130120002',
                'full_name' => 'Selin Demir',
                'email' => 'selin.demir@ogr.iu.edu.tr',
                'cohort' => 2022,
                'department_id' => 1,
                'company' => 'Trendyol Group',
                'role' => 'Senior Product Manager',
                'industry' => 'E-Commerce & Logistics',
                'location' => 'Istanbul, Turkey',
                'skills' => json_encode(['Product Strategy', 'Agile/Scrum', 'Data Analytics', 'A/B Testing']),
                'is_mentor' => 1,
                'mentor_capacity' => 2
            ],
            [
                'id' => 3,
                'student_id' => '130120003',
                'full_name' => 'Burak Celik',
                'email' => 'burak.celik@ogr.iu.edu.tr',
                'cohort' => 2021,
                'department_id' => 1,
                'company' => 'Getir Global',
                'role' => 'DevOps & Site Reliability Engineer',
                'industry' => 'FinTech & Rapid Delivery',
                'location' => 'Amsterdam, Netherlands',
                'skills' => json_encode(['Docker', 'Kubernetes', 'AWS', 'Terraform', 'CI/CD']),
                'is_mentor' => 0,
                'mentor_capacity' => 0
            ],
            [
                'id' => 4,
                'student_id' => '130120004',
                'full_name' => 'Aylin Yildiz',
                'email' => 'aylin.yildiz@ogr.iu.edu.tr',
                'cohort' => 2024,
                'department_id' => 1,
                'company' => 'Garanti BBVA Technology',
                'role' => 'Cybersecurity Analyst',
                'industry' => 'Banking & Digital Security',
                'location' => 'Istanbul, Turkey',
                'skills' => json_encode(['SIEM', 'Network Security', 'ISO 27001', 'Penetration Testing']),
                'is_mentor' => 1,
                'mentor_capacity' => 2
            ]
        ];

        foreach ($baselineAlumni as $item) {
            $alumniStmt->execute($item);
        }
        echo "  \033[32m✔ Baseline alumni fixtures seeded.\033[0m\n";

        // Dynamic alumni records
        if ($generatedCount > 0) {
            echo "\033[1;34m[Seeder]\033[0m Generating {$generatedCount} dynamic alumni records with AlumniFactory...\n";
            $factoryRecords = AlumniFactory::count($generatedCount);
            $insertDynamicStmt = $pdo->prepare("
                INSERT INTO alumni (student_id, full_name, email, cohort, department_id, company, role, industry, location, skills, is_mentor, mentor_capacity)
                VALUES (:student_id, :full_name, :email, :cohort, :department_id, :company, :role, :industry, :location, :skills, :is_mentor, :mentor_capacity)
                ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);
            ");

            $createdCount = 0;
            foreach ($factoryRecords as $rec) {
                try {
                    $insertDynamicStmt->execute([
                        'student_id' => $rec['student_id'],
                        'full_name' => $rec['full_name'],
                        'email' => $rec['email'],
                        'cohort' => $rec['cohort'],
                        'department_id' => $rec['department_id'],
                        'company' => $rec['company'],
                        'role' => $rec['role'],
                        'industry' => $rec['industry'],
                        'location' => $rec['location'],
                        'skills' => json_encode($rec['skills']),
                        'is_mentor' => $rec['is_mentor'] ? 1 : 0,
                        'mentor_capacity' => $rec['mentor_capacity']
                    ]);
                    $createdCount++;
                } catch (\Throwable $e) {
                    // Ignore rare duplicate student_id / email collisions
                }
            }
            echo "  \033[32m✔ Generated and seeded {$createdCount} dynamic alumni profiles.\033[0m\n";
        }

        // 3. Seed Companies
        echo "\033[1;34m[Seeder]\033[0m Seeding hiring companies...\n";
        $companyStmt = $pdo->prepare("
            INSERT INTO companies (id, name, industry, location, website, description) VALUES
            (1, 'Trendyol Group', 'E-Commerce & Logistics', 'Istanbul, Turkey', 'https://trendyol.com', 'Leading e-commerce and logistics platform in Turkey and Europe.'),
            (2, 'Turkcell Tech', 'Telecommunications & Cloud', 'Istanbul, Turkey', 'https://turkcell.com.tr', 'Pioneering telecommunications provider and cloud technology developer.'),
            (3, 'Getir Global', 'FinTech & Rapid Delivery', 'Amsterdam, Netherlands', 'https://getir.com', 'Ultra-fast grocery delivery pioneer expanding fintech solutions.'),
            (4, 'Garanti BBVA Technology', 'Banking & Digital Security', 'Istanbul, Turkey', 'https://garantibbvateknoloji.com.tr', 'Next-generation digital banking and fintech engineering center.')
            ON DUPLICATE KEY UPDATE name=VALUES(name), industry=VALUES(industry);
        ");
        $companyStmt->execute();
        echo "  \033[32m✔ Companies seeded successfully.\033[0m\n";

        // 4. Seed Job Postings (Baseline + Dynamic)
        echo "\033[1;34m[Seeder]\033[0m Seeding baseline job postings...\n";
        $jobStmt = $pdo->prepare("
            INSERT INTO job_postings (id, title, company_id, company_name, user_id, description, requirements, location, job_type, salary_range, experience_level, status) VALUES
            (1, 'Senior Cloud & Data Engineer', 2, 'Turkcell Tech', 1, 'Design scalable real-time streaming architectures using Spark and Kafka.', 'Strong proficiency in Python, SQL, Docker, Kafka, and Databricks.', 'Istanbul, Turkey (Hybrid)', 'full-time', '₺95,000 - ₺130,000 / month', 'Senior', 'active'),
            (2, 'Associate Product Manager', 1, 'Trendyol Group', 2, 'Drive next-generation e-commerce search, recommendation, and checkout experiences.', 'Bachelor in MIS or Computer Engineering. Strong analytical and SQL skills.', 'Istanbul, Turkey (On-site)', 'full-time', '₺60,000 - ₺85,000 / month', 'Entry', 'active'),
            (3, 'Graduate DevOps Intern', 3, 'Getir Global', 3, 'Automate multi-region AWS infrastructure and container pipelines.', 'Basic knowledge of Docker, Linux, CI/CD, and Git.', 'Remote', 'internship', '₺25,000 - ₺35,000 / month', 'Entry', 'active')
            ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description);
        ");
        $jobStmt->execute();
        echo "  \033[32m✔ Baseline job postings seeded.\033[0m\n";

        $jobCount = max(5, intval($generatedCount / 2));
        echo "\033[1;34m[Seeder]\033[0m Generating {$jobCount} dynamic job postings with JobPostingFactory...\n";
        $dynJobs = JobPostingFactory::count($jobCount);
        $insertJobStmt = $pdo->prepare("
            INSERT INTO job_postings (title, company_id, company_name, user_id, description, requirements, location, job_type, salary_range, experience_level, status)
            VALUES (:title, :company_id, :company_name, :user_id, :description, :requirements, :location, :job_type, :salary_range, :experience_level, :status)
        ");

        $seededJobs = 0;
        foreach ($dynJobs as $job) {
            try {
                $insertJobStmt->execute($job);
                $seededJobs++;
            } catch (\Throwable $e) {
                // Ignore errors
            }
        }
        echo "  \033[32m✔ Generated and seeded {$seededJobs} dynamic job postings.\033[0m\n";

        // 5. Seed Applications (Baseline + Dynamic)
        echo "\033[1;34m[Seeder]\033[0m Seeding baseline applications...\n";
        $appStmt = $pdo->prepare("
            INSERT INTO applications (id, job_posting_id, user_id, applicant_name, applicant_email, resume_url, cover_letter, status) VALUES
            (1, 1, 4, 'Aylin Yildiz', 'aylin.yildiz@ogr.iu.edu.tr', 'https://drive.google.com/file/d/aylin-cv', 'Passionate about secure cloud and big data engineering pipelines.', 'under_review'),
            (2, 2, NULL, 'Caner Erkin', 'caner.erkin@ogr.iu.edu.tr', 'https://linkedin.com/in/caner-erkin', 'Recent MIS graduate with proven product analytics and SQL experience.', 'submitted')
            ON DUPLICATE KEY UPDATE applicant_name=VALUES(applicant_name);
        ");
        $appStmt->execute();
        echo "  \033[32m✔ Baseline applications seeded.\033[0m\n";

        $appCount = max(6, $jobCount);
        echo "\033[1;34m[Seeder]\033[0m Generating {$appCount} dynamic applications with ApplicationFactory...\n";
        $insertAppStmt = $pdo->prepare("
            INSERT INTO applications (job_posting_id, user_id, applicant_name, applicant_email, resume_url, cover_letter, status)
            VALUES (:job_posting_id, :user_id, :applicant_name, :applicant_email, :resume_url, :cover_letter, :status)
        ");

        $seededApps = 0;
        for ($i = 0; $i < $appCount; $i++) {
            $targetJobId = rand(1, 3);
            $appData = ApplicationFactory::definition($targetJobId);
            try {
                $insertAppStmt->execute($appData);
                $seededApps++;
            } catch (\Throwable $e) {
                // Ignore errors
            }
        }
        echo "  \033[32m✔ Generated and seeded {$seededApps} dynamic candidate applications.\033[0m\n";
    }
}
