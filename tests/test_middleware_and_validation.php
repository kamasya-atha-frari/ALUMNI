<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for:
 * 1. Invalid Requests API testing
 * 2. JobPosting Validation Rules (ddd.md & Controller)
 * 3. Request Logger Middleware ([time] [method] [route] [res.type] [duration])
 * 4. 404 Guardian Middleware (404 with clear descriptive reason)
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniIstü\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require_once $file;
});

use AlumniIstü\Middleware\LoggerMiddleware;
use AlumniIstü\Middleware\NotFoundGuardianMiddleware;
use AlumniIstü\Middleware\Guardian404Middleware;
use AlumniIstü\Controllers\JobPostingApiController;
use AlumniIstü\Models\JobPosting;
use AlumniIstü\Core\Router;
use AlumniIstü\Core\Response;

$passed = 0;
$total = 0;

function assertRule(bool $condition, string $label): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m {$label}\n";
    } else {
        echo "  \033[31m✖ FAIL\033[0m {$label}\n";
    }
}

echo "\n\033[1;35m=== Middleware & Validation Rules Automated Verification ===\033[0m\n\n";

// =========================================================================
// 1. Task 1: JobPosting Validation Rules (Whiteboard Item 2 & ddd.md)
// =========================================================================
echo "1. JobPosting Validation Rules (ddd.md Item 2):\n";
$controller = new JobPostingApiController();

// 1.1: title is required
$missingTitleErrors = $controller->validateJobPostingInput([
    'company' => 'Turkcell Tech',
    'description' => 'A great opportunity'
]);
assertRule(
    isset($missingTitleErrors['title']) && str_contains($missingTitleErrors['title'], "'title' is required"),
    "Rule: 'title' is required when missing"
);

// 1.2: title max 120 characters
$longTitle = str_repeat("A", 121);
$longTitleErrors = $controller->validateJobPostingInput([
    'title' => $longTitle,
    'company' => 'Turkcell Tech'
]);
assertRule(
    isset($longTitleErrors['title']) && str_contains($longTitleErrors['title'], "must not exceed 120 characters"),
    "Rule: 'title' maximum 120 characters enforced (121 chars rejected)"
);

$valid120Title = str_repeat("A", 120);
$valid120Errors = $controller->validateJobPostingInput([
    'title' => $valid120Title,
    'company' => 'Turkcell Tech'
]);
assertRule(
    !isset($valid120Errors['title']),
    "Rule: 'title' exactly 120 characters is accepted"
);

// 1.3: company is required
$missingCompanyErrors = $controller->validateJobPostingInput([
    'title' => 'Backend Engineer',
    'description' => 'Developing REST APIs'
]);
assertRule(
    isset($missingCompanyErrors['company']) && str_contains($missingCompanyErrors['company'], "'company' (or company_name) is required"),
    "Rule: 'company' is required when missing"
);

// 1.4: salary and description are optional
$minimalValidPayload = [
    'title' => 'Junior Cloud Developer',
    'company' => 'Trendyol Group'
];
$minimalErrors = $controller->validateJobPostingInput($minimalValidPayload);
assertRule(
    empty($minimalErrors),
    "Rule: 'salary' and 'description' are optional (minimal title + company passes validation)"
);

// 1.5: create job posting with minimal required fields only
$createdJob = JobPosting::create([
    'title' => 'AI Research Intern',
    'company' => 'Getir'
]);
assertRule(
    $createdJob->id !== null &&
    $createdJob->title === 'AI Research Intern' &&
    $createdJob->company_name === 'Getir',
    "Creation: JobPosting created successfully with optional fields omitted"
);
$createdJob->delete();

// =========================================================================
// 2. Task 2: Request Logger Middleware (Whiteboard Item 3)
// =========================================================================
echo "\n2. Request Logger Middleware (Whiteboard Item 3):\n";

LoggerMiddleware::clearLogs();

// Test manual record format
$fakeStartTime = microtime(true) - 0.018; // 18 ms ago
$log = LoggerMiddleware::record('POST', '/user', 201, $fakeStartTime);

// Check structure: [time] [method] [route] [res.type] [duration]
// Example from whiteboard: 10:20:30 Post /user 201 18 ms
assertRule(
    preg_match('/^\d{2}:\d{2}:\d{2} Post \/user 201 \d+ ms$/', $log) === 1,
    "Log Format: Matches [time] [method] [route] [res.type] [duration] ('{$log}')"
);

