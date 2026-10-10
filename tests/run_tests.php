<?php
declare(strict_types=1);

/**
 * AlumniNexus Automated Test Suite
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

// Test Domain Enums
echo "1. Domain Enums:\n";
assertCheck(UserRole::ALUMNI->value === 'alumni', "UserRole::ALUMNI equals 'alumni'");
assertCheck(MentorshipStatus::AVAILABLE->badgeColor() === 'emerald', "MentorshipStatus badge is 'emerald'");

// Test Service Filtering
echo "\n2. Alumni Service Logic:\n";
$service = new AlumniService();
$all = $service->getAll();
assertCheck(count($all) >= 4, "Retrieved default alumni records (count: " . count($all) . ")");

$mentors = $service->getAll(['mentor' => 'true']);
assertCheck(count($mentors) === 3, "Filtered active mentors correctly (expected: 3, got: " . count($mentors) . ")");

$cohort2023 = $service->getAll(['cohort' => 2023]);
assertCheck(count($cohort2023) === 1 && $cohort2023[0]['full_name'] === 'Emre Arslan', "Filtered cohort 2023 accurately");

// Test Analytics
echo "\n3. Career Analytics Engine:\n";
$analytics = $service->getAnalytics();
assertCheck($analytics['employment_rate_percentage'] > 90, "Employment index exceeds 90% benchmark");
assertCheck(isset($analytics['top_industries']['Telecommunications & Cloud']), "Industry breakdown tracks Cloud & Telecom");

// Test Router Dispatching
echo "\n4. Router Engine Pattern Matching:\n";
$router = new Router();
$matchedId = null;
$router->get('/api/v1/alumni/{id}', function ($id) use (&$matchedId) {
    $matchedId = (int)$id;
});

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/alumni/42';
// simulate resolve via reflection
$ref = new ReflectionClass($router);
$method = $ref->getMethod('resolve');

echo "\n\033[1;32mTest Summary: {$passed} / {$total} assertions passed successfully.\033[0m\n\n";
exit($passed === $total ? 0 : 1);
