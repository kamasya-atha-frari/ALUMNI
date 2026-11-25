<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for STEP 5: Admin Panel API, UI Guard & Governance Mutations
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

use AlumniIstü\Core\RequestContext;
use AlumniIstü\Core\Response;
use AlumniIstü\Core\Router;
use AlumniIstü\Middleware\AuthMiddleware;
use AlumniIstü\Middleware\RoleMiddleware;
use AlumniIstü\Services\AnnouncementService;
use AlumniIstü\Services\AuthService;
use AlumniIstü\Services\CaptchaService;
use AlumniIstü\Services\TokenService;

$passed = 0;
$total = 0;

function assertAdmin(bool $condition, string $label): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m {$label}\n";
    } else {
        echo "  \033[31m✖ FAIL\033[0m {$label}\n";
    }
}

echo "\n\033[1;35m=== Admin Panel & Governance Verification Suite ===\033[0m\n\n";

$tmp = bin2hex(random_bytes(6));
$captchaFile = sys_get_temp_dir() . "/adm_cap_{$tmp}.json";
$userFile = sys_get_temp_dir() . "/adm_usr_{$tmp}.json";
$revFile = sys_get_temp_dir() . "/adm_rev_{$tmp}.json";
$annFile = sys_get_temp_dir() . "/adm_ann_{$tmp}.json";

$captchaService = new CaptchaService($captchaFile);
$tokenService = new TokenService($revFile);
$authService = new AuthService($userFile, $tokenService);
$annService = new AnnouncementService($annFile);

// Seed two users in fallback store via registration + promotion
$cap1 = 'cap_adm1_' . $tmp;
$captchaService->storeKnownChallenge($cap1, 'ADM111', 300);
$u1 = $authService->register([
    'username' => 'kamasya_' . $tmp,
    'email' => "kamasya_{$tmp}@ogr.iu.edu.tr",
    'password' => 'AdminPass2026!',
    'full_name' => 'Kamasya Atha Frari',
    'role' => 'alumni',
    'captcha_id' => $cap1,
    'captcha_answer' => 'ADM111',
], $captchaService);
$adminUserId = (int)($u1['user']['id'] ?? 0);
$promoted = $authService->updateUserRole($adminUserId, 'admin');
assertAdmin($promoted['ok'] === true && ($promoted['user']['role'] ?? '') === 'admin', "Promote owner account to 'admin' succeeds");

$cap2 = 'cap_adm2_' . $tmp;
$captchaService->storeKnownChallenge($cap2, 'USR222', 300);
$u2 = $authService->register([
    'username' => 'member_' . $tmp,
    'email' => "member_{$tmp}@ogr.iu.edu.tr",
    'password' => 'MemberPass2026!',
    'full_name' => 'Regular Member',
    'role' => 'student',
    'captcha_id' => $cap2,
    'captcha_answer' => 'USR222',
], $captchaService);
$memberId = (int)($u2['user']['id'] ?? 0);

// 1. Self-lockout guards
echo "1. Admin Self-Protection & User Governance:\n";
$selfDemote = $authService->updateUserRole($adminUserId, 'alumni', $adminUserId);
assertAdmin(!$selfDemote['ok'] && ($selfDemote['error_code'] ?? '') === 'CANNOT_DEMOTE_SELF', "Blocks admin self-demotion (422 CANNOT_DEMOTE_SELF)");

$selfDeactivate = $authService->updateUserStatus($adminUserId, false, $adminUserId);
assertAdmin(!$selfDeactivate['ok'] && ($selfDeactivate['error_code'] ?? '') === 'CANNOT_DEACTIVATE_SELF', "Blocks admin self-deactivation (422 CANNOT_DEACTIVATE_SELF)");

$selfDelete = $authService->deleteUser($adminUserId, $adminUserId);
assertAdmin(!$selfDelete['ok'] && ($selfDelete['error_code'] ?? '') === 'CANNOT_DELETE_SELF', "Blocks admin self-deletion (422 CANNOT_DELETE_SELF)");

// 2. Role, status & delete mutations on target user
$changeRole = $authService->updateUserRole($memberId, 'moderator', $adminUserId);
assertAdmin($changeRole['ok'] === true && ($changeRole['user']['role'] ?? '') === 'moderator', "Admin changes member role from student -> moderator");

$deactivate = $authService->updateUserStatus($memberId, false, $adminUserId);
assertAdmin($deactivate['ok'] === true && ($deactivate['user']['is_active'] ?? true) === false, "Admin deactivates user account");