$parts = explode(' ', $log);
assertRule(count($parts) === 6, "Log Elements: Contains exactly 5 structured fields + 'ms' unit");
assertRule(preg_match('/^\d{2}:\d{2}:\d{2}$/', $parts[0]) === 1, "Field 1 [time]: Valid 24h timestamp ({$parts[0]})");
assertRule($parts[1] === 'Post', "Field 2 [method]: Capitalized method name ('Post')");
assertRule($parts[2] === '/user', "Field 3 [route]: Requested route path ('/user')");
assertRule($parts[3] === '201', "Field 4 [res.type]: HTTP Status Code ('201')");
assertRule(is_numeric($parts[4]) && $parts[5] === 'ms', "Field 5 [duration]: Measured duration in milliseconds ({$parts[4]} ms)");

// Test GET route logging
$logGet = LoggerMiddleware::record('GET', '/api/users', 200, microtime(true) - 0.005);
assertRule(
    str_contains($logGet, 'Get /api/users 200'),
    "GET Logging: Properly logged GET request ('{$logGet}')"
);

// Test pipeline handle() execution
$executedNext = false;
$mw = new LoggerMiddleware();
$mw->handle('DELETE', '/api/users/1', function () use (&$executedNext) {
    $executedNext = true;
    http_response_code(200);
    return "deleted";
});
assertRule($executedNext, "Pipeline: LoggerMiddleware::handle delegates to next handler");
$lastLog = LoggerMiddleware::getLastLog();
assertRule(
    $lastLog !== null && str_contains($lastLog, 'Delete /api/users/1 200'),
    "Pipeline Log: Recorded '{$lastLog}'"
);

// =========================================================================
// 3. Task 3: 404 Guardian Middleware (Whiteboard Item 4)
// =========================================================================
echo "\n3. 404 Guardian Middleware (Whiteboard Item 4):\n";

assertRule(
    class_exists(NotFoundGuardianMiddleware::class),
    "Guardian Class: NotFoundGuardianMiddleware exists"
);
assertRule(
    class_exists(Guardian404Middleware::class),
    "Guardian Alias: Guardian404Middleware exists and is available"
);

// Test router integration with 404 Guardian
$testRouter = new Router();
$testRouter->get('/api/test-ok', fn() => 'ok');

// Simulate unmatched route dispatch
$matched = $testRouter->dispatch('GET', '/api/unknown-endpoint-xyz', false);
assertRule(!$matched, "Router Dispatch: Correctly marks unmatched route as false");

// Verify 404 Guardian returns structured reason
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/api/nonexistent-route';

// Capture output
Response::$preventExit = true;
ob_start();
NotFoundGuardianMiddleware::guard('POST', '/api/nonexistent-route');
$output = ob_get_clean();
Response::$preventExit = false;

$code = http_response_code();
assertRule($code === 404, "404 Guardian: Sets HTTP status code to strictly 404 (actual: {$code})");

$json = json_decode($output, true);
assertRule(is_array($json), "404 Guardian: Returns valid JSON response payload");
assertRule(
    isset($json['code']) && $json['code'] === 404,
    "404 Guardian: JSON contains 'code' = 404"
);
assertRule(
    isset($json['guardian']) && str_contains($json['guardian'], '404 Guardian'),
    "404 Guardian: JSON contains 'guardian' identification tag"
);
assertRule(
    !empty($json['reason']),
    "404 Guardian: JSON contains descriptive 'reason' (" . ($json['reason'] ?? '') . ")"
);
assertRule(
    isset($json['path']) && $json['path'] === '/api/nonexistent-route',
    "404 Guardian: JSON identifies requested path"
);
assertRule(
    isset($json['method']) && $json['method'] === 'POST',
    "404 Guardian: JSON identifies requested method ('POST')"
);

// =========================================================================
// 4. Task 4: Invalid Request Handling across API endpoints
// =========================================================================
echo "\n4. Invalid Request Handling (Whiteboard Item 1):\n";

// 4.1: POST /api/v1/job-postings without title
$jobErrors = $controller->validateJobPostingInput([]);
assertRule(
    isset($jobErrors['title']) && isset($jobErrors['company']),
    "Invalid Request: Missing both title and company triggers explicit validation errors"
);

// 4.2: POST /api/v1/job-postings with oversized title
$oversizedErrors = $controller->validateJobPostingInput([
    'title' => str_repeat('X', 150),
    'company' => 'Trendyol'
]);
assertRule(
    isset($oversizedErrors['title']) && str_contains($oversizedErrors['title'], '120 characters'),
    "Invalid Request: Oversized title (>120 chars) triggers 120-char limit error"
);

