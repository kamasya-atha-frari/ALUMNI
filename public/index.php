<?php
declare(strict_types=1);

/**
 * AlumniIstü - Intelligent Alumni Network Platform
 * Istanbul University MIS (YBS)
 */

// PSR-4 Style Lightweight Autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniIstü\\';
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

use AlumniIstü\Core\Router;
use AlumniIstü\Core\Response;
use AlumniIstü\Core\Env;
use AlumniIstü\Controllers\AdminApiController;
use AlumniIstü\Controllers\AlumniApiController;
use AlumniIstü\Controllers\AuthApiController;
use AlumniIstü\Controllers\JobPostingApiController;
use AlumniIstü\Controllers\ApplicationApiController;
use AlumniIstü\Controllers\CompanyApiController;
use AlumniIstü\Controllers\HealthController;
use AlumniIstü\Controllers\WebController;
use AlumniIstü\Controllers\UserController;
use AlumniIstü\Controllers\UserProfileApiController;
use AlumniIstü\Middleware\AuthMiddleware;
use AlumniIstü\Middleware\LoggerMiddleware;
use AlumniIstü\Middleware\NotFoundGuardianMiddleware;
use AlumniIstü\Middleware\RoleMiddleware;
use AlumniIstü\Middleware\ServerErrorGuardianMiddleware;

// Register 5xx Server Error Guardian (catches unhandled exceptions & logs to logs/error.logs)
ServerErrorGuardianMiddleware::register();

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

// Interactive Views & Aliases with Graceful Redirection
$router->get('/home', fn() => Response::redirect('/'));
$router->get('/jobs', [WebController::class, 'jobs']);
$router->get('/job', fn() => Response::redirect('/jobs'));
$router->get('/job-postings', [WebController::class, 'jobs']);
$router->get('/applications', [WebController::class, 'applications']);
$router->get('/application', fn() => Response::redirect('/applications'));

// 6. Temporary About Page (Item 6 on whiteboard: GET /about -> temp. about page)
$router->get('/about', [WebController::class, 'about']);
$router->get('/auto/about', [WebController::class, 'about']);

// Additional Views
$router->get('/analytics', [WebController::class, 'analytics']);
$router->get('/register', [WebController::class, 'register']);
$router->get('/signup', fn() => Response::redirect('/register'));
$router->get('/login', [WebController::class, 'login']);
$router->get('/signin', fn() => Response::redirect('/login'));
// Authenticated User Dashboard / My Profile — Protected server-side by AuthMiddleware
$router->get('/my-profile', [WebController::class, 'myProfile'], [new AuthMiddleware()]);
$router->get('/dashboard', [WebController::class, 'myProfile'], [new AuthMiddleware()]);
$router->get('/profile', [WebController::class, 'myProfile'], [new AuthMiddleware()]);
// Admin Panel UI — Protected server-side by AuthMiddleware + RoleMiddleware(['admin'])
$router->get(
    '/admin',
    [WebController::class, 'admin'],
    [new AuthMiddleware(), RoleMiddleware::requireRole(['admin'])]
);
$router->get('/admin-panel', fn() => Response::redirect('/admin'));
$router->get('/swagger', [WebController::class, 'swagger']);
$router->get('/docs', [WebController::class, 'swagger']);
$router->get('/api/swagger', [WebController::class, 'swagger']);

// Classroom Whiteboard Auto Routes
$router->get('/ok', [WebController::class, 'ok']);

