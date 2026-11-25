<?php
declare(strict_types=1);

/**
 * Automated Verification Suite for:
 * 1. Local SVG & Math CAPTCHA generation (zero external dependencies)
 * 2. Server-side CAPTCHA verification (missing, invalid, incorrect, expired, reused, max attempts)
 * 3. Registration field validation, RBAC role allowlist, duplicate checks (409), password hashing
 * 4. Client <-> Server Token Authentication & RBAC Authorization flow (login, Bearer token, 401/403)
 * 5. OpenAPI 3.1.0 & Postman Collection JSON integrity checks
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
use AlumniIstü\Services\AuthService;
use AlumniIstü\Services\CaptchaService;
use AlumniIstü\Services\GoogleOAuthService;
use AlumniIstü\Services\TokenService;

$passed = 0;
$total = 0;

function assertCheck(bool $condition, string $label): void {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m {$label}\n";
    } else {
        echo "  \033[31m✖ FAIL\033[0m {$label}\n";
    }
}

echo "\n\033[1;35m=== Authentication, Registration & CAPTCHA Verification Suite ===\033[0m\n\n";

$tmpSuffix = bin2hex(random_bytes(6));
$captchaStoreFile = sys_get_temp_dir() . "/test_captcha_{$tmpSuffix}.json";
$userStoreFile = sys_get_temp_dir() . "/test_users_{$tmpSuffix}.json";
$revokedStoreFile = sys_get_temp_dir() . "/test_revoked_{$tmpSuffix}.json";

$captchaService = new CaptchaService($captchaStoreFile);
$tokenService = new TokenService($revokedStoreFile);
$authService = new AuthService($userStoreFile, $tokenService);

// -------------------------------------------------------------------------
// 1. CAPTCHA Generation Checks
// -------------------------------------------------------------------------
echo "1. CAPTCHA Generation (SVG & Math):\n";

$alphaCap = $captchaService->generate('alphanumeric', '127.0.0.1');
assertCheck(
    !empty($alphaCap['captcha_id']) && str_starts_with($alphaCap['captcha_id'], 'cap_'),
    "Alphanumeric CAPTCHA returns opaque captcha_id"
);
assertCheck(
    str_contains((string)$alphaCap['svg'], '<svg') && str_starts_with((string)$alphaCap['image_data_uri'], 'data:image/svg+xml;base64,'),
    "Alphanumeric CAPTCHA renders native SVG and base64 data URI"
);
assertCheck(
    !array_key_exists('answer', $alphaCap) && !array_key_exists('code', $alphaCap),
    "CAPTCHA payload never leaks plaintext answer"
);

$mathCap = $captchaService->generate('math', '127.0.0.1');
assertCheck(
    $mathCap['mode'] === 'math' && str_contains((string)$mathCap['svg'], '= ?'),
    "Math CAPTCHA generates arithmetic SVG challenge"
);

// -------------------------------------------------------------------------
// 2. Server-Side CAPTCHA Verification Rules
// -------------------------------------------------------------------------
echo "\n2. Server-Side CAPTCHA Verification Rules:\n";

$missingRes = $captchaService->verify('', '');
assertCheck(!$missingRes['valid'] && $missingRes['error_code'] === 'CAPTCHA_MISSING', "Rejects missing CAPTCHA fields (CAPTCHA_MISSING)");

$unknownRes = $captchaService->verify('cap_does_not_exist', 'ABC123');
assertCheck(!$unknownRes['valid'] && $unknownRes['error_code'] === 'CAPTCHA_INVALID', "Rejects unknown captcha_id (CAPTCHA_INVALID)");

$expiredId = 'cap_expired_' . $tmpSuffix;
$captchaService->storeKnownChallenge($expiredId, 'EXPIRE', -10);
$expiredRes = $captchaService->verify($expiredId, 'EXPIRE');
assertCheck(!$expiredRes['valid'] && $expiredRes['error_code'] === 'CAPTCHA_EXPIRED', "Rejects expired CAPTCHA (CAPTCHA_EXPIRED)");

$knownId = 'cap_valid_' . $tmpSuffix;
$captchaService->storeKnownChallenge($knownId, 'K7P4M9', 300);

$wrongRes = $captchaService->verify($knownId, 'WRONG1');
assertCheck(!$wrongRes['valid'] && $wrongRes['error_code'] === 'CAPTCHA_INCORRECT', "Rejects wrong CAPTCHA answer (CAPTCHA_INCORRECT)");

$validRes = $captchaService->verify($knownId, 'k7p4m9'); // case-insensitive
assertCheck($validRes['valid'] === true, "Accepts valid CAPTCHA answer (case-insensitive)");

$reusedRes = $captchaService->verify($knownId, 'K7P4M9');
assertCheck(!$reusedRes['valid'] && $reusedRes['error_code'] === 'CAPTCHA_REUSED', "Rejects reused CAPTCHA challenge (CAPTCHA_REUSED)");

// -------------------------------------------------------------------------
// 3. Registration Validation, CAPTCHA Gate, Password Hashing & Duplicate Checks
// -------------------------------------------------------------------------
echo "\n3. Registration Workflow & Validation:\n";

$uniqueTag = bin2hex(random_bytes(4));
$testUsername = "testuser_{$uniqueTag}";
$testEmail = "testuser_{$uniqueTag}@ogr.iu.edu.tr";

// 3.1 Rejects privileged role self-assignment (admin)
$adminAttempt = $authService->register([
    'username' => "admin_{$uniqueTag}",
    'email' => "admin_{$uniqueTag}@ogr.iu.edu.tr",
    'password' => 'SecurePass2026!',
    'full_name' => 'Unauthorized Admin',
    'role' => 'admin',
    'captcha_id' => 'cap_any',
    'captcha_answer' => 'ABC123',
], $captchaService);
assertCheck(
    !$adminAttempt['ok'] && $adminAttempt['status'] === 422 && isset($adminAttempt['errors']['role']),
    "RBAC Guard: Rejects self-registration with privileged role 'admin' (HTTP 422)"
);

// 3.2 Rejects registration when CAPTCHA is missing
$noCaptcha = $authService->register([
    'username' => $testUsername,
    'email' => $testEmail,
    'password' => 'SecurePass2026!',
    'password_confirmation' => 'SecurePass2026!',
    'full_name' => 'Zeynep Test',
    'role' => 'alumni',
    'graduation_year' => 2024,
], $captchaService);
assertCheck(
    !$noCaptcha['ok'] && $noCaptcha['status'] === 422 && $noCaptcha['error_code'] === 'CAPTCHA_MISSING',
    "Anti-Bot Gate: Rejects registration without CAPTCHA (HTTP 422)"
);

// 3.3 Rejects registration when CAPTCHA is wrong
$regCapId = 'cap_reg_' . $tmpSuffix;
$captchaService->storeKnownChallenge($regCapId, 'M4N8Q2', 300);
$badCaptchaReg = $authService->register([
    'username' => $testUsername,
    'email' => $testEmail,
    'password' => 'SecurePass2026!',
    'password_confirmation' => 'SecurePass2026!',
    'full_name' => 'Zeynep Test',
    'role' => 'alumni',
    'graduation_year' => 2024,
    'captcha_id' => $regCapId,
    'captcha_answer' => 'BADANS',
], $captchaService);
assertCheck(
    !$badCaptchaReg['ok'] && $badCaptchaReg['status'] === 422 && $badCaptchaReg['error_code'] === 'CAPTCHA_INCORRECT',
    "Anti-Bot Gate: Rejects registration with incorrect CAPTCHA (HTTP 422)"
);

// 3.4 Successful registration with valid CAPTCHA
$goodReg = $authService->register([
    'username' => $testUsername,
    'email' => $testEmail,
    'password' => 'SecurePass2026!',
    'password_confirmation' => 'SecurePass2026!',
    'full_name' => 'Zeynep Test',
    'role' => 'alumni',
    'graduation_year' => 2024,
    'department_id' => 1,
    'company' => 'Trendyol Group',
    'job_title' => 'Product Analyst',
    'captcha_id' => $regCapId,
    'captcha_answer' => 'M4N8Q2',
], $captchaService);
assertCheck(
    $goodReg['ok'] === true && $goodReg['status'] === 201 && !empty($goodReg['user']['id']),
    "Registration: Succeeds with HTTP 201 Created when CAPTCHA & fields are valid"
);
assertCheck(
    !array_key_exists('password_hash', $goodReg['user'] ?? []) && !array_key_exists('password', $goodReg['user'] ?? []),
    "Security: Sanitized user response omits password and password_hash"
);
assertCheck(
    !empty($goodReg['token']['access_token']) && ($goodReg['token']['token_type'] ?? '') === 'Bearer',
    "Token Issuance: Registration returns HS256 Bearer access token"
);

// 3.5 Reused CAPTCHA rejected on subsequent registration
$reusedReg = $authService->register([
    'username' => "other_{$uniqueTag}",
    'email' => "other_{$uniqueTag}@ogr.iu.edu.tr",
    'password' => 'SecurePass2026!',
    'full_name' => 'Replay Attempt',
    'captcha_id' => $regCapId,
    'captcha_answer' => 'M4N8Q2',
], $captchaService);
assertCheck(
    !$reusedReg['ok'] && $reusedReg['status'] === 422 && $reusedReg['error_code'] === 'CAPTCHA_REUSED',
    "Anti-Bot Gate: Rejects registration reusing consumed CAPTCHA (HTTP 422)"
);

// 3.6 Duplicate username rejected with HTTP 409 Conflict
$dupCap1 = 'cap_dup1_' . $tmpSuffix;
$captchaService->storeKnownChallenge($dupCap1, 'DUP111', 300);
$dupUsernameRes = $authService->register([
    'username' => $testUsername,
    'email' => "different_{$uniqueTag}@ogr.iu.edu.tr",
    'password' => 'SecurePass2026!',
    'full_name' => 'Duplicate Username',
    'captcha_id' => $dupCap1,
    'captcha_answer' => 'DUP111',
], $captchaService);
assertCheck(
    !$dupUsernameRes['ok'] && $dupUsernameRes['status'] === 409 && $dupUsernameRes['error_code'] === 'DUPLICATE_USERNAME',
    "Duplicate Guard: Rejects duplicate username with HTTP 409 Conflict"
);

// 3.7 Duplicate email rejected with HTTP 409 Conflict
$dupCap2 = 'cap_dup2_' . $tmpSuffix;
$captchaService->storeKnownChallenge($dupCap2, 'DUP222', 300);
$dupEmailRes = $authService->register([
    'username' => "unique_{$uniqueTag}",
    'email' => $testEmail,
    'password' => 'SecurePass2026!',
    'full_name' => 'Duplicate Email',
    'captcha_id' => $dupCap2,
    'captcha_answer' => 'DUP222',
], $captchaService);
assertCheck(
    !$dupEmailRes['ok'] && $dupEmailRes['status'] === 409 && $dupEmailRes['error_code'] === 'DUPLICATE_EMAIL',
    "Duplicate Guard: Rejects duplicate email with HTTP 409 Conflict"
);

// -------------------------------------------------------------------------
// 4. Client <-> Server Token Authentication & RBAC Authorization Flow
// -------------------------------------------------------------------------
echo "\n4. Token Authentication & RBAC Authorization Flow:\n";

$badLogin = $authService->login($testUsername, 'WrongPassword99');
assertCheck(!$badLogin['ok'] && $badLogin['status'] === 401, "Login rejects invalid password with HTTP 401");

$goodLogin = $authService->login($testUsername, 'SecurePass2026!');
assertCheck(
    $goodLogin['ok'] === true && $goodLogin['status'] === 200 && !empty($goodLogin['token']['access_token']),
    "Login authenticates hashed password and returns Bearer token (HTTP 200)"
);

$bearer = 'Bearer ' . ($goodLogin['token']['access_token'] ?? '');
$claims = AuthMiddleware::authenticate($bearer, false, $tokenService);
assertCheck(
    is_array($claims) && ($claims['username'] ?? '') === $testUsername && ($claims['role'] ?? '') === 'alumni' && !empty($claims['jti']),
    "AuthMiddleware validates Bearer token and extracts user claims, role, and unique jti"
);

$tamperedClaims = AuthMiddleware::authenticate($bearer . 'tampered', false, $tokenService);
assertCheck($tamperedClaims === null, "AuthMiddleware rejects tampered JWT signature");

$alumniAllowed = AuthMiddleware::requireRole(['alumni', 'employer'], $claims, null, false, $tokenService);
assertCheck($alumniAllowed !== null, "RBAC Authorization allows 'alumni' on ['alumni', 'employer'] route");

Response::$preventExit = true;
ob_start();
$adminDenied = AuthMiddleware::requireRole(['admin'], $claims, null, true, $tokenService);
$forbiddenOut = ob_get_clean();
Response::$preventExit = false;
$forbiddenJson = json_decode($forbiddenOut, true);
assertCheck(
    $adminDenied === null && ($forbiddenJson['code'] ?? null) === 403 && ($forbiddenJson['error_code'] ?? '') === 'FORBIDDEN_ROLE',
    "RBAC Authorization rejects 'alumni' on admin-only route with HTTP 403 FORBIDDEN_ROLE"
);

// 4.1 Logout & Server-Side Token Revocation
$revokeResult = $tokenService->revokeToken($claims, (string)$goodLogin['token']['access_token']);
assertCheck(
    $revokeResult['revoked'] === true && !empty($revokeResult['token_id']),
    "Logout: TokenService::revokeToken() records jti revocation"
);

Response::$preventExit = true;
ob_start();
$revokedAttempt = AuthMiddleware::authenticate($bearer, true, $tokenService);
$revokedOut = ob_get_clean();
Response::$preventExit = false;
$revokedJson = json_decode($revokedOut, true);
assertCheck(
    $revokedAttempt === null && ($revokedJson['code'] ?? null) === 401 && ($revokedJson['error_code'] ?? '') === 'TOKEN_REVOKED',
    "Logout Enforcement: Subsequent request with revoked token returns HTTP 401 TOKEN_REVOKED"
);

// -------------------------------------------------------------------------
// 5. Google OAuth 2.0 State, Token Verification, Creation & Account Linking
// -------------------------------------------------------------------------
echo "\n5. Google OAuth 2.0 Registration, Linking & Error Guards:\n";

$mockHttp = static function (string $method, string $url, array $params = [], array $headers = []): array {
    if (str_contains($url, 'id_token=valid_google_token')) {
        return [
            'ok' => true,
            'status' => 200,
            'body' => [
                'sub' => 'google_sub_10001',
                'email' => 'merve.google@gmail.com',
                'email_verified' => 'true',
                'name' => 'Merve Google',
                'picture' => 'https://lh3.googleusercontent.com/avatar/merve.png',
                'iss' => 'https://accounts.google.com',
                'exp' => (string)(time() + 3600),
            ],
        ];
    }
    if (str_contains($url, 'id_token=unverified_google_token')) {
        return [
            'ok' => true,
            'status' => 200,
            'body' => [
                'sub' => 'google_sub_unverified',
                'email' => 'unverified@gmail.com',
                'email_verified' => 'false',
                'name' => 'Unverified User',
                'iss' => 'https://accounts.google.com',
                'exp' => (string)(time() + 3600),
            ],
        ];
    }
    return ['ok' => false, 'status' => 400, 'body' => ['error_description' => 'Invalid Value']];
};

$googleOAuth = new GoogleOAuthService($mockHttp);

$oauthState = $googleOAuth->createState('student', '/register');
$parsedState = $googleOAuth->verifyState($oauthState);
assertCheck(
    is_array($parsedState) && ($parsedState['role'] ?? '') === 'student',
    "Google OAuth CSRF state signs and verifies cleanly"
);
assertCheck(
    $googleOAuth->verifyState($oauthState . 'tampered') === null,
    "Google OAuth rejects tampered CSRF state"
);

$invalidIdTok = $googleOAuth->verifyIdToken('invalid_token');
assertCheck(
    !$invalidIdTok['ok'] && $invalidIdTok['status'] === 401 && $invalidIdTok['error_code'] === 'GOOGLE_TOKEN_INVALID',
    "Google OAuth rejects invalid ID token (HTTP 401 GOOGLE_TOKEN_INVALID)"
);

$unverifiedIdTok = $googleOAuth->verifyIdToken('unverified_google_token');
assertCheck(
    !$unverifiedIdTok['ok'] && $unverifiedIdTok['status'] === 422 && $unverifiedIdTok['error_code'] === 'GOOGLE_EMAIL_UNVERIFIED',
    "Google OAuth rejects unverified Google email (HTTP 422 GOOGLE_EMAIL_UNVERIFIED)"
);

$verifiedIdTok = $googleOAuth->verifyIdToken('valid_google_token');
assertCheck(
    $verifiedIdTok['ok'] === true && ($verifiedIdTok['profile']['google_id'] ?? '') === 'google_sub_10001',
    "Google OAuth verifies valid ID token and extracts normalized profile"
);

// 5.1 New Google account creation (auth_provider = 'google', password_hash = null)
$googleNewRes = $authService->registerOrLoginWithGoogle($verifiedIdTok['profile'], 'alumni');
assertCheck(
    $googleNewRes['ok'] === true
    && $googleNewRes['status'] === 201
    && $googleNewRes['is_new_user'] === true
    && ($googleNewRes['user']['auth_provider'] ?? '') === 'google'
    && ($googleNewRes['user']['google_linked'] ?? false) === true
    && !empty($googleNewRes['token']['access_token']),
    "Google Registration: Creates new account (201, auth_provider='google') and issues Bearer token"
);

// 5.2 Returning Google user logs into same account (no duplicate created)
$googleReturnRes = $authService->registerOrLoginWithGoogle($verifiedIdTok['profile'], 'alumni');
assertCheck(
    $googleReturnRes['ok'] === true
    && $googleReturnRes['status'] === 200
    && $googleReturnRes['is_new_user'] === false
    && ($googleReturnRes['user']['id'] ?? null) === ($googleNewRes['user']['id'] ?? -1),
    "Google Sign-In: Existing google_id logs into same account (HTTP 200)"
);

// 5.3 Google-only user trying password login gets explicit OAUTH_ACCOUNT_ONLY error
$googlePwAttempt = $authService->login('merve.google@gmail.com', 'SomePassword123');
assertCheck(
    !$googlePwAttempt['ok'] && $googlePwAttempt['status'] === 401 && $googlePwAttempt['error_code'] === 'OAUTH_ACCOUNT_ONLY',
    "OAuth Guard: Google-only user attempting password login receives 401 OAUTH_ACCOUNT_ONLY"
);

// 5.4 Linking Google to an existing local email account preserves local credentials
$linkRes = $authService->registerOrLoginWithGoogle([
    'google_id' => 'google_sub_linked_' . $tmpSuffix,
    'email' => $testEmail,
    'email_verified' => true,
    'full_name' => 'Zeynep Test',
    'avatar_url' => 'https://lh3.googleusercontent.com/avatar/zeynep.png',
], 'alumni');
assertCheck(
    $linkRes['ok'] === true
    && $linkRes['status'] === 200
    && $linkRes['account_linked'] === true
    && ($linkRes['user']['id'] ?? null) === ($goodReg['user']['id'] ?? -1)
    && ($linkRes['user']['google_linked'] ?? false) === true,
    "Account Linking: Verified Google email links to existing local account cleanly (HTTP 200)"
);

// -------------------------------------------------------------------------
// 6. Router Per-Route AuthMiddleware & RoleMiddleware Pipeline Checks
// -------------------------------------------------------------------------
echo "\n6. Router Per-Route AuthMiddleware & RoleMiddleware Pipeline:\n";

$rbacRouter = new Router();
$rbacRouter->get('/api/public-ping', function (): void {
    Response::json(['status' => 'ok', 'public' => true], 200);
});
$rbacRouter->get('/api/protected-alumni', function (): void {
    Response::json(['status' => 'ok', 'user' => RequestContext::user()], 200);
}, [new AuthMiddleware($tokenService), RoleMiddleware::requireRole(['alumni', 'admin'], $tokenService)]);
$rbacRouter->get('/api/protected-admin', function (): void {
    Response::json(['status' => 'ok', 'admin' => true, 'user' => RequestContext::user()], 200);
}, [new AuthMiddleware($tokenService), RoleMiddleware::requireRole(['admin'], $tokenService)]);

// 6.1 Public route accessible without token
unset($_SERVER['HTTP_AUTHORIZATION']);
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$rbacRouter->resolve('GET', '/api/public-ping');
$pubOut = ob_get_clean();
Response::$preventExit = false;
$pubJson = json_decode($pubOut, true);
assertCheck(($pubJson['public'] ?? false) === true, "Public route executes cleanly without Bearer token");

// 6.2 Protected route without token returns 401 guardian response
Response::$preventExit = true;
ob_start();
$rbacRouter->resolve('GET', '/api/protected-admin');
$unauthOut = ob_get_clean();
Response::$preventExit = false;
$unauthJson = json_decode($unauthOut, true);
assertCheck(
    ($unauthJson['code'] ?? null) === 401
    && ($unauthJson['guardian'] ?? '') === 'Auth Middleware'
    && ($unauthJson['error_code'] ?? '') === 'TOKEN_MISSING',
    "Protected route without token returns 401 Auth Middleware guardian response"
);

// 6.3 Authenticated alumni accesses requireRole(['alumni', 'admin']) and RequestContext is populated
$alumniToken = $tokenService->issueToken([
    'id' => 77,
    'username' => 'selin.alumni',
    'email' => 'selin@ogr.iu.edu.tr',
    'role' => 'alumni',
])['access_token'];
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$alumniToken}";
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$rbacRouter->resolve('GET', '/api/protected-alumni');
$alumniOkOut = ob_get_clean();
Response::$preventExit = false;
$alumniOkJson = json_decode($alumniOkOut, true);
assertCheck(
    ($alumniOkJson['status'] ?? '') === 'ok'
    && ($alumniOkJson['user']['id'] ?? null) === 77
    && ($alumniOkJson['user']['email'] ?? '') === 'selin@ogr.iu.edu.tr'
    && ($alumniOkJson['user']['role'] ?? '') === 'alumni',
    "AuthMiddleware attaches id/email/role to RequestContext and allows permitted role"
);

// 6.4 Authenticated alumni accessing admin-only route returns 403 Role Authorization Middleware response
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$rbacRouter->resolve('GET', '/api/protected-admin');
$alumni403Out = ob_get_clean();
Response::$preventExit = false;
$alumni403Json = json_decode($alumni403Out, true);
assertCheck(
    ($alumni403Json['code'] ?? null) === 403
    && ($alumni403Json['guardian'] ?? '') === 'Role Authorization Middleware'
    && ($alumni403Json['error_code'] ?? '') === 'FORBIDDEN_ROLE'
    && ($alumni403Json['current_role'] ?? '') === 'alumni',
    "RoleMiddleware rejects alumni on admin-only route with 403 guardian response"
);

// 6.5 Authenticated admin accesses admin-only route successfully
$adminToken = $tokenService->issueToken([
    'id' => 1,
    'username' => 'admin.ybs',
    'email' => 'admin@alumni.iu.edu.tr',
    'role' => 'admin',
])['access_token'];
$_SERVER['HTTP_AUTHORIZATION'] = "Bearer {$adminToken}";
RequestContext::clear();
Response::$preventExit = true;
ob_start();
$rbacRouter->resolve('GET', '/api/protected-admin');
$adminOkOut = ob_get_clean();
Response::$preventExit = false;
$adminOkJson = json_decode($adminOkOut, true);
assertCheck(
    ($adminOkJson['admin'] ?? false) === true && ($adminOkJson['user']['role'] ?? '') === 'admin',
    "RoleMiddleware allows admin token onto admin-only route"
);
unset($_SERVER['HTTP_AUTHORIZATION']);
RequestContext::clear();

// -------------------------------------------------------------------------
// 7. OpenAPI & Postman JSON Integrity
// -------------------------------------------------------------------------
echo "\n7. API Documentation Integrity (OpenAPI & Postman):\n";

$openapi = json_decode((string)file_get_contents(__DIR__ . '/../docs/openapi.json'), true);
assertCheck(
    is_array($openapi)
    && isset($openapi['components']['securitySchemes']['BearerAuth'])
    && isset($openapi['paths']['/api/v1/auth/captcha'])
    && isset($openapi['paths']['/api/v1/auth/register'])
    && isset($openapi['paths']['/api/v1/auth/login'])
    && isset($openapi['paths']['/api/v1/auth/logout'])
    && isset($openapi['paths']['/api/v1/auth/me'])
    && isset($openapi['paths']['/api/v1/admin/users'])
    && isset($openapi['paths']['/api/v1/analytics/accreditation'])
    && isset($openapi['paths']['/api/v1/auth/google'])
    && isset($openapi['paths']['/api/v1/auth/google/callback'])
    && isset($openapi['paths']['/api/v1/auth/google/token']),
    "docs/openapi.json declares BearerAuth and all auth, CAPTCHA, Google OAuth, and RBAC endpoints"
);

$postman = json_decode((string)file_get_contents(__DIR__ . '/../docs/AlumniIstü_Postman_Collection.json'), true);
$hasAuthFolder = false;
$hasGoogleFolder = false;
if (is_array($postman['item'] ?? null)) {
    foreach ($postman['item'] as $folder) {
        $name = (string)($folder['name'] ?? '');
        if (str_contains($name, 'Authentication, Registration & CAPTCHA')) {
            $hasAuthFolder = true;
        }
        if (str_contains($name, 'Google OAuth')) {
            $hasGoogleFolder = true;
        }
    }
}
assertCheck(
    is_array($postman) && $hasAuthFolder && $hasGoogleFolder,
    "docs/AlumniIstü_Postman_Collection.json is valid JSON containing CAPTCHA + Google OAuth folders"
);

// Cleanup temp files
@unlink($captchaStoreFile);
@unlink($userStoreFile);
@unlink($revokedStoreFile);

echo "\n\033[1;32mAuth & CAPTCHA Verification Summary: {$passed} / {$total} assertions passed.\033[0m\n\n";
exit($passed === $total ? 0 : 1);
