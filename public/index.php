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
use AlumniNexus\Core\Env;
use AlumniNexus\Controllers\AlumniApiController;
use AlumniNexus\Controllers\HealthController;
use AlumniNexus\Controllers\WebController;

// Initialize Environment Variables
Env::load();

$router = new Router();

// Web Views
$router->get('/', [WebController::class, 'home']);
$router->get('/analytics', [WebController::class, 'analytics']);
$router->get('/swagger', [WebController::class, 'swagger']);
$router->get('/docs', [WebController::class, 'swagger']);

// Health Probe & Telemetry
$router->get('/api/v1/health', [HealthController::class, 'check']);
$router->get('/api/v1/analytics', [AlumniApiController::class, 'analytics']);

// Full REST API CRUD Routes: /api/v1/alumni
$router->get('/api/v1/alumni', [AlumniApiController::class, 'index']);
$router->post('/api/v1/alumni', [AlumniApiController::class, 'create']);
$router->get('/api/v1/alumni/{id}', [AlumniApiController::class, 'show']);
$router->put('/api/v1/alumni/{id}', [AlumniApiController::class, 'update']);
$router->patch('/api/v1/alumni/{id}', [AlumniApiController::class, 'update']);
$router->delete('/api/v1/alumni/{id}', [AlumniApiController::class, 'delete']);

// Backward-compatible routes: /api/alumni and /alumni
$router->get('/api/alumni', [AlumniApiController::class, 'index']);
$router->post('/api/alumni', [AlumniApiController::class, 'create']);
$router->get('/api/alumni/{id}', [AlumniApiController::class, 'show']);
$router->put('/api/alumni/{id}', [AlumniApiController::class, 'update']);
$router->delete('/api/alumni/{id}', [AlumniApiController::class, 'delete']);
$router->get('/alumni', [AlumniApiController::class, 'index']);

// Classroom Slide Compatible Routes: /api/users
// Supports: POST /api/users {"name": "Ada", "email": "ada@cv.edu.tr"}
$router->get('/api/users', [AlumniApiController::class, 'index']);
$router->post('/api/users', [AlumniApiController::class, 'create']);
$router->get('/api/users/{id}', [AlumniApiController::class, 'show']);
$router->put('/api/users/{id}', [AlumniApiController::class, 'update']);
$router->delete('/api/users/{id}', [AlumniApiController::class, 'delete']);

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
