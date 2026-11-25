<?php
declare(strict_types=1);

namespace AlumniIstü\Controllers;

use AlumniIstü\Core\RequestContext;
use AlumniIstü\Core\Response;
use AlumniIstü\Domain\Enums\UserRole;
use AlumniIstü\Middleware\AuthMiddleware;
use AlumniIstü\Services\AuthService;
use AlumniIstü\Services\CaptchaService;
use AlumniIstü\Services\GoogleOAuthService;
use AlumniIstü\Services\TokenService;

class AuthApiController {
    private AuthService $authService;
    private CaptchaService $captchaService;
    private GoogleOAuthService $googleOAuthService;
    private TokenService $tokenService;

    public function __construct(
        ?AuthService $authService = null,
        ?CaptchaService $captchaService = null,
        ?GoogleOAuthService $googleOAuthService = null,
        ?TokenService $tokenService = null
    ) {
        $this->tokenService = $tokenService ?? new TokenService();
        $this->authService = $authService ?? new AuthService(null, $this->tokenService);
        $this->captchaService = $captchaService ?? new CaptchaService();
        $this->googleOAuthService = $googleOAuthService ?? new GoogleOAuthService();
    }

    /**
     * GET /api/v1/auth/providers
     * Public configuration discovery for Register UI (CAPTCHA driver + Google OAuth readiness).
     */
    public function providers(): void {
        Response::json([
            'status' => 'success',
            'data' => [
                'captcha' => [
                    'driver' => $this->captchaService->getDriver(),
                    'default_mode' => $this->captchaService->getDefaultMode(),
                    'ttl_seconds' => $this->captchaService->getTtlSeconds(),
                ],
                'google' => $this->googleOAuthService->getPublicConfig(),
                'allowed_public_roles' => UserRole::selfRegistrationRoles(),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/auth/captcha
     * Generate a short-lived, single-use SVG or Math CAPTCHA challenge.
     */
    public function captcha(): void {
        $mode = isset($_GET['mode']) ? (string)$_GET['mode'] : null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $challenge = $this->captchaService->generate($mode, $ip);

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        Response::json([
            'status' => 'success',
            'data' => $challenge,
        ], 200);
    }

    /**
     * POST /api/v1/auth/register
     * Register a new AlumniIstü user account with mandatory server-side CAPTCHA verification.
     */
    public function register(): void {
        $input = $this->getRequestPayload();
        $result = $this->authService->register($input, $this->captchaService);

        if (!$result['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $result['status'],
                'error_code' => $result['error_code'] ?? 'REGISTRATION_FAILED',
                'message' => $result['message'] ?? 'Registration failed.',
                'errors' => $result['errors'] ?? [],
            ], $result['status']);
            return;
        }

        $this->setAuthCookie(
            (string)$result['token']['access_token'],
            (int)$result['token']['expires_in']
        );

        Response::json([
            'status' => 'success',
            'message' => 'Account registered successfully.',
            'data' => [
                'user' => $result['user'],
                'access_token' => $result['token']['access_token'],
                'token_type' => $result['token']['token_type'],
                'expires_in' => $result['token']['expires_in'],
                'expires_at' => $result['token']['expires_at'],
            ],
        ], 201);
    }

    /**
     * POST /api/v1/auth/login
     * Authenticate username/email + password and issue an HS256 Bearer access token.
     */
    public function login(): void {
        $input = $this->getRequestPayload();
        $identifier = (string)($input['username'] ?? $input['email'] ?? '');
        $password = (string)($input['password'] ?? '');

        $result = $this->authService->login($identifier, $password);

        if (!$result['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $result['status'],
                'error_code' => $result['error_code'] ?? 'AUTH_FAILED',
                'message' => $result['message'] ?? 'Authentication failed.',
            ], $result['status']);
            return;
        }

        $this->setAuthCookie(
            (string)$result['token']['access_token'],
            (int)$result['token']['expires_in']
        );

        Response::json([
            'status' => 'success',
            'message' => 'Authentication successful.',
            'data' => [
                'access_token' => $result['token']['access_token'],
                'token_type' => $result['token']['token_type'],
                'expires_in' => $result['token']['expires_in'],
                'expires_at' => $result['token']['expires_at'],
                'user' => $result['user'],
            ],
        ], 200);
    }

    /**
     * POST /api/v1/auth/logout (alias: POST /api/auth/logout)
     * Revoke the current Bearer access token server-side and clear any auth cookie cleanly.
     */
    public function logout(): void {
        $claims = RequestContext::user() ?? AuthMiddleware::authenticate(null, true, $this->tokenService);
        if ($claims === null) {
            return;
        }

        $rawToken = TokenService::extractBearerToken() ?? '';
        $revocation = $this->tokenService->revokeToken($claims, $rawToken);
        RequestContext::clear();

        // Clear optional cookie if one was set by a browser client
        if (!headers_sent()) {
            setcookie('alumni_istu_token', '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        Response::json([
            'status' => 'success',
            'message' => 'Logged out successfully. Access token has been revoked.',
            'data' => [
                'revoked' => $revocation['revoked'],
                'token_id' => $revocation['token_id'],
                'revoked_at' => $revocation['revoked_at'],
                'user_id' => (int)($claims['sub'] ?? $claims['id'] ?? 0),
                'username' => (string)($claims['username'] ?? ''),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/auth/me
     * Protected endpoint validating Authorization: Bearer <token> and returning current user & RBAC permissions.
     */
    public function me(): void {
        $claims = RequestContext::user() ?? AuthMiddleware::authenticate(null, true, $this->tokenService);
        if ($claims === null) {
            return;
        }

        $userId = (int)($claims['sub'] ?? $claims['id'] ?? 0);
        $user = $this->authService->findById($userId);

        if ($user === null) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'USER_NOT_FOUND',
                'message' => 'Authenticated user account no longer exists.',
            ], 404);
            return;
        }

        if (isset($user['is_active']) && !$user['is_active']) {
            Response::json([
                'status' => 'error',
                'code' => 403,
                'error_code' => 'ACCOUNT_INACTIVE',
                'message' => 'This account has been deactivated. Navigation has been reset to guest state.',
            ], 403);
            return;
        }

        $role = (string)($user['role'] ?? 'alumni');
        Response::json([
            'status' => 'success',
            'data' => [
                'authenticated' => true,
                'role' => $role,
                'is_admin' => $role === UserRole::ADMIN->value,
                'user' => $user,
                'token_claims' => [
                    'jti' => $claims['jti'] ?? null,
                    'sub' => $claims['sub'] ?? $claims['id'] ?? null,
                    'username' => $claims['username'] ?? null,
                    'email' => $claims['email'] ?? null,
                    'role' => $claims['role'] ?? null,
                    'iat' => $claims['iat'] ?? null,
                    'exp' => $claims['exp'] ?? null,
                ],
            ],
        ], 200);
    }

    /**
     * GET /api/v1/admin/users (Protected: requireRole(['admin']))
     * List all registered platform accounts and role counts for administrative governance.
     */
    public function listUsers(): void {
        $users = $this->authService->listUsers();
        $roleCounts = [];
        foreach ($users as $u) {
            $r = (string)($u['role'] ?? 'alumni');
            $roleCounts[$r] = ($roleCounts[$r] ?? 0) + 1;
        }

        Response::json([
            'status' => 'success',
            'requested_by' => RequestContext::user(),
            'count' => count($users),
            'role_distribution' => $roleCounts,
            'data' => $users,
        ], 200);
    }

    /**
     * PATCH /api/v1/admin/users/{id}/role (Protected: requireRole(['admin']))
     * Update a user's RBAC role.
     */
    public function updateUserRole(string $id): void {
        $input = $this->getRequestPayload();
        $newRole = (string)($input['role'] ?? '');
        $result = $this->authService->updateUserRole((int)$id, $newRole);

        if (!$result['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $result['status'],
                'error_code' => $result['error_code'] ?? 'ROLE_UPDATE_FAILED',
                'message' => $result['message'] ?? 'Failed to update user role.',
            ], $result['status']);
            return;
        }

        Response::json([
            'status' => 'success',
            'message' => "User #{$id} role updated successfully.",
            'updated_by' => RequestContext::user(),
            'data' => $result['user'],
        ], 200);
    }

    /**
     * GET /api/v1/auth/google
     * Initiate Google OAuth 2.0 redirect flow (or return JSON authorize_url when ?format=json / Accept: application/json).
     */
    public function googleRedirect(): void {
        $role = strtolower(trim((string)($_GET['role'] ?? UserRole::ALUMNI->value)));
        if (!in_array($role, UserRole::selfRegistrationRoles(), true)) {
            $role = UserRole::ALUMNI->value;
        }
        $returnPath = (string)($_GET['return_to'] ?? '/register');
        $wantsJson = $this->wantsJsonResponse();

        $built = $this->googleOAuthService->buildAuthorizationUrl($role, $returnPath);
        if (!$built['ok']) {
            if ($wantsJson) {
                Response::json([
                    'status' => 'error',
                    'code' => $built['status'],
                    'error_code' => $built['error_code'],
                    'message' => $built['message'],
                ], $built['status']);
                return;
            }
            $this->redirectWithError(
                (string)$built['error_code'],
                (string)$built['message'],
                $returnPath
            );
            return;
        }

        if ($wantsJson) {
            Response::json([
                'status' => 'success',
                'data' => [
                    'authorize_url' => $built['authorize_url'],
                    'state' => $built['state'],
                ],
            ], 200);
            return;
        }

        Response::redirect((string)$built['authorize_url']);
    }

    /**
     * GET /api/v1/auth/google/callback
     * Handle Google OAuth 2.0 callback: validate state, exchange code, link/create user, issue Bearer token.
     */
    public function googleCallback(): void {
        $wantsJson = $this->wantsJsonResponse();

        // Attempt to read return path from state early (for error redirects)
        $statePayload = $this->googleOAuthService->verifyState(isset($_GET['state']) ? (string)$_GET['state'] : null);
        $returnPath = is_array($statePayload) ? (string)($statePayload['return_path'] ?? '/register') : '/register';

        // 1. Handle user cancellation or Google OAuth error response
        if (isset($_GET['error']) && trim((string)$_GET['error']) !== '') {
            $oauthErr = trim((string)$_GET['error']);
            $errCode = $oauthErr === 'access_denied' ? 'OAUTH_CONSENT_CANCELLED' : 'OAUTH_PROVIDER_ERROR';
            $message = $oauthErr === 'access_denied'
                ? 'Google sign-in was cancelled before granting consent.'
                : 'Google OAuth returned an error: ' . $oauthErr;

            if ($wantsJson) {
                Response::json([
                    'status' => 'error',
                    'code' => 400,
                    'error_code' => $errCode,
                    'message' => $message,
                ], 400);
                return;
            }
            $this->redirectWithError($errCode, $message, $returnPath);
            return;
        }

        // 2. Verify CSRF state token
        if ($statePayload === null) {
            $message = 'Invalid or expired OAuth state parameter. Please try Continue with Google again.';
            if ($wantsJson) {
                Response::json([
                    'status' => 'error',
                    'code' => 400,
                    'error_code' => 'OAUTH_INVALID_STATE',
                    'message' => $message,
                ], 400);
                return;
            }
            $this->redirectWithError('OAUTH_INVALID_STATE', $message, $returnPath);
            return;
        }

        // 3. Exchange authorization code for verified Google profile
        $code = isset($_GET['code']) ? (string)$_GET['code'] : '';
        $profileResult = $this->googleOAuthService->exchangeCodeForProfile($code);
        if (!$profileResult['ok']) {
            if ($wantsJson) {
                Response::json([
                    'status' => 'error',
                    'code' => $profileResult['status'],
                    'error_code' => $profileResult['error_code'],
                    'message' => $profileResult['message'],
                ], $profileResult['status']);
                return;
            }
            $this->redirectWithError(
                (string)($profileResult['error_code'] ?? 'OAUTH_FAILED'),
                (string)($profileResult['message'] ?? 'Google authentication failed.'),
                $returnPath
            );
            return;
        }

        // 4. Register, link, or log in the user and issue our application JWT
        $requestedRole = (string)($statePayload['role'] ?? UserRole::ALUMNI->value);
        $authResult = $this->authService->registerOrLoginWithGoogle($profileResult['profile'], $requestedRole);

        if (!$authResult['ok']) {
            if ($wantsJson) {
                Response::json([
                    'status' => 'error',
                    'code' => $authResult['status'],
                    'error_code' => $authResult['error_code'],
                    'message' => $authResult['message'],
                ], $authResult['status']);
                return;
            }
            $this->redirectWithError(
                (string)($authResult['error_code'] ?? 'GOOGLE_AUTH_FAILED'),
                (string)($authResult['message'] ?? 'Could not complete Google registration.'),
                $returnPath
            );
            return;
        }

        $this->setAuthCookie(
            (string)$authResult['token']['access_token'],
            (int)$authResult['token']['expires_in']
        );

        if ($wantsJson) {
            Response::json([
                'status' => 'success',
                'message' => !empty($authResult['is_new_user'])
                    ? 'Account registered with Google successfully.'
                    : (!empty($authResult['account_linked'])
                        ? 'Google account linked and signed in successfully.'
                        : 'Signed in with Google successfully.'),
                'data' => [
                    'is_new_user' => (bool)$authResult['is_new_user'],
                    'account_linked' => (bool)$authResult['account_linked'],
                    'user' => $authResult['user'],
                    'access_token' => $authResult['token']['access_token'],
                    'token_type' => $authResult['token']['token_type'],
                    'expires_in' => $authResult['token']['expires_in'],
                    'expires_at' => $authResult['token']['expires_at'],
                ],
            ], $authResult['status']);
            return;
        }

        // Browser flow: return token via URL fragment (#) so it is never sent to server logs/Referer headers
        $returnPath = (string)($statePayload['return_path'] ?? '/register');
        $fragment = http_build_query([
            'oauth_provider' => 'google',
            'oauth_token' => $authResult['token']['access_token'],
            'is_new_user' => !empty($authResult['is_new_user']) ? '1' : '0',
            'account_linked' => !empty($authResult['account_linked']) ? '1' : '0',
        ]);
        Response::redirect("{$returnPath}#{$fragment}");
    }

    /**
     * POST /api/v1/auth/google/token
     * Verify a Google ID token directly (for Swagger UI, Postman, SPA, or mobile clients)
     * and issue the application's HS256 Bearer access token.
     */
    public function googleToken(): void {
        $input = $this->getRequestPayload();
        $idToken = (string)($input['id_token'] ?? $input['credential'] ?? '');
        $requestedRole = isset($input['role']) ? (string)$input['role'] : null;

        $verified = $this->googleOAuthService->verifyIdToken($idToken);
        if (!$verified['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $verified['status'],
                'error_code' => $verified['error_code'],
                'message' => $verified['message'],
            ], $verified['status']);
            return;
        }

        $authResult = $this->authService->registerOrLoginWithGoogle($verified['profile'], $requestedRole);
        if (!$authResult['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $authResult['status'],
                'error_code' => $authResult['error_code'],
                'message' => $authResult['message'],
            ], $authResult['status']);
            return;
        }

        $this->setAuthCookie(
            (string)$authResult['token']['access_token'],
            (int)$authResult['token']['expires_in']
        );

        Response::json([
            'status' => 'success',
            'message' => !empty($authResult['is_new_user'])
                ? 'Account registered with Google successfully.'
                : (!empty($authResult['account_linked'])
                    ? 'Google account linked and signed in successfully.'
                    : 'Signed in with Google successfully.'),
            'data' => [
                'is_new_user' => (bool)$authResult['is_new_user'],
                'account_linked' => (bool)$authResult['account_linked'],
                'user' => $authResult['user'],
                'access_token' => $authResult['token']['access_token'],
                'token_type' => $authResult['token']['token_type'],
                'expires_in' => $authResult['token']['expires_in'],
                'expires_at' => $authResult['token']['expires_at'],
            ],
        ], $authResult['status']);
    }

    private function setAuthCookie(string $accessToken, int $expiresIn): void {
        if (!headers_sent() && $accessToken !== '') {
            setcookie('alumni_istu_token', $accessToken, [
                'expires' => time() + max(300, $expiresIn),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * GET /api/v1/departments
     * List academic departments for registration dropdowns.
     */
    public function departments(): void {
        $departments = $this->authService->getDepartments();
        Response::json([
            'status' => 'success',
            'count' => count($departments),
            'data' => $departments,
        ], 200);
    }

    private function wantsJsonResponse(): bool {
        if (isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json') {
            return true;
        }
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    private function redirectWithError(string $errorCode, string $message, string $returnPath = '/register'): void {
        $safePath = str_starts_with($returnPath, '/') ? $returnPath : '/register';
        $query = http_build_query([
            'oauth_error' => $errorCode,
            'oauth_error_message' => $message,
        ]);
        Response::redirect("{$safePath}?{$query}");
    }

    private function getRequestPayload(): array {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }
}
