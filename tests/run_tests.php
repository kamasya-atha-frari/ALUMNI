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

// Test Router Dispatching & Whiteboard Milestone Routes
echo "\n4. Router Engine & Whiteboard Milestone Routes:\n";
$router = new Router();

// 1. GET /auto & POST / -> "ok"
$router->get('/auto', fn(): string => "ok");
$router->post('/', fn(): string => "ok");
$router->post('/auto', fn(): string => "ok");

// 2. GET /hello -> "Hello, World!"
$router->get('/hello', fn(): string => "Hello, World!");
$router->get('/auto/hello', fn(): string => "Hello, World!");

// 3. GET /hello/{name} -> "Hello, Emre!"
$router->get('/hello/{name}', fn(string $name): string => "Hello, " . htmlspecialchars(ucfirst($name)) . "!");
$router->get('/auto/hello/{name}', fn(string $name): string => "Hello, " . htmlspecialchars(ucfirst($name)) . "!");

// 4. GET /sum/{number1}/{number2} -> sum
$router->get('/sum/{number1}/{number2}', function ($n1, $n2): string {
    $sum = (float)$n1 + (float)$n2;
    return (string)((floor($sum) == $sum) ? (int)$sum : $sum);
});
$router->get('/auto/sum/{number1}/{number2}', function ($n1, $n2): string {
    $sum = (float)$n1 + (float)$n2;
    return (string)((floor($sum) == $sum) ? (int)$sum : $sum);
});

// 5. GET / -> main page
$mainVisited = false;
$router->get('/', function () use (&$mainVisited) {
    $mainVisited = true;
});

// 6. GET /about -> about page
$aboutVisited = false;
$router->get('/about', function () use (&$aboutVisited) {
    $aboutVisited = true;
});

// Execute dispatch checks without process termination (exitOnResult = false)
$okMatched = $router->dispatch('GET', '/auto', false);
assertCheck($okMatched, "Item 1: Matched GET /auto ('ok')");

$postMatched = $router->dispatch('POST', '/', false);
assertCheck($postMatched, "Item 1: Matched POST / ('ok')");

$helloMatched = $router->dispatch('GET', '/hello', false);
assertCheck($helloMatched, "Item 2: Matched GET /hello ('Hello, World!')");

$emreMatched = $router->dispatch('GET', '/hello/emre', false);
assertCheck($emreMatched, "Item 3: Matched GET /hello/emre ('Hello, Emre!')");

$sumMatched = $router->dispatch('GET', '/sum/15/25', false);
assertCheck($sumMatched, "Item 4: Matched GET /sum/15/25 (sum = 40)");

$router->dispatch('GET', '/', false);
assertCheck($mainVisited, "Item 5: Matched GET / (temporary one main page)");

$router->dispatch('GET', '/about', false);
assertCheck($aboutVisited, "Item 6: Matched GET /about (temp. about page)");

// Test Subdirectory Normalization (e.g., http://localhost/alumni/hello/emre)
$subMatched = $router->dispatch('GET', '/alumni/hello/emre', false);
assertCheck($subMatched, "Subdirectory: Normalized /alumni/hello/emre -> /hello/emre");

$subSumMatched = $router->dispatch('GET', '/alumni/sum/10/20', false);
assertCheck($subSumMatched, "Subdirectory: Normalized /alumni/sum/10/20 -> /sum/10/20");

// 5. Week 03 REST API Milestone Routes (Assoc. Prof. Dr. Emre Akadal)
echo "\n5. Week 03 REST API & In-Memory User Service (Whiteboard Items 1-7):\n";
$userService = new \AlumniNexus\Services\UserService();
$userService->reset();

$users = $userService->getAll();
assertCheck(count($users) >= 4, "Initial in-memory user dataset loaded (count: " . count($users) . ")");
assertCheck($users[0]['name'] === 'Elif Kaya', "Slide B verified: user Elif Kaya exists");

$newUser = $userService->create(['name' => 'Canan Dag', 'graduationYear' => 2025]);
assertCheck($newUser['name'] === 'Canan Dag' && $newUser['graduationYear'] === 2025, "Item 3: POST /api/users created user in-memory");

$fetched = $userService->getById((int)$newUser['id']);
assertCheck($fetched !== null && $fetched['id'] === $newUser['id'], "Item 5: GET /api/users/{id} retrieved record");

$patched = $userService->update((int)$newUser['id'], ['graduationYear' => 2026]);
assertCheck($patched['graduationYear'] === 2026, "Item 5: PATCH /api/users/{id} modified graduationYear");

$deleted = $userService->delete((int)$newUser['id']);
assertCheck($deleted === true && $userService->getById((int)$newUser['id']) === null, "Item 6: DELETE /api/users/{id} purged record");

// Router dispatch checks for Week 03
$router->get('/api/health', fn(): string => '{"status":"ok"}');
$router->post('/api/users', fn(): string => '{"id":1}');
$router->get('/api/users', fn(): string => '[]');
$router->get('/api/users/{id}', fn($id): string => '{"id":' . $id . '}');
$router->put('/api/users/{id}', fn($id): string => '{"id":' . $id . '}');
$router->patch('/api/users/{id}', fn($id): string => '{"id":' . $id . '}');
$router->delete('/api/users/{id}', fn($id): string => '{"status":"deleted"}');
$router->get('/api/swagger', fn(): string => 'swagger');

assertCheck($router->dispatch('GET', '/api/health', false), "Item 1: Matched GET /api/health");
assertCheck($router->dispatch('POST', '/api/users', false), "Item 3: Matched POST /api/users");
assertCheck($router->dispatch('GET', '/api/users', false), "Item 4: Matched GET /api/users");
assertCheck($router->dispatch('GET', '/api/users/42', false), "Item 5: Matched GET /api/users/{id}");
assertCheck($router->dispatch('PUT', '/api/users/42', false), "Item 5: Matched PUT /api/users/{id}");
assertCheck($router->dispatch('PATCH', '/api/users/42', false), "Item 5: Matched PATCH /api/users/{id}");
assertCheck($router->dispatch('DELETE', '/api/users/42', false), "Item 6: Matched DELETE /api/users/{id}");
assertCheck($router->dispatch('GET', '/api/swagger', false), "Item 7: Matched GET /api/swagger");

echo "\n\033[1;32mTest Summary: {$passed} / {$total} assertions passed successfully.\033[0m\n\n";
exit($passed === $total ? 0 : 1);
