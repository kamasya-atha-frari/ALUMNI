<?php
declare(strict_types=1);

/**
 * Week 03 Automated API Verification Suite
 * 
 * Verifies all specifications from Assoc. Prof. Dr. Emre Akadal's lecture whiteboard:
 * 1. GET /api/health -> JSON
 * 2. Postman Collection artifact presence & validity
 * 3. POST /api/users (don't use any database yet)
 * 4. GET /api/users -> list all users
 * 5. PUT /api/users/{id}, PATCH /api/users/{id}, GET /api/users/{id}
 * 6. DELETE /api/users/{id}
 * 7. GET /api/swagger -> OpenAPI / Swagger UI
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniNexus\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require_once $file;
});

use AlumniNexus\Services\UserService;
use AlumniNexus\Controllers\UserController;
use AlumniNexus\Controllers\HealthController;
use AlumniNexus\Controllers\WebController;
use AlumniNexus\Core\Router;

$passed = 0;
$total = 0;

function assertTest(bool $condition, string $label): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m {$label}\n";
    } else {
        echo "  \033[31m✖ FAIL\033[0m {$label}\n";
    }
}

echo "\n\033[1;36m=== Week 03 REST API & In-Memory User Test Suite ===\033[0m\n\n";

// 1. Test In-Memory UserService
echo "1. UserService (In-Memory without Database):\n";
$tempStorage = sys_get_temp_dir() . '/test_alumni_users_' . uniqid() . '.json';
$userService = new UserService($tempStorage);
$userService->reset();

$initialUsers = $userService->getAll();
assertTest(count($initialUsers) === 4, "Initial seed users loaded (count: 4: Elif, Mert, Zeynep, Can)");
assertTest($initialUsers[0]['name'] === 'Elif Kaya' && $initialUsers[0]['graduationYear'] === 2024, "Slide B verified: Elif Kaya, 2024");

// Whiteboard Item 3: POST /api/users (Create user, no DB)
$createdUser = $userService->create([
    'name' => 'Ahmet Yılmaz',
    'graduationYear' => 2025
]);
assertTest($createdUser['id'] === 5 && $createdUser['name'] === 'Ahmet Yılmaz', "Item 3: Created user with auto-increment ID 5");

$allAfterPost = $userService->getAll();
assertTest(count($allAfterPost) === 5, "Item 4: User count increased to 5");

// Whiteboard Item 5: GET /api/users/{id}
$fetchedUser = $userService->getById(5);
assertTest($fetchedUser !== null && $fetchedUser['name'] === 'Ahmet Yılmaz', "Item 5: Retrieved user by ID 5");

// Whiteboard Item 5: PUT /api/users/{id} (Replace whole)
$replacedUser = $userService->replace(5, [
    'name' => 'Ahmet Yılmaz (Updated)',
    'graduationYear' => 2026
]);
assertTest(
    $replacedUser['name'] === 'Ahmet Yılmaz (Updated)' && $replacedUser['graduationYear'] === 2026,
    "Item 5: PUT full replacement updated record"
);

// Whiteboard Item 5: PATCH /api/users/{id} (Change one field)
$patchedUser = $userService->update(5, [
    'graduationYear' => 2027
]);
assertTest(
    $patchedUser['name'] === 'Ahmet Yılmaz (Updated)' && $patchedUser['graduationYear'] === 2027,
    "Item 5: PATCH updated only graduationYear field"
);

// Whiteboard Item 6: DELETE /api/users/{id}
$deleted = $userService->delete(5);
assertTest($deleted === true, "Item 6: DELETE returned true for ID 5");
$notFoundUser = $userService->getById(5);
assertTest($notFoundUser === null, "Item 6: User with ID 5 no longer exists");
assertTest(count($userService->getAll()) === 4, "Item 6: User count restored to 4");

// Clean up test temp file
if (file_exists($tempStorage)) {
    @unlink($tempStorage);
}

// 2. Test Router Dispatching for Week 03 Routes
echo "\n2. Router Dispatching for Week 03 Whiteboard Routes:\n";
$router = new Router();

// Register the routes
$router->get('/api/health', [HealthController::class, 'ping']);
$router->post('/api/users', [UserController::class, 'store']);
$router->get('/api/users', [UserController::class, 'index']);
$router->get('/api/users/{id}', [UserController::class, 'show']);
$router->put('/api/users/{id}', [UserController::class, 'update']);
$router->patch('/api/users/{id}', [UserController::class, 'patch']);
$router->delete('/api/users/{id}', [UserController::class, 'destroy']);
$router->get('/api/swagger', [WebController::class, 'swagger']);

// Also test Slide 4 /api/alumni aliases
$router->get('/api/alumni', [UserController::class, 'index']);
$router->post('/api/alumni', [UserController::class, 'store']);

assertTest($router->dispatch('GET', '/api/health', false), "Item 1: Matched GET /api/health");
assertTest($router->dispatch('POST', '/api/users', false), "Item 3: Matched POST /api/users");
assertTest($router->dispatch('GET', '/api/users', false), "Item 4: Matched GET /api/users");
assertTest($router->dispatch('GET', '/api/users/1', false), "Item 5: Matched GET /api/users/1");
assertTest($router->dispatch('PUT', '/api/users/1', false), "Item 5: Matched PUT /api/users/1");
assertTest($router->dispatch('PATCH', '/api/users/1', false), "Item 5: Matched PATCH /api/users/1");
assertTest($router->dispatch('DELETE', '/api/users/1', false), "Item 6: Matched DELETE /api/users/1");
assertTest($router->dispatch('GET', '/api/swagger', false), "Item 7: Matched GET /api/swagger");

// Test Subdirectory Normalization
assertTest($router->dispatch('GET', '/alumni/api/users', false), "Subdirectory: Normalized /alumni/api/users -> /api/users");
assertTest($router->dispatch('PATCH', '/alumni/api/users/2', false), "Subdirectory: Normalized PATCH /alumni/api/users/2");
assertTest($router->dispatch('GET', '/api/alumni', false), "Slide 4 Alias: Matched GET /api/alumni");

// 3. Postman Collection Verification
echo "\n3. Postman Collection Artifact (Whiteboard Item 2):\n";
$postmanPath = __DIR__ . '/../docs/AlumniNexus_Week03_Postman_Collection.json';
assertTest(file_exists($postmanPath), "Postman collection file exists at docs/AlumniNexus_Week03_Postman_Collection.json");
$postmanJson = json_decode(file_get_contents($postmanPath), true);
assertTest(isset($postmanJson['item']) && count($postmanJson['item']) >= 8, "Postman collection defines all 8 endpoint requests");

echo "\n\033[1;32mWeek 03 Test Summary: {$passed} / {$total} assertions passed successfully.\033[0m\n\n";
exit($passed === $total ? 0 : 1);
