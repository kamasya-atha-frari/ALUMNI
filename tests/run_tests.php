<?php
declare(strict_types=1);

/**
 * AlumniNexus Automated Test Suite
 * Includes Week 5/6 Database, Job Postings, and Applications integration tests.
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniNexus\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require_once $file;
});

use AlumniNexus\Services\AlumniService;
use AlumniNexus\Domain\Enums\UserRole;
use AlumniNexus\Domain\Enums\MentorshipStatus;
use AlumniNexus\Core\Router;
use AlumniNexus\Models\JobPosting;
use AlumniNexus\Models\Application;
use AlumniNexus\Models\Company;
use AlumniNexus\Models\Alumni;
use AlumniNexus\Models\User;

$passed = 0;
$total = 0;

function assertCheck(bool $condition, string $label): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m {$label}\n";
    } else {
        echo "  \033[31m✖ FAIL\033[0m {$label}\n";
    }
}

echo "\n\033[1;34m=== AlumniNexus System Test Suite ===\033[0m\n\n";

// 1. Test Domain Enums
echo "1. Domain Enums:\n";
assertCheck(UserRole::ALUMNI->value === 'alumni', "UserRole::ALUMNI equals 'alumni'");
assertCheck(MentorshipStatus::AVAILABLE->badgeColor() === 'emerald', "MentorshipStatus badge is 'emerald'");

// 2. Test Service Filtering
echo "\n2. Alumni Service Read Logic:\n";
$service = new AlumniService();
$all = $service->getAll();
assertCheck(count($all) >= 4, "Retrieved default alumni records (count: " . count($all) . ")");

$mentors = $service->getAll(['mentor' => 'true']);
assertCheck(count($mentors) >= 3, "Filtered active mentors correctly (found: " . count($mentors) . ")");

$cohort2023 = $service->getAll(['cohort' => 2023]);
assertCheck(count($cohort2023) >= 1 && $cohort2023[0]['full_name'] === 'Emre Arslan', "Filtered cohort 2023 accurately");

// 3. Test CRUD Operations
echo "\n3. Alumni CRUD Operations:\n";
$created = $service->create([
    'full_name' => 'Ada Lovelace',
    'email' => 'ada@cv.edu.tr',
    'company' => 'Analytical Engine Lab',
    'role' => 'Founding Computing Visionary',
    'industry' => 'Computer Science History',
    'cohort' => 2024,
    'is_mentor' => true
]);
assertCheck(isset($created['id']) && $created['full_name'] === 'Ada Lovelace', "CREATE: Created new record (ID: #" . ($created['id'] ?? '?') . ")");

$fetched = $service->findById((int)$created['id']);
assertCheck($fetched !== null && $fetched['email'] === 'ada@cv.edu.tr', "READ: Fetched created record by ID");

$updated = $service->update((int)$created['id'], [
    'role' => 'Chief Algorithm Architect',
    'company' => 'Babbage & Co'
]);
assertCheck($updated !== null && $updated['role'] === 'Chief Algorithm Architect', "UPDATE: Updated role attribute");

$deleted = $service->delete((int)$created['id']);
assertCheck($deleted === true, "DELETE: Successfully deleted record");

$afterDelete = $service->findById((int)$created['id']);
assertCheck($afterDelete === null, "CONFIRM: Deleted record is no longer findable");

// 4. Test Analytics
echo "\n4. Career Analytics Engine:\n";
$analytics = $service->getAnalytics();
assertCheck($analytics['employment_rate_percentage'] > 90, "Employment index exceeds 90% benchmark");
assertCheck(isset($analytics['top_industries']['Telecommunications & Cloud']), "Industry breakdown tracks Cloud & Telecom");

// 5. Test Router Engine Pattern Matching
echo "\n5. Router Engine Pattern Matching:\n";
$router = new Router();
$matchedId = null;
$router->get('/api/v1/alumni/{id}', function ($id) use (&$matchedId) {
    $matchedId = (int)$id;
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/alumni/42';
$router->resolve('GET', '/api/v1/alumni/42');
assertCheck($matchedId === 42, "Router correctly extracted dynamic path parameter {id} = 42");

// 6. Test JobPosting Model & CRUD Operations
echo "\n6. JobPosting Model & CRUD:\n";
$initialJobs = JobPosting::all();
assertCheck(count($initialJobs) >= 3, "JobPosting::all() retrieved baseline postings (found: " . count($initialJobs) . ")");

$newJob = JobPosting::create([
    'title' => 'Staff Machine Learning Engineer',
    'company_name' => 'Insider Tech',
    'company_id' => 1,
    'user_id' => 1,
    'description' => 'Build high-scale generative AI and deep retrieval recommendation models.',
    'requirements' => 'Proficiency in Python, PyTorch, Vector DBs, and Triton inference.',
    'location' => 'Istanbul, Turkey (Hybrid)',
    'job_type' => 'full-time',
    'salary_range' => '₺120,000 - ₺170,000 / month',
    'experience_level' => 'Senior',
    'status' => 'active'
]);
assertCheck($newJob->id !== null && $newJob->title === 'Staff Machine Learning Engineer', "CREATE: Created JobPosting #{$newJob->id}");

$foundJob = JobPosting::find((int)$newJob->id);
assertCheck($foundJob !== null && $foundJob->company_name === 'Insider Tech', "READ: Found created JobPosting by ID");

$updateJobResult = $foundJob->update([
    'salary_range' => '₺130,000 - ₺180,000 / month',
    'status' => 'active'
]);
assertCheck($updateJobResult === true && $foundJob->salary_range === '₺130,000 - ₺180,000 / month', "UPDATE: Updated JobPosting salary attribute");

$filterJobs = JobPosting::all(['job_type' => 'full-time']);
assertCheck(count($filterJobs) >= 2, "FILTER: Successfully filtered jobs by job_type='full-time'");

// 7. Test Application Model & CRUD Operations
echo "\n7. Application Model & CRUD:\n";
$initialApps = Application::all();
assertCheck(count($initialApps) >= 2, "Application::all() retrieved baseline applications (found: " . count($initialApps) . ")");

$newApp = Application::create([
    'job_posting_id' => (int)$newJob->id,
    'user_id' => 2,
    'applicant_name' => 'Kerem Aktürkoğlu',
    'applicant_email' => 'kerem.akturkoglu@ogr.iu.edu.tr',
    'resume_url' => 'https://drive.google.com/file/d/kerem-cv',
    'cover_letter' => 'Passionate about ML engineering and recommender systems.',
    'status' => 'submitted'
]);
assertCheck($newApp->id !== null && $newApp->applicant_name === 'Kerem Aktürkoğlu', "CREATE: Created Application #{$newApp->id}");

$foundApp = Application::find((int)$newApp->id);
assertCheck($foundApp !== null && $foundApp->job_posting_id === (int)$newJob->id, "READ: Found Application by ID with correct foreign key");

$updateAppResult = $foundApp->update(['status' => 'interview']);
assertCheck($updateAppResult === true && $foundApp->status === 'interview', "UPDATE: Updated Application status to 'interview'");

// 8. Test Relationships
echo "\n8. Model Entity Relationships:\n";
// JobPosting -> Company
$jobCompany = $newJob->company();
assertCheck($jobCompany !== null && $jobCompany->name === 'Trendyol Group', "RELATION: JobPosting::company() resolved Company entity ({$jobCompany?->name})");

// JobPosting -> User (Poster)
$poster = $newJob->postedBy();
assertCheck($poster !== null && $poster->id === 1, "RELATION: JobPosting::postedBy() resolved Alumni poster (#{$poster?->id})");

// JobPosting -> Applications
$jobApps = $newJob->applications();
assertCheck(count($jobApps) >= 1 && $jobApps[0]->applicant_name === 'Kerem Aktürkoğlu', "RELATION: JobPosting::applications() resolved candidate application");

// Application -> JobPosting
$parentJob = $newApp->jobPosting();
assertCheck($parentJob !== null && $parentJob->id === $newJob->id, "RELATION: Application::jobPosting() resolved parent JobPosting (#{$parentJob?->id})");

// Application -> Applicant User
$applicantUser = $newApp->applicant();
assertCheck($applicantUser !== null && $applicantUser->id === 2, "RELATION: Application::applicant() resolved Alumni user (#{$applicantUser?->id})");

// Company -> JobPostings
$compPostings = $jobCompany->jobPostings();
assertCheck(count($compPostings) >= 1, "RELATION: Company::jobPostings() returned associated postings (count: " . count($compPostings) . ")");

// Alumni -> JobPostings & Applications
$alumniOne = Alumni::find(1);
assertCheck($alumniOne !== null && count($alumniOne->jobPostings()) >= 1, "RELATION: Alumni::jobPostings() returned authored postings");

$alumniTwo = Alumni::find(2);
assertCheck($alumniTwo !== null && count($alumniTwo->applications()) >= 1, "RELATION: Alumni::applications() returned submitted applications");

// 9. Nested & Direct Route Resolution
echo "\n9. Nested and Direct Routes Resolution:\n";
$router2 = new Router();
$nestedCompanyHit = false;
$nestedUserJobHit = false;
$nestedJobAppHit = false;
$colonParamHit = false;

$router2->get('/api/v1/companies/{id}/job-postings', function ($id) use (&$nestedCompanyHit) {
    $nestedCompanyHit = ($id === '2');
});
$router2->get('/api/v1/users/{id}/job-postings', function ($id) use (&$nestedUserJobHit) {
    $nestedUserJobHit = ($id === '1');
});
$router2->get('/api/v1/job-postings/{id}/applications', function ($id) use (&$nestedJobAppHit) {
    $nestedJobAppHit = ($id === '3');
});
$router2->get('/api/companies/:id/job-postings', function ($id) use (&$colonParamHit) {
    $colonParamHit = ($id === '5');
});

$router2->resolve('GET', '/api/v1/companies/2/job-postings');
assertCheck($nestedCompanyHit, "NESTED ROUTE: /api/v1/companies/{id}/job-postings resolved cleanly");

$router2->resolve('GET', '/api/v1/users/1/job-postings');
assertCheck($nestedUserJobHit, "NESTED ROUTE: /api/v1/users/{id}/job-postings resolved cleanly");

$router2->resolve('GET', '/api/v1/job-postings/3/applications');
assertCheck($nestedJobAppHit, "NESTED ROUTE: /api/v1/job-postings/{id}/applications resolved cleanly");

$router2->resolve('GET', '/api/companies/5/job-postings');
assertCheck($colonParamHit, "NESTED ROUTE: /api/companies/:id/job-postings (:id syntax) resolved cleanly");

// 10. Classroom Whiteboard Auto Routes (Images 3 & 4)
echo "\n10. Classroom Whiteboard Auto Routes:\n";
$routerClass = new Router();
$helloHit = false;
$helloNameVal = null;
$sumVal = null;
$okHit = false;

$routerClass->get('/ok', function () use (&$okHit) { $okHit = true; });
$routerClass->get('/hello', function () use (&$helloHit) { $helloHit = true; });
$routerClass->get('/hello/{name}', function ($name) use (&$helloNameVal) { $helloNameVal = "Hello, " . ucfirst($name) . "!"; });
$routerClass->get('/sum/{number1}/{number2}', function ($n1, $n2) use (&$sumVal) { $sumVal = (int)$n1 + (int)$n2; });

$routerClass->resolve('GET', '/ok');
assertCheck($okHit, "CLASSROOM ROUTE 1: GET /ok matches correctly");

$routerClass->resolve('GET', '/hello');
assertCheck($helloHit, "CLASSROOM ROUTE 2: GET /hello matches correctly");

$routerClass->resolve('GET', '/hello/emre');
assertCheck($helloNameVal === 'Hello, Emre!', "CLASSROOM ROUTE 3: GET /hello/emre matches 'Hello, Emre!'");

$routerClass->resolve('GET', '/sum/5/10');
assertCheck($sumVal === 15, "CLASSROOM ROUTE 4: GET /sum/5/10 calculates sum = 15");

// Trailing dot normalization test (e.g. user typed /jobs.)
$jobsHit = false;
$routerClass->get('/jobs', function () use (&$jobsHit) { $jobsHit = true; });
$routerClass->resolve('GET', '/jobs.');
assertCheck($jobsHit, "URL TOLERANCE: /jobs. (with trailing dot) successfully resolved to /jobs");

// Clean up created entities
$newApp->delete();
$newJob->delete();

echo "\n\033[1;32mTest Summary: {$passed} / {$total} assertions passed successfully.\033[0m\n\n";
exit($passed === $total ? 0 : 1);
