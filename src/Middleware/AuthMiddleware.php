<?php
declare(strict_types=1);

namespace AlumniIstü\Middleware;

use AlumniIstü\Core\RequestContext;
use AlumniIstü\Core\Response;
use AlumniIstü\Services\TokenService;

/**
 * AuthMiddleware (Authentication Middleware)
 *
 * Extracts Bearer token from Authorization header (or auth cookie/session if present),
 * verifies JWT signature, expiration, and server-side revocation, attaches the authenticated
 * user (`id`, `username`, `email`, `role`) to RequestContext, and emits structured HTTP 401
 * responses consistent with AlumniIstü's Guardian Middleware format.
 */
class AuthMiddleware implements MiddlewareInterface {
    private ?TokenService $tokenService;

    public function __construct(?TokenService $tokenService = null) {
        $this->tokenService = $tokenService;
    }

    /**
     * Standard middleware pipeline handler.
     */
    public function handle(string $method, string $uri, callable $next): mixed {
        $claims = self::authenticate(null, true, $this->tokenService, $method, $uri);
        if ($claims === null) {
            return false;
        }
        return $next();
    }

    /**
     * Validate Bearer access token (signature, expiry, and revocation), attach user to RequestContext,
     * and return decoded claims. Emits HTTP 401 Unauthorized on failure when $sendError is true.
     */
    public static function authenticate(
        ?string $authorizationHeader = null,
        bool $sendError = true,
        ?TokenService $tokenService = null,
        ?string $method = null,
        ?string $uri = null
    ): ?array {
        $token = TokenService::extractBearerToken($authorizationHeader);
        if (($token === null || $token === '') && $authorizationHeader === null && isset($_SESSION['auth_token'])) {
            $token = trim((string)$_SESSION['auth_token']);
        }

        $reqMethod = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $reqPath = parse_url($uri ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $normalizedPath = preg_replace('#^/alumni#i', '', $reqPath) ?: '/';
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isBrowserHtml = !str_starts_with($normalizedPath, '/api/')
            && str_contains($accept, 'text/html')
            && !str_contains($accept, 'application/json');

        if ($token === null || $token === '') {
            RequestContext::clear();
            if ($sendError) {
                if ($isBrowserHtml) {
                    $query = http_build_query([
                        'auth_error' => 'TOKEN_MISSING',
                        'next' => $reqPath,
                    ]);
                    Response::redirect("/login?{$query}");
                    return null;
                }
                Response::json([
                    'status' => 'error',
                    'code' => 401,
                    'guardian' => 'Auth Middleware',
                    'error' => 'Unauthorized',
                    'error_code' => 'TOKEN_MISSING',
                    'message' => 'Authentication required. Provide an Authorization: Bearer <token> header.',
                    'reason' => "Access to '{$reqMethod} {$reqPath}' requires a valid Bearer access token.",
                    'method' => $reqMethod,
                    'path' => $reqPath,
                    'timestamp' => date('c'),
                ], 401);
            }
            return null;
        }

        $service = $tokenService ?? new TokenService();
        $inspection = $service->inspectToken($token);
        if (!$inspection['valid'] || !is_array($inspection['claims'])) {
            RequestContext::clear();
            if ($sendError) {
                $reasonCode = (string)($inspection['reason'] ?? 'TOKEN_INVALID_OR_EXPIRED');
                $message = $reasonCode === 'TOKEN_REVOKED'
                    ? 'Access token has been revoked via logout. Please log in again.'
                    : 'Access token is invalid or has expired. Please authenticate again.';

                if ($isBrowserHtml) {
                    $query = http_build_query([
                        'auth_error' => $reasonCode,
                        'next' => $reqPath,
                    ]);
                    Response::redirect("/login?{$query}");
                    return null;
                }

                Response::json([
                    'status' => 'error',
                    'code' => 401,
                    'guardian' => 'Auth Middleware',
                    'error' => 'Unauthorized',
                    'error_code' => $reasonCode,
                    'message' => $message,
                    'reason' => $message,
                    'method' => $reqMethod,
                    'path' => $reqPath,
                    'timestamp' => date('c'),
                ], 401);
            }
            return null;
        }

        $claims = $inspection['claims'];
        RequestContext::setUser([
            'id' => (int)($claims['sub'] ?? 0),
            'sub' => (int)($claims['sub'] ?? 0),
            'username' => (string)($claims['username'] ?? ''),
            'email' => (string)($claims['email'] ?? ''),
            'role' => (string)($claims['role'] ?? ''),
            'jti' => $claims['jti'] ?? null,
            'iat' => $claims['iat'] ?? null,
            'exp' => $claims['exp'] ?? null,
        ]);

        return $claims;
    }

    /**
     * Backward-compatible static helper delegating role checks to RoleMiddleware.
     *
     * @param string[] $allowedRoles
     */
    public static function requireRole(
        array $allowedRoles,
        ?array $claims = null,
        ?string $authorizationHeader = null,
        bool $sendError = true,
        ?TokenService $tokenService = null
    ): ?array {
        return RoleMiddleware::check(
            $allowedRoles,
            $claims,
            $authorizationHeader,
            $sendError,
            $tokenService
        );
    }
}
