<?php
/**
 * Web Route Definitions
 */

require_once __DIR__ . '/../controllers/UserController.php';

function renderView(string $viewName): void {
    $file = __DIR__ . '/../views/' . $viewName;
    if (file_exists($file)) {
        header('Content-Type: text/html; charset=utf-8');
        echo file_get_contents($file);
    } else {
        http_response_code(404);
        echo "View {$viewName} not found.";
    }
}

/** @var Router $router */
$router->get('/', function () {
    renderView('main.html');
});

$router->get('/about', function () {
    renderView('about.html');
});

$router->get('/swagger', function () {
    renderView('swagger.html');
});

$router->get('/docs', function () {
    renderView('swagger.html');
});

// User Management Web Routes
$router->get('/users', [UserController::class, 'index']);
$router->get('/users/create', [UserController::class, 'create']);
$router->post('/users', function () {
    $ctrl = new UserController();
    $ctrl->store($_POST);
});
$router->get('/users/{id}', function ($id) {
    $ctrl = new UserController();
    $ctrl->show((int)$id);
});
$router->post('/users/{id}/update', function ($id) {
    $ctrl = new UserController();
    $ctrl->update((int)$id, $_POST);
});
$router->post('/users/{id}/delete', function ($id) {
    $ctrl = new UserController();
    $ctrl->delete((int)$id);
});