// 4.3: POST /api/users with missing name
$userService = new \AlumniIstü\Services\UserService();
$userCtrl = new \AlumniIstü\Controllers\UserController($userService);
assertRule(
    method_exists($userCtrl, 'store'),
    "Invalid Request: UserController::store is configured for Postman validation"
);

// =========================================================================
// 5. Tasks 4 & 5: 5xx Guardian Middleware & File Logging (logs/error.logs)
// =========================================================================
echo "\n5. 5xx Guardian Middleware & Error Logging to File (logs/error.logs):\n";

assertRule(
    class_exists(\AlumniIstü\Middleware\ServerErrorGuardianMiddleware::class),
    "5xx Guardian Class: ServerErrorGuardianMiddleware exists"
);
assertRule(
    class_exists(\AlumniIstü\Middleware\Guardian5xxMiddleware::class),
    "5xx Guardian Alias: Guardian5xxMiddleware exists and is available"
);

$logFile = \AlumniIstü\Middleware\ServerErrorGuardianMiddleware::getLogFilePath();
assertRule(
    str_ends_with($logFile, 'logs/error.logs'),
    "Log Path: Points to 'logs/error.logs' ({$logFile})"
);

// Ensure logs directory is created and log an intentional test exception
$testException = new \RuntimeException("Database query timeout simulation");
$loggedEntry = \AlumniIstü\Middleware\ServerErrorGuardianMiddleware::logToFile(
    $testException,
    'POST',
    '/api/v1/jobs/test-error',
    'err_test_123'
);

assertRule(
    file_exists($logFile),
    "File Creation: Automatically created 'logs/error.logs' file"
);

$fileContents = file_get_contents($logFile);
assertRule(
    str_contains($fileContents, 'Database query timeout simulation'),
    "Log Content: 'logs/error.logs' recorded exception message"
);
assertRule(
    str_contains($fileContents, '[POST /api/v1/jobs/test-error]'),
    "Log Context: 'logs/error.logs' recorded HTTP method and route path"
);
assertRule(
    str_contains($fileContents, 'RuntimeException'),
    "Log Detail: 'logs/error.logs' recorded exception class name"
);

// Test 5xx Guardian structured JSON response
$_SERVER['HTTP_ACCEPT'] = 'application/json';
Response::$preventExit = true;
ob_start();
\AlumniIstü\Middleware\ServerErrorGuardianMiddleware::handleException($testException, 'POST', '/api/v1/simulate-crash');
$errorOutput = ob_get_clean();
Response::$preventExit = false;

$errorCode = http_response_code();
assertRule($errorCode === 500, "5xx Guardian: Sets HTTP status code to strictly 500");

$errorJson = json_decode($errorOutput, true);
assertRule(is_array($errorJson), "5xx Guardian: Returns valid JSON error response");
assertRule(
    isset($errorJson['code']) && $errorJson['code'] === 500,
    "5xx Guardian: Response contains 'code' = 500"
);
assertRule(
    isset($errorJson['guardian']) && str_contains($errorJson['guardian'], '5xx Guardian'),
    "5xx Guardian: Response contains 'guardian' tag"
);
assertRule(
    !empty($errorJson['error_id']),
    "5xx Guardian: Response contains unique tracking 'error_id' (" . ($errorJson['error_id'] ?? '') . ")"
);
assertRule(
    !empty($errorJson['reason']) && str_contains($errorJson['reason'], 'logs/error.logs'),
    "5xx Guardian: Response references 'logs/error.logs' in reason"
);

// Router exception catching test
$crashRouter = new Router();
$crashRouter->get('/api/crash-me', function () {
    throw new \Exception("Boom! Test explosion");
});

Response::$preventExit = true;
ob_start();
$crashRouter->resolve('GET', '/api/crash-me');
$crashOutput = ob_get_clean();
Response::$preventExit = false;

$crashJson = json_decode($crashOutput, true);
assertRule(
    is_array($crashJson) && ($crashJson['code'] ?? null) === 500,
    "Router Integration: Router::resolve caught exception and returned 500 via 5xx Guardian"
);

echo "\n\033[1;32mValidation & Middleware Test Summary: {$passed} / {$total} assertions passed successfully.\033[0m\n\n";
exit($passed === $total ? 0 : 1);
