<?php
declare(strict_types=1);

/**
 * AlumniNexus - Intelligent Alumni Network Platform
 * Istanbul University MIS (YBS)
 */

// PSR-4 Style Lightweight Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniNexus\\';
    $baseDir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use AlumniNexus\Core\Router;
use AlumniNexus\Core\Response;
use AlumniNexus\Controllers\AlumniApiController;
use AlumniNexus\Controllers\HealthController;
use AlumniNexus\Controllers\WebController;
use AlumniNexus\Controllers\UserController;

$router = new Router();

// Web Views & Whiteboard Milestone Routes
// 1. Root / Utility Health Check (Item 1 on whiteboard & title "Routes /auto")
$router->get('/auto', function (): string {
    return "ok";
});
$router->post('/auto', function (): string {
    return "ok";
});
$router->post('/', function (): string {
    return "ok";
});

// 2. Hello World Route (Item 2 on whiteboard: GET /hello -> "Hello, World!")
$router->get('/hello', function (): string {
    return "Hello, World!";
});
$router->get('/auto/hello', function (): string {
    return "Hello, World!";
});

// 3. Parameterized Greeting Route (Item 3 on whiteboard: GET /hello/{name} -> "Hello, Emre!")
$router->get('/hello/{name}', function (string $name): string {
    return "Hello, " . htmlspecialchars(ucfirst($name)) . "!";
});
$router->get('/auto/hello/{name}', function (string $name): string {
    return "Hello, " . htmlspecialchars(ucfirst($name)) . "!";
});

// 4. Parameterized Calculation Route (Item 4 on whiteboard: GET /sum/{number1}/{number2} -> sum)
$router->get('/sum/{number1}/{number2}', function ($number1, $number2): string {
    $sum = (float)$number1 + (float)$number2;
    $res = (floor($sum) == $sum) ? (int)$sum : $sum;
    return (string)$res;
});
$router->get('/auto/sum/{number1}/{number2}', function ($number1, $number2): string {
    $sum = (float)$number1 + (float)$number2;
    $res = (floor($sum) == $sum) ? (int)$sum : $sum;
    return (string)$res;
});
$router->get('/auto/sum/{a}/{b}', function ($a, $b): string {
    $sum = (float)$a + (float)$b;
    $res = (floor($sum) == $sum) ? (int)$sum : $sum;
    return (string)$res;
});

// 5. Temporary One Main Page (Item 5 on whiteboard: GET / -> temporary one main page)
$router->get('/', [WebController::class, 'home']);

// 6. Temporary About Page (Item 6 on whiteboard: GET /about -> temp. about page)
$router->get('/about', [WebController::class, 'about']);
$router->get('/auto/about', [WebController::class, 'about']);

// Additional Views
$router->get('/analytics', [WebController::class, 'analytics']);
$router->get('/swagger', [WebController::class, 'swagger']);
$router->get('/docs', [WebController::class, 'swagger']);
$router->get('/api/swagger', [WebController::class, 'swagger']);

// Week 03 REST API Milestone Routes (Whiteboard items 1, 3, 4, 5, 6, 7)
// Item 1: Health Probe (GET /api/health -> JSON)
$router->get('/api/health', [HealthController::class, 'check']);
$router->get('/auto/api/health', [HealthController::class, 'check']);

// Item 3: POST /api/users (don't use any database yet)
$router->post('/api/users', [UserController::class, 'store']);

// Item 4: GET /api/users -> list all users
$router->get('/api/users', [UserController::class, 'index']);

// Item 5: GET / PUT / PATCH /api/users/{id}
$router->get('/api/users/{id}', [UserController::class, 'show']);
$router->put('/api/users/{id}', [UserController::class, 'update']);
$router->patch('/api/users/{id}', [UserController::class, 'patch']);

// Item 6: DELETE /api/users/{id}
$router->delete('/api/users/{id}', [UserController::class, 'destroy']);

// Slide 4 /api/alumni Aliases for lecture live demo
$router->get('/api/alumni', [UserController::class, 'index']);
$router->get('/api/alumni/{id}', [UserController::class, 'show']);
$router->post('/api/alumni', [UserController::class, 'store']);
$router->put('/api/alumni/{id}', [UserController::class, 'update']);
$router->patch('/api/alumni/{id}', [UserController::class, 'patch']);
$router->delete('/api/alumni/{id}', [UserController::class, 'destroy']);

// REST API v1 Routes
$router->get('/api/v1/health', [HealthController::class, 'check']);
$router->get('/api/v1/alumni', [AlumniApiController::class, 'index']);
$router->get('/api/v1/alumni/{id}', [AlumniApiController::class, 'show']);
$router->get('/api/v1/analytics', [AlumniApiController::class, 'analytics']);

// Base URL / Backward-compatible route for /alumni
$router->get('/alumni', function (): void {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accept, 'application/json') || isset($_GET['api']) || isset($_GET['json'])) {
        $controller = new AlumniApiController();
        $controller->index();
        return;
    }
    $web = new WebController();
    $web->home();
});
$router->post('/alumni', function (): string {
    return "ok";
});

// OpenAPI Specification JSON
$router->get('/openapi.json', function () {
    $file = __DIR__ . '/../docs/openapi.json';
    if (file_exists($file)) {
        header('Content-Type: application/json; charset=utf-8');
        echo file_get_contents($file);
    } else {
        Response::json(['error' => 'OpenAPI schema missing'], 404);
    }
});

// Resolve incoming request
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/';

$router->resolve($method, $uri);