// Base URL / Backward-compatible route for /alumni & Individual Alumni Profile View
$router->get('/alumni/{id}', function (string $id): void {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accept, 'application/json') || isset($_GET['api']) || isset($_GET['json'])) {
        (new AlumniApiController())->show($id);
        return;
    }
    (new WebController())->profile($id);
});
$router->get('/profile/{id}', [WebController::class, 'profile']);

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
// Health Probes & Telemetry (Public + Role-Protected Accreditation)
// =============================================================
$router->get('/api/health', [HealthController::class, 'check']);
$router->get('/auto/api/health', [HealthController::class, 'check']);
$router->get('/api/v1/health', [HealthController::class, 'check']);
$router->get('/api/v1/analytics', [AlumniApiController::class, 'analytics']);
$router->get(
    '/api/v1/analytics/accreditation',
    [AlumniApiController::class, 'accreditationAnalytics'],
    [new AuthMiddleware(), RoleMiddleware::requireRole(['faculty', 'moderator', 'admin'])]
);
$router->get(
    '/api/analytics/accreditation',
    [AlumniApiController::class, 'accreditationAnalytics'],
    [new AuthMiddleware(), RoleMiddleware::requireRole(['faculty', 'moderator', 'admin'])]
);

// =============================================================
// Authentication, Registration, Anti-Bot CAPTCHA & Google OAuth
// Public: providers, captcha, register, login, google*, departments
// Protected (AuthMiddleware): logout, me
// Admin RBAC (AuthMiddleware + RoleMiddleware(['admin'])): admin/users*
// =============================================================
$router->get('/api/v1/auth/providers', [AuthApiController::class, 'providers']);
$router->get('/api/v1/auth/captcha', [AuthApiController::class, 'captcha']);
$router->post('/api/v1/auth/register', [AuthApiController::class, 'register']);
$router->post('/api/v1/auth/login', [AuthApiController::class, 'login']);
$router->post('/api/v1/auth/logout', [AuthApiController::class, 'logout'], [new AuthMiddleware()]);
$router->get('/api/v1/auth/me', [AuthApiController::class, 'me'], [new AuthMiddleware()]);
$router->get('/api/v1/auth/google', [AuthApiController::class, 'googleRedirect']);
$router->get('/api/v1/auth/google/callback', [AuthApiController::class, 'googleCallback']);
$router->post('/api/v1/auth/google/token', [AuthApiController::class, 'googleToken']);
$router->get('/api/v1/departments', [AuthApiController::class, 'departments']);

$router->get('/api/auth/providers', [AuthApiController::class, 'providers']);
$router->get('/api/auth/captcha', [AuthApiController::class, 'captcha']);
$router->post('/api/auth/register', [AuthApiController::class, 'register']);
$router->post('/api/auth/login', [AuthApiController::class, 'login']);
$router->post('/api/auth/logout', [AuthApiController::class, 'logout'], [new AuthMiddleware()]);
$router->get('/api/auth/me', [AuthApiController::class, 'me'], [new AuthMiddleware()]);
$router->get('/api/auth/google', [AuthApiController::class, 'googleRedirect']);
$router->get('/api/auth/google/callback', [AuthApiController::class, 'googleCallback']);
$router->post('/api/auth/google/token', [AuthApiController::class, 'googleToken']);
$router->get('/api/departments', [AuthApiController::class, 'departments']);

// Public Published Department Announcements
$router->get('/api/v1/announcements', [AdminApiController::class, 'publicAnnouncements']);
$router->get('/api/announcements', [AdminApiController::class, 'publicAnnouncements']);

// =============================================================
// User-Specific Profile & Activity API (AuthMiddleware + Ownership Check)
// =============================================================
$authOnly = [new AuthMiddleware()];
foreach (['/api/v1/profile', '/api/profile'] as $profilePrefix) {
    $router->get("{$profilePrefix}/me", [UserProfileApiController::class, 'showMe'], $authOnly);
    $router->put("{$profilePrefix}/me", [UserProfileApiController::class, 'updateMe'], $authOnly);
    $router->patch("{$profilePrefix}/me", [UserProfileApiController::class, 'updateMe'], $authOnly);
    $router->get("{$profilePrefix}/me/activity", [UserProfileApiController::class, 'myActivity'], $authOnly);
    $router->get("{$profilePrefix}/users/{id}", [UserProfileApiController::class, 'showById'], $authOnly);
    $router->put("{$profilePrefix}/users/{id}", [UserProfileApiController::class, 'updateById'], $authOnly);
    $router->patch("{$profilePrefix}/users/{id}", [UserProfileApiController::class, 'updateById'], $authOnly);
}

