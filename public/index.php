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

$router = new Router();

// Web Views
$router->get('/', [WebController::class, 'home']);
$router->get('/analytics', [WebController::class, 'analytics']);
$router->get('/swagger', [WebController::class, 'swagger']);
$router->get('/docs', [WebController::class, 'swagger']);

// REST API v1 Routes
$router->get('/api/v1/health', [HealthController::class, 'check']);
$router->get('/api/v1/alumni', [AlumniApiController::class, 'index']);
$router->get('/api/v1/alumni/{id}', [AlumniApiController::class, 'show']);
$router->get('/api/v1/analytics', [AlumniApiController::class, 'analytics']);

// Backward-compatible route for /alumni
$router->get('/alumni', [AlumniApiController::class, 'index']);

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
