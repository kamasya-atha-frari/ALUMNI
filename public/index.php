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
use AlumniNexus\Controllers\JobPostingApiController;
use AlumniNexus\Controllers\ApplicationApiController;
use AlumniNexus\Controllers\CompanyApiController;
use AlumniNexus\Controllers\HealthController;
use AlumniNexus\Controllers\WebController;

// Initialize Environment Variables
Env::load();

$router = new Router();

// Web Views
$router->get('/', [WebController::class, 'home']);
$router->get('/jobs', [WebController::class, 'jobs']);
$router->get('/job-postings', [WebController::class, 'jobs']);
$router->get('/applications', [WebController::class, 'applications']);
$router->get('/analytics', [WebController::class, 'analytics']);
$router->get('/swagger', [WebController::class, 'swagger']);
$router->get('/docs', [WebController::class, 'swagger']);
$router->get('/about', [WebController::class, 'about']);

// Classroom Whiteboard Auto Routes
$router->get('/ok', [WebController::class, 'ok']);
$router->get('/hello', [WebController::class, 'hello']);
$router->get('/hello/{name}', [WebController::class, 'helloName']);
$router->get('/sum/{number1}/{number2}', [WebController::class, 'sum']);

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
$router->get('/api/users', [AlumniApiController::class, 'index']);
$router->post('/api/users', [AlumniApiController::class, 'create']);
$router->get('/api/users/{id}', [AlumniApiController::class, 'show']);
$router->put('/api/users/{id}', [AlumniApiController::class, 'update']);
$router->delete('/api/users/{id}', [AlumniApiController::class, 'delete']);

// -------------------------------------------------------------
// Direct CRUD Routes: Job Postings
// -------------------------------------------------------------
$router->get('/api/v1/job-postings', [JobPostingApiController::class, 'index']);
$router->post('/api/v1/job-postings', [JobPostingApiController::class, 'create']);
$router->get('/api/v1/job-postings/{id}', [JobPostingApiController::class, 'show']);
$router->put('/api/v1/job-postings/{id}', [JobPostingApiController::class, 'update']);
$router->patch('/api/v1/job-postings/{id}', [JobPostingApiController::class, 'update']);
$router->delete('/api/v1/job-postings/{id}', [JobPostingApiController::class, 'delete']);

$router->get('/api/job-postings', [JobPostingApiController::class, 'index']);
$router->post('/api/job-postings', [JobPostingApiController::class, 'create']);
$router->get('/api/job-postings/{id}', [JobPostingApiController::class, 'show']);
$router->put('/api/job-postings/{id}', [JobPostingApiController::class, 'update']);
$router->patch('/api/job-postings/{id}', [JobPostingApiController::class, 'update']);
$router->delete('/api/job-postings/{id}', [JobPostingApiController::class, 'delete']);

// -------------------------------------------------------------
// Nested Routes: Job Postings under Companies & Users
// (Supports both {id} and :id syntax)
// -------------------------------------------------------------
$router->get('/api/v1/companies/{id}/job-postings', [JobPostingApiController::class, 'byCompany']);
$router->post('/api/v1/companies/{id}/job-postings', [JobPostingApiController::class, 'createForCompany']);
$router->get('/api/companies/{id}/job-postings', [JobPostingApiController::class, 'byCompany']);
$router->post('/api/companies/{id}/job-postings', [JobPostingApiController::class, 'createForCompany']);

$router->get('/api/v1/users/{id}/job-postings', [JobPostingApiController::class, 'byUser']);
$router->post('/api/v1/users/{id}/job-postings', [JobPostingApiController::class, 'createForUser']);
$router->get('/api/users/{id}/job-postings', [JobPostingApiController::class, 'byUser']);
$router->post('/api/users/{id}/job-postings', [JobPostingApiController::class, 'createForUser']);

$router->get('/api/v1/alumni/{id}/job-postings', [JobPostingApiController::class, 'byUser']);
$router->post('/api/v1/alumni/{id}/job-postings', [JobPostingApiController::class, 'createForUser']);
$router->get('/api/alumni/{id}/job-postings', [JobPostingApiController::class, 'byUser']);
$router->post('/api/alumni/{id}/job-postings', [JobPostingApiController::class, 'createForUser']);

// -------------------------------------------------------------
// Direct CRUD Routes: Applications
// -------------------------------------------------------------
$router->get('/api/v1/applications', [ApplicationApiController::class, 'index']);
$router->post('/api/v1/applications', [ApplicationApiController::class, 'create']);
$router->get('/api/v1/applications/{id}', [ApplicationApiController::class, 'show']);
$router->put('/api/v1/applications/{id}', [ApplicationApiController::class, 'update']);
$router->patch('/api/v1/applications/{id}', [ApplicationApiController::class, 'update']);
$router->delete('/api/v1/applications/{id}', [ApplicationApiController::class, 'delete']);

$router->get('/api/applications', [ApplicationApiController::class, 'index']);
$router->post('/api/applications', [ApplicationApiController::class, 'create']);
$router->get('/api/applications/{id}', [ApplicationApiController::class, 'show']);
$router->put('/api/applications/{id}', [ApplicationApiController::class, 'update']);
$router->patch('/api/applications/{id}', [ApplicationApiController::class, 'update']);
$router->delete('/api/applications/{id}', [ApplicationApiController::class, 'delete']);

// -------------------------------------------------------------
// Nested Routes: Applications under Job Postings & Users
// (Supports both {id} and :id syntax)
// -------------------------------------------------------------
$router->get('/api/v1/job-postings/{id}/applications', [ApplicationApiController::class, 'byJobPosting']);
$router->post('/api/v1/job-postings/{id}/applications', [ApplicationApiController::class, 'createForJobPosting']);
$router->get('/api/job-postings/{id}/applications', [ApplicationApiController::class, 'byJobPosting']);
$router->post('/api/job-postings/{id}/applications', [ApplicationApiController::class, 'createForJobPosting']);

$router->get('/api/v1/users/{id}/applications', [ApplicationApiController::class, 'byUser']);
$router->get('/api/users/{id}/applications', [ApplicationApiController::class, 'byUser']);
$router->get('/api/v1/alumni/{id}/applications', [ApplicationApiController::class, 'byUser']);
$router->get('/api/alumni/{id}/applications', [ApplicationApiController::class, 'byUser']);

// -------------------------------------------------------------
// Companies Catalog Routes
// -------------------------------------------------------------
$router->get('/api/v1/companies', [CompanyApiController::class, 'index']);
$router->get('/api/v1/companies/{id}', [CompanyApiController::class, 'show']);
$router->post('/api/v1/companies', [CompanyApiController::class, 'create']);
$router->get('/api/companies', [CompanyApiController::class, 'index']);
$router->get('/api/companies/{id}', [CompanyApiController::class, 'show']);
$router->post('/api/companies', [CompanyApiController::class, 'create']);

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