// =============================================================
// Admin Panel Control Plane API (Protected: AuthMiddleware + admin Role)
// =============================================================
$adminGuard = [new AuthMiddleware(), RoleMiddleware::requireRole(['admin'])];

foreach (['/api/v1/admin', '/api/admin'] as $adminPrefix) {
    $router->get("{$adminPrefix}/overview", [AdminApiController::class, 'overview'], $adminGuard);

    // Users & Roles Governance
    $router->get("{$adminPrefix}/users", [AdminApiController::class, 'listUsers'], $adminGuard);
    $router->patch("{$adminPrefix}/users/{id}/role", [AdminApiController::class, 'updateUserRole'], $adminGuard);
    $router->patch("{$adminPrefix}/users/{id}/status", [AdminApiController::class, 'updateUserStatus'], $adminGuard);
    $router->delete("{$adminPrefix}/users/{id}", [AdminApiController::class, 'deleteUser'], $adminGuard);

    // Core AlumniIstü Directory Management
    $router->post("{$adminPrefix}/alumni", [AdminApiController::class, 'createAlumni'], $adminGuard);
    $router->put("{$adminPrefix}/alumni/{id}", [AdminApiController::class, 'updateAlumni'], $adminGuard);
    $router->patch("{$adminPrefix}/alumni/{id}", [AdminApiController::class, 'updateAlumni'], $adminGuard);
    $router->delete("{$adminPrefix}/alumni/{id}", [AdminApiController::class, 'deleteAlumni'], $adminGuard);

    // Job Postings Moderation
    $router->get("{$adminPrefix}/job-postings", [AdminApiController::class, 'listJobs'], $adminGuard);
    $router->put("{$adminPrefix}/job-postings/{id}", [AdminApiController::class, 'updateJob'], $adminGuard);
    $router->patch("{$adminPrefix}/job-postings/{id}", [AdminApiController::class, 'updateJob'], $adminGuard);
    $router->delete("{$adminPrefix}/job-postings/{id}", [AdminApiController::class, 'deleteJob'], $adminGuard);

    // Department Announcements / Posts Management
    $router->get("{$adminPrefix}/announcements", [AdminApiController::class, 'listAnnouncements'], $adminGuard);
    $router->post("{$adminPrefix}/announcements", [AdminApiController::class, 'createAnnouncement'], $adminGuard);
    $router->put("{$adminPrefix}/announcements/{id}", [AdminApiController::class, 'updateAnnouncement'], $adminGuard);
    $router->patch("{$adminPrefix}/announcements/{id}", [AdminApiController::class, 'updateAnnouncement'], $adminGuard);
    $router->delete("{$adminPrefix}/announcements/{id}", [AdminApiController::class, 'deleteAnnouncement'], $adminGuard);
}

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
$router->delete('/api/v1/alumni/{id}', [AlumniApiController::class, 'delete'], [new AuthMiddleware(), RoleMiddleware::requireRole(['moderator', 'admin'])]);

// Backward-compatible routes: /api/alumni
$router->get('/api/alumni', [AlumniApiController::class, 'index']);
$router->post('/api/alumni', [AlumniApiController::class, 'create']);
$router->get('/api/alumni/{id}', [AlumniApiController::class, 'show']);
$router->put('/api/alumni/{id}', [AlumniApiController::class, 'update']);
$router->patch('/api/alumni/{id}', [AlumniApiController::class, 'update']);
$router->delete('/api/alumni/{id}', [AlumniApiController::class, 'delete'], [new AuthMiddleware(), RoleMiddleware::requireRole(['moderator', 'admin'])]);

