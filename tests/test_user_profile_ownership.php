<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for STEP 7:
 * User-Specific Pages, Editable Profile API & Backend Ownership Authorization
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniIstü\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use AlumniIstü\Controllers\UserProfileApiController;
use AlumniIstü\Core\RequestContext;
use AlumniIstü\Core\Response;
use AlumniIstü\Core\Router;
use AlumniIstü\Middleware\AuthMiddleware;
use AlumniIstü\Services\AnnouncementService;
use AlumniIstü\Services\AuthService;
use AlumniIstü\Services\CaptchaService;
use AlumniIstü\Services\TokenService;

$passed = 0;
$total = 0;

function assertOwn(bool $condition, string $label): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m {$label}\n";
    } else {
        echo "  \033[31m✖ FAIL\033[0m {$label}\n";
    }
}

echo "\n\033[1;35m=== User Profile & Ownership Authorization Suite (STEP 7) ===\033[0m\n\n";

$tmp = bin2hex(random_bytes(6));
$capFile = sys_get_temp_dir() . "/own_cap_{$tmp}.json";
$usrFile = sys_get_temp_dir() . "/own_usr_{$tmp}.json";
$revFile = sys_get_temp_dir() . "/own_rev_{$tmp}.json";
$annFile = sys_get_temp_dir() . "/own_ann_{$tmp}.json";

$captchaService = new CaptchaService($capFile);
$tokenService = new TokenService($revFile);
$authService = new AuthService($usrFile, $tokenService);
$annService = new AnnouncementService($annFile);

// Register 2 regular users + 1 admin
$c1 = 'c1_' . $tmp;
$captchaService->storeKnownChallenge($c1, 'AAA111', 300);
$userA = $authService->register([
    'username' => 'alice_' . $tmp,
    'email' => "alice_{$tmp}@ogr.iu.edu.tr",
    'password' => 'PassWord2026!',
    'full_name' => 'Alice Alumni',
    'role' => 'alumni',
    'captcha_id' => $c1,
    'captcha_answer' => 'AAA111',
], $captchaService);
$idA = (int)$userA['user']['id'];
$tokA = (string)$userA['token']['access_token'];

$c2 = 'c2_' . $tmp;
$captchaService->storeKnownChallenge($c2, 'BBB222', 300);
$userB = $authService->register([
    'username' => 'bob_' . $tmp,
    'email' => "bob_{$tmp}@ogr.iu.edu.tr",
    'password' => 'PassWord2026!',
    'full_name' => 'Bob Student',
    'role' => 'student',
    'captcha_id' => $c2,
    'captcha_answer' => 'BBB222',
], $captchaService);
$idB = (int)$userB['user']['id'];
$tokB = (string)$userB['token']['access_token'];

$c3 = 'c3_' . $tmp;
$captchaService->storeKnownChallenge($c3, 'CCC333', 300);
$userAdmin = $authService->register([
    'username' => 'adm_' . $tmp,
    'email' => "adm_{$tmp}@ogr.iu.edu.tr",
    'password' => 'AdminPass2026!',
    'full_name' => 'Admin User',
    'role' => 'alumni',
    'captcha_id' => $c3,
    'captcha_answer' => 'CCC333',
], $captchaService);
$idAdmin = (int)$userAdmin['user']['id'];
$authService->updateUserRole($idAdmin, 'admin');
$tokAdmin = (string)$authService->login("adm_{$tmp}", 'AdminPass2026!')['token']['access_token'];

// 1. Validation & Profile Update via AuthService
echo "1. Profile Field Validation & Self-Update:\n";
$badUrl = $authService->updateUserProfile($idA, ['linkedin_url' => 'not-a-valid-url']);
assertOwn(!$badUrl['ok'] && $badUrl['status'] === 422 && isset($badUrl['errors']['linkedin_url']), "Rejects malformed LinkedIn URL (HTTP 422)");

