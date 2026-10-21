<?php
declare(strict_types=1);

/**
 * AlumniNexus Job Postings & Applications Seeder
 * Usage:
 *   php database/seed_jobs.php [--count=10]
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniNexus\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) === 0) {
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    $dbPrefix = 'AlumniNexus\\Database\\';
    $dbLen = strlen($dbPrefix);
    if (strncmp($dbPrefix, $class, $dbLen) === 0) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, $dbLen)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/factories/JobPostingFactory.php';
require_once __DIR__ . '/factories/ApplicationFactory.php';

use AlumniNexus\Core\Database;
use AlumniNexus\Core\Env;
use AlumniNexus\Database\Factories\JobPostingFactory;
use AlumniNexus\Database\Factories\ApplicationFactory;

Env::load();

echo "\033[1;36m==================================================\033[0m\n";
echo "\033[1;36m   AlumniNexus Job Postings & Seeder Engine       \033[0m\n";
echo "\033[1;36m==================================================\033[0m\n\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "\033[31m[ERROR] Cannot connect to database.\033[0m\n";
    exit(1);
}

$count = 10;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--count=')) {
        $count = (int)substr($arg, 8);
    }
}

echo "Seeding {$count} realistic job postings and applications...\n";

// 1. Ensure baseline companies
$pdo->exec("
    INSERT INTO companies (id, name, industry, location, website, description) VALUES
    (1, 'Trendyol Group', 'E-Commerce & Logistics', 'Istanbul, Turkey', 'https://trendyol.com', 'Leading e-commerce platform.'),
    (2, 'Turkcell Tech', 'Telecommunications & Cloud', 'Istanbul, Turkey', 'https://turkcell.com.tr', 'Cloud technology developer.'),
    (3, 'Getir Global', 'FinTech & Rapid Delivery', 'Amsterdam, Netherlands', 'https://getir.com', 'Grocery delivery pioneer.')
    ON DUPLICATE KEY UPDATE name=VALUES(name);
");

// 2. Generate and seed jobs
$insertJob = $pdo->prepare("
    INSERT INTO job_postings (title, company_id, company_name, user_id, description, requirements, location, job_type, salary_range, experience_level, status)
    VALUES (:title, :company_id, :company_name, :user_id, :description, :requirements, :location, :job_type, :salary_range, :experience_level, :status)
");

$insertApp = $pdo->prepare("
    INSERT INTO applications (job_posting_id, user_id, applicant_name, applicant_email, resume_url, cover_letter, status)
    VALUES (:job_posting_id, :user_id, :applicant_name, :applicant_email, :resume_url, :cover_letter, :status)
");

$jobs = JobPostingFactory::count($count);
$createdJobs = 0;
$createdApps = 0;

foreach ($jobs as $job) {
    try {
        $insertJob->execute($job);
        $newJobId = (int)$pdo->lastInsertId();
        $createdJobs++;

        // Add 1-3 applications per created job
        $numApps = rand(1, 3);
        for ($i = 0; $i < $numApps; $i++) {
            $app = ApplicationFactory::definition($newJobId);
            $insertApp->execute($app);
            $createdApps++;
        }
    } catch (\Throwable $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\033[32m✔ Successfully seeded {$createdJobs} job postings and {$createdApps} applications.\033[0m\n\n";