// =============================================================
// Week 05/06: Direct CRUD Routes - Job Postings
// =============================================================
$router->get('/api/v1/job-postings', [JobPostingApiController::class, 'index']);
$router->post('/api/v1/job-postings', [JobPostingApiController::class, 'create']);
$router->get('/api/v1/job-postings/{id}', [JobPostingApiController::class, 'show']);
$router->put('/api/v1/job-postings/{id}', [JobPostingApiController::class, 'update']);
$router->patch('/api/v1/job-postings/{id}', [JobPostingApiController::class, 'update']);
$router->delete('/api/v1/job-postings/{id}', [JobPostingApiController::class, 'delete'], [new AuthMiddleware(), RoleMiddleware::requireRole(['alumni', 'employer', 'moderator', 'admin'])]);

$router->get('/api/job-postings', [JobPostingApiController::class, 'index']);
$router->post('/api/job-postings', [JobPostingApiController::class, 'create']);
$router->get('/api/job-postings/{id}', [JobPostingApiController::class, 'show']);
$router->put('/api/job-postings/{id}', [JobPostingApiController::class, 'update']);
$router->patch('/api/job-postings/{id}', [JobPostingApiController::class, 'update']);
$router->delete('/api/job-postings/{id}', [JobPostingApiController::class, 'delete'], [new AuthMiddleware(), RoleMiddleware::requireRole(['alumni', 'employer', 'moderator', 'admin'])]);

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
$router->put('/api/v1/applications/{id}', [ApplicationApiController::class, 'update'], [new AuthMiddleware(), RoleMiddleware::requireRole(['alumni', 'employer', 'moderator', 'admin'])]);
$router->patch('/api/v1/applications/{id}', [ApplicationApiController::class, 'update'], [new AuthMiddleware(), RoleMiddleware::requireRole(['alumni', 'employer', 'moderator', 'admin'])]);
$router->delete('/api/v1/applications/{id}', [ApplicationApiController::class, 'delete'], [new AuthMiddleware(), RoleMiddleware::requireRole(['student', 'alumni', 'moderator', 'admin'])]);

$router->get('/api/applications', [ApplicationApiController::class, 'index']);
$router->post('/api/applications', [ApplicationApiController::class, 'create']);
$router->get('/api/applications/{id}', [ApplicationApiController::class, 'show']);
$router->put('/api/applications/{id}', [ApplicationApiController::class, 'update'], [new AuthMiddleware(), RoleMiddleware::requireRole(['alumni', 'employer', 'moderator', 'admin'])]);
$router->patch('/api/applications/{id}', [ApplicationApiController::class, 'update'], [new AuthMiddleware(), RoleMiddleware::requireRole(['alumni', 'employer', 'moderator', 'admin'])]);
$router->delete('/api/applications/{id}', [ApplicationApiController::class, 'delete'], [new AuthMiddleware(), RoleMiddleware::requireRole(['student', 'alumni', 'moderator', 'admin'])]);

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
// Companies Catalog Routes (Public Reads, Employer/Admin Create)
// =============================================================
$router->get('/api/v1/companies', [CompanyApiController::class, 'index']);
$router->get('/api/v1/companies/{id}', [CompanyApiController::class, 'show']);
$router->post('/api/v1/companies', [CompanyApiController::class, 'create'], [new AuthMiddleware(), RoleMiddleware::requireRole(['employer', 'moderator', 'admin'])]);
$router->get('/api/companies', [CompanyApiController::class, 'index']);
$router->get('/api/companies/{id}', [CompanyApiController::class, 'show']);
$router->post('/api/companies', [CompanyApiController::class, 'create'], [new AuthMiddleware(), RoleMiddleware::requireRole(['employer', 'moderator', 'admin'])]);

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

// Start Request Logger Middleware (Whiteboard Item 3):
// Outputs directly to terminal: [time] [method] [route] [res.type] [duration]
LoggerMiddleware::start($method, $uri);

$router->resolve($method, $uri);
