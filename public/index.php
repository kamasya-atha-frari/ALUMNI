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
use AlumniNexus\Controllers\UserController;

// Initialize Environment Variables
Env::load();

$router = new Router();

// =============================================================
// Web Views & Whiteboard Milestone Routes
// =============================================================

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

// 5. Main Page (Item 5 on whiteboard: GET / -> temporary one main page)
$router->get('/', [WebController::class, 'home']);

// Interactive Views
$router->get('/jobs', [WebController::class, 'jobs']);
$router->get('/job-postings', [WebController::class, 'jobs']);
$router->get('/applications', [WebController::class, 'applications']);

// 6. Temporary About Page (Item 6 on whiteboard: GET /about -> temp. about page)
$router->get('/about', [WebController::class, 'about']);
$router->get('/auto/about', [WebController::class, 'about']);

// Additional Views
$router->get('/analytics', [WebController::class, 'analytics']);
$router->get('/swagger', [WebController::class, 'swagger']);
$router->get('/docs', [WebController::class, 'swagger']);
$router->get('/api/swagger', [WebController::class, 'swagger']);

// Classroom Whiteboard Auto Routes
$router->get('/ok', [WebController::class, 'ok']);

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
$router->post('/alumni', function (): void {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!empty($data) && is_array($data)) {
        $controller = new AlumniApiController();
        $controller->create();
        return;
    }
    Response::text("ok");
});

// =============================================================
// Health Probes & Telemetry
// =============================================================
$router->get('/api/health', [HealthController::class, 'check']);
$router->get('/auto/api/health', [HealthController::class, 'check']);
$router->get('/api/v1/health', [HealthController::class, 'check']);
$router->get('/api/v1/analytics', [AlumniApiController::class, 'analytics']);

// =============================================================
// Week 03 REST API Milestone Routes: In-Memory /api/users
// =============================================================
$router->post('/api/users', [UserController::class, 'store']);
$router->get('/api/users', [UserController::class, 'index']);
$router->get('/api/users/{id}', [UserController::class, 'show']);
$router->put('/api/users/{id}', [UserController::class, 'update']);
$router->patch('/api/users/{id}', [UserController::class, 'patch']);
$router->delete('/api/users/{id}', [UserController::class, 'destroy']);

// =============================================================
// Full REST API CRUD Routes: Alumni (Week 04 Database + Model)
// =============================================================
$router->get('/api/v1/alumni', [AlumniApiController::class, 'index']);
$router->post('/api/v1/alumni', [AlumniApiController::class, 'create']);
$router->get('/api/v1/alumni/{id}', [AlumniApiController::class, 'show']);
$router->put('/api/v1/alumni/{id}', [AlumniApiController::class, 'update']);
$router->patch('/api/v1/alumni/{id}', [AlumniApiController::class, 'update']);
$router->delete('/api/v1/alumni/{id}', [AlumniApiController::class, 'delete']);

// Backward-compatible routes: /api/alumni
$router->get('/api/alumni', [AlumniApiController::class, 'index']);
$router->post('/api/alumni', [AlumniApiController::class, 'create']);
$router->get('/api/alumni/{id}', [AlumniApiController::class, 'show']);
$router->put('/api/alumni/{id}', [AlumniApiController::class, 'update']);
$router->patch('/api/alumni/{id}', [AlumniApiController::class, 'update']);
$router->delete('/api/alumni/{id}', [AlumniApiController::class, 'delete']);

// =============================================================
// Week 05/06: Direct CRUD Routes - Job Postings
// =============================================================
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

// =============================================================
// Week 05/06: Nested Routes - Job Postings under Companies & Users
// (Supports both {id} and :id syntax)
// =============================================================
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

// =============================================================
// Week 05/06: Direct CRUD Routes - Applications
// =============================================================
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

// =============================================================
// Week 05/06: Nested Routes - Applications under Job Postings & Users
// (Supports both {id} and :id syntax)
// =============================================================
$router->get('/api/v1/job-postings/{id}/applications', [ApplicationApiController::class, 'byJobPosting']);
$router->post('/api/v1/job-postings/{id}/applications', [ApplicationApiController::class, 'createForJobPosting']);
$router->get('/api/job-postings/{id}/applications', [ApplicationApiController::class, 'byJobPosting']);
$router->post('/api/job-postings/{id}/applications', [ApplicationApiController::class, 'createForJobPosting']);

$router->get('/api/v1/users/{id}/applications', [ApplicationApiController::class, 'byUser']);
$router->get('/api/users/{id}/applications', [ApplicationApiController::class, 'byUser']);
$router->get('/api/v1/alumni/{id}/applications', [ApplicationApiController::class, 'byUser']);
$router->get('/api/alumni/{id}/applications', [ApplicationApiController::class, 'byUser']);

// =============================================================
// Companies Catalog Routes
// =============================================================
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
