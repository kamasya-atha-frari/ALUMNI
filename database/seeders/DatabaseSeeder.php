<?php
declare(strict_types=1);

namespace AlumniNexus\Database\Seeders;

use AlumniNexus\Core\Database;
use AlumniNexus\Database\Factories\AlumniFactory;
use PDO;

class DatabaseSeeder {
    public static function run(int $generatedCount = 15): void {
        $pdo = Database::getConnection();
        if (!$pdo) {
            echo "\033[31m[ERROR] Cannot seed: No database connection.\033[0m\n";
            return;
        }

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

        echo "\033[1;34m[Seeder]\033[0m Seeding baseline alumni fixtures...\n";
        $alumniStmt = $pdo->prepare("
            INSERT INTO alumni (id, student_id, full_name, email, cohort, department_id, company, role, industry, location, skills, is_mentor, mentor_capacity) VALUES
            (:id, :student_id, :full_name, :email, :cohort, :department_id, :company, :role, :industry, :location, :skills, :is_mentor, :mentor_capacity)
            ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), role=VALUES(role), company=VALUES(company), skills=VALUES(skills);
        ");

        $baseline = [
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

        foreach ($baseline as $item) {
            $alumniStmt->execute($item);
        }
        echo "  \033[32m✔ Baseline fixtures seeded.\033[0m\n";

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
                    // Ignore rare duplicate student_id / email collisions in randomized generation
                }
            }
            echo "  \033[32m✔ Generated and seeded {$createdCount} dynamic alumni profiles.\033[0m\n";
        }
    }
}