$inactiveLogin = $authService->login("member_{$tmp}", 'MemberPass2026!');
assertAdmin(!$inactiveLogin['ok'] && $inactiveLogin['status'] === 403 && ($inactiveLogin['error_code'] ?? '') === 'ACCOUNT_INACTIVE', "Deactivated user is blocked from logging in (HTTP 403 ACCOUNT_INACTIVE)");

$reactivate = $authService->updateUserStatus($memberId, true, $adminUserId);
assertAdmin($reactivate['ok'] === true && ($reactivate['user']['is_active'] ?? false) === true, "Admin reactivates user account");

$delRes = $authService->deleteUser($memberId, $adminUserId);
assertAdmin($delRes['ok'] === true && $authService->findById($memberId) === null, "Admin deletes target user permanently");

// 3. Announcement CRUD & Validation
echo "\n2. Admin Announcements Validation & CRUD:\n";
$badAnn = $annService->validateInput(['body' => 'Missing title']);
assertAdmin(isset($badAnn['title']), "Announcement validation rejects missing title");

$badCat = $annService->validateInput(['title' => 'Valid', 'body' => 'Body', 'category' => 'unknown']);
assertAdmin(isset($badCat['category']), "Announcement validation rejects unknown category");

$createdAnn = $annService->create([
    'title' => 'MIS Accreditation Notice',
    'body' => 'Survey closes Friday.',
    'category' => 'accreditation',
    'status' => 'draft',
    'is_pinned' => true,
], ['id' => $adminUserId, 'username' => 'kamasya']);
assertAdmin($createdAnn['id'] > 0 && $createdAnn['status'] === 'draft' && $createdAnn['is_pinned'] === true, "Admin creates draft pinned announcement");

$updatedAnn = $annService->update((int)$createdAnn['id'], ['status' => 'published']);
assertAdmin(($updatedAnn['status'] ?? '') === 'published', "Admin publishes announcement");
assertAdmin($annService->delete((int)$createdAnn['id']) === true, "Admin deletes announcement");

// 4. Protected /admin UI & API Guard Checks
echo "\n3. Admin UI & API Middleware Guards:\n";
$router = new Router();
$router->get('/admin', fn() => Response::text('ADMIN_UI_OK'), [new AuthMiddleware($tokenService), RoleMiddleware::requireRole(['admin'], $tokenService)]);

// 4.1 API/curl without token -> 401 JSON
unset($_SERVER['HTTP_AUTHORIZATION']);
$_SERVER['HTTP_ACCEPT'] = 'application/json';
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$router->resolve('GET', '/admin');
$out401 = ob_get_clean();
Response::$preventExit = false;
$json401 = json_decode($out401, true);
assertAdmin(($json401['code'] ?? null) === 401, "Unauthenticated access to /admin returns 401");

// 4.2 Non-admin token -> 403 Forbidden
$alumniTok = $tokenService->issueToken(['id' => 99, 'username' => 'stu', 'email' => 's@iu.edu.tr', 'role' => 'student'])['access_token'];
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$alumniTok}";
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$router->resolve('GET', '/admin');
$out403 = ob_get_clean();
Response::$preventExit = false;
$json403 = json_decode($out403, true);
assertAdmin(($json403['code'] ?? null) === 403 && ($json403['error_code'] ?? '') === 'FORBIDDEN_ROLE', "Non-admin access to /admin returns 403 FORBIDDEN_ROLE");

// 4.3 Admin token -> 200 OK
$adminTok = $tokenService->issueToken(['id' => $adminUserId, 'username' => 'kamasya', 'email' => 'kamasyaatha.frari@ogr.iu.edu.tr', 'role' => 'admin'])['access_token'];
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$adminTok}";
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$router->resolve('GET', '/admin');
$out200 = ob_get_clean();
Response::$preventExit = false;
assertAdmin(trim($out200) === 'ADMIN_UI_OK', "Admin token passes AuthMiddleware + RoleMiddleware(['admin']) onto /admin");

unset($_SERVER['HTTP_AUTHORIZATION']);
RequestContext::clear();
@unlink($captchaFile);
@unlink($userFile);
@unlink($revFile);
@unlink($annFile);

echo "\n\033[1;32mAdmin Panel Verification Summary: {$passed} / {$total} assertions passed.\033[0m\n\n";
exit($passed === $total ? 0 : 1);