$badYear = $authService->updateUserProfile($idA, ['graduation_year' => 1800]);
assertOwn(!$badYear['ok'] && $badYear['status'] === 422 && isset($badYear['errors']['graduation_year']), "Rejects out-of-range graduation year (HTTP 422)");

$goodUpd = $authService->updateUserProfile($idA, [
    'bio' => 'Cloud & MIS architect mentoring undergraduates.',
    'location' => 'Istanbul, Turkey',
    'linkedin_url' => 'https://linkedin.com/in/alice-alumni',
    'github_url' => 'https://github.com/alice-alumni',
    'skills' => 'Python, SQL, Docker',
]);
assertOwn(
    $goodUpd['ok'] === true
    && ($goodUpd['user']['bio'] ?? '') === 'Cloud & MIS architect mentoring undergraduates.'
    && ($goodUpd['user']['skills'] ?? []) === ['Python', 'SQL', 'Docker'],
    "Persists bio, location, social links, and parsed skills cleanly"
);

// 2. Ownership Enforcement through Router + AuthMiddleware + UserProfileApiController
echo "\n2. Ownership & AuthMiddleware Enforcement:\n";
$ctrl = new UserProfileApiController($authService, null, $annService);
$router = new Router();
$router->get('/api/v1/profile/me', [$ctrl, 'showMe'], [new AuthMiddleware($tokenService)]);
$router->get('/api/v1/profile/users/{id}', [$ctrl, 'showById'], [new AuthMiddleware($tokenService)]);
$router->patch('/api/v1/profile/users/{id}', [$ctrl, 'updateById'], [new AuthMiddleware($tokenService)]);

// 2.1 Unauthenticated -> 401
unset($_SERVER['HTTP_AUTHORIZATION']);
$_SERVER['HTTP_ACCEPT'] = 'application/json';
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$router->resolve('GET', '/api/v1/profile/me');
$out401 = ob_get_clean();
Response::$preventExit = false;
$j401 = json_decode($out401, true);
assertOwn(($j401['code'] ?? null) === 401, "Unauthenticated GET /api/v1/profile/me returns 401");

// 2.2 User A reading User A's own profile -> 200
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokA}";
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$router->resolve('GET', "/api/v1/profile/users/{$idA}");
$outSelf = ob_get_clean();
Response::$preventExit = false;
$jSelf = json_decode($outSelf, true);
assertOwn(($jSelf['status'] ?? '') === 'success' && ($jSelf['data']['user']['id'] ?? null) === $idA, "Regular user can access their own profile (/profile/users/{self})");

// 2.3 User A attempting to access User B's profile -> 403 OWNERSHIP_VIOLATION
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokA}";
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$router->resolve('GET', "/api/v1/profile/users/{$idB}");
$outOther = ob_get_clean();
Response::$preventExit = false;
$jOther = json_decode($outOther, true);
assertOwn(
    ($jOther['code'] ?? null) === 403 && ($jOther['error_code'] ?? '') === 'OWNERSHIP_VIOLATION',
    "Regular user accessing another user's profile is blocked with 403 OWNERSHIP_VIOLATION"
);

// 2.4 Admin accessing User B's profile -> 200 OK
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$tokAdmin}";
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$router->resolve('GET', "/api/v1/profile/users/{$idB}");
$outAdmin = ob_get_clean();
Response::$preventExit = false;
$jAdmin = json_decode($outAdmin, true);
assertOwn(
    ($jAdmin['status'] ?? '') === 'success' && ($jAdmin['data']['user']['id'] ?? null) === $idB,
    "Admin can access any user's profile via /profile/users/{id} (HTTP 200)"
);

unset($_SERVER['HTTP_AUTHORIZATION']);
RequestContext::clear();
@unlink($capFile);
@unlink($usrFile);
@unlink($revFile);
@unlink($annFile);

echo "\n\033[1;32mUser Profile & Ownership Verification Summary: {$passed} / {$total} assertions passed.\033[0m\n\n";
exit($passed === $total ? 0 : 1);
