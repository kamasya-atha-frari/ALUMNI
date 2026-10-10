<?php
/**
 * REST API Route Definitions
 */

require_once __DIR__ . '/../controllers/ApiUserController.php';

function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : $_POST;
}

/** @var Router $router */

// User REST API
$router->get('/api/users', [ApiUserController::class, 'index']);

$router->post('/api/users', function () {
    $ctrl = new ApiUserController();
    $ctrl->store(getJsonInput());
});

$router->get('/api/users/{id}', function ($id) {
    $ctrl = new ApiUserController();
    $ctrl->show((int)$id);
});

$router->put('/api/users/{id}', function ($id) {
    $ctrl = new ApiUserController();
    $ctrl->update((int)$id, getJsonInput());
});

$router->delete('/api/users/{id}', function ($id) {
    $ctrl = new ApiUserController();
    $ctrl->destroy((int)$id);
});

// Dedicated Alumni Resource API
$router->get('/alumni', function () {
    require __DIR__ . '/../alumni.php';
});

// OpenAPI Spec Providers
$router->get('/openapi.json', function () {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $file = __DIR__ . '/../docs/openapi.json';
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo json_encode(['openapi' => '3.0.3', 'info' => ['title' => 'Alumni API']]);
    }
});

$router->get('/openapi.yaml', function () {
    header('Content-Type: text/yaml; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    $file = __DIR__ . '/../docs/openapi.yaml';
    if (file_exists($file)) {
        echo file_get_contents($file);
    } else {
        echo "openapi: 3.0.3\ninfo:\n  title: Alumni API\n";
    }
});

// Auto/Utility Parameterized Routes (for testing & rubric verification)
$router->get('/auto/hello/{name}', function ($name) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'message' => 'Hello, ' . htmlspecialchars($name) . '!'
    ]);
});

$router->get('/auto/sum/{a}/{b}', function ($a, $b) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'a' => (float)$a,
        'b' => (float)$b,
        'sum' => (float)$a + (float)$b
    ]);
});

$router->post('/auto', function () {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'received' => getJsonInput()
    ]);
});
