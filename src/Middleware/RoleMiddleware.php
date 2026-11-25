<?php
declare(strict_types=1);

namespace AlumniIstü\Middleware;

use AlumniIstü\Core\RequestContext;
use AlumniIstü\Core\Response;
use AlumniIstü\Services\TokenService;

/**
 * RoleMiddleware (Role Authorization Middleware)
 *
 * Enforces Role-Based Access Control (RBAC) on protected routes:
 * - Returns HTTP 401 Unauthorized when the request is unauthenticated.
 * - Returns HTTP 403 Forbidden when the authenticated user's role is not permitted.
 * - Uses AlumniIstü's consistent Guardian error-handling JSON structure.
 *
 * Usage:
 *   $router->get('/api/v1/admin/users', [...], [new AuthMiddleware(), RoleMiddleware::requireRole(['admin'])]);
 */
class RoleMiddleware implements MiddlewareInterface {
    /** @var string[] */
    private array $allowedRoles;
    private ?TokenService $tokenService;

    /**
     * @param string[] $allowedRoles
     */
    public function __construct(array $allowedRoles, ?TokenService $tokenService = null) {
        $this->allowedRoles = array_values(array_map(
            static fn(string $r): string => strtolower(trim($r)),
            $allowedRoles
        ));
        $this->tokenService = $tokenService;
    }

    /**
     * Expressive factory: RoleMiddleware::requireRole(['admin']) or RoleMiddleware::requireRole(['alumni', 'admin'])
     *
     * @param string[] $allowedRoles
     */
    public static function requireRole(array $allowedRoles, ?TokenService $tokenService = null): self {
        return new self($allowedRoles, $tokenService);
    }

    /**
     * Standard middleware pipeline handler.
     */
    public function handle(string $method, string $uri, callable $next): mixed {
        $user = RequestContext::user();
        if ($user === null) {
            // Automatically run authentication if AuthMiddleware was not explicitly queued before RoleMiddleware
            $claims = AuthMiddleware::authenticate(null, true, $this->tokenService, $method, $uri);
            if ($claims === null) {
                return false;
            }
            $user = RequestContext::user() ?? $claims;
        }

        $authorized = self::check($this->allowedRoles, $user, null, true, $this->tokenService, $method, $uri);
        if ($authorized === null) {
            return false;
        }

        return $next();
    }

    /**
     * Imperative role verification helper.
     *
     * @param string[] $allowedRoles
     */
    public static function check(
        array $allowedRoles,
        ?array $userOrClaims = null,
        ?string $authorizationHeader = null,
        bool $sendError = true,
        ?TokenService $tokenService = null,
        ?string $method = null,
        ?string $uri = null
    ): ?array {
        $reqMethod = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $reqPath = parse_url($uri ?? ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

        $verified = $userOrClaims ?? RequestContext::user();
        if ($verified === null) {
            $verified = AuthMiddleware::authenticate($authorizationHeader, $sendError, $tokenService, $reqMethod, $reqPath);
            if ($verified === null) {
                return null;
            }
        }

        $normalizedAllowed = array_values(array_map(
            static fn(string $r): string => strtolower(trim($r)),
            $allowedRoles
        ));
        $currentRole = strtolower(trim((string)($verified['role'] ?? '')));

        if ($currentRole === '' || !in_array($currentRole, $normalizedAllowed, true)) {
            if ($sendError) {
                $allowedList = implode(', ', $normalizedAllowed);
                $reason = "Endpoint '{$reqMethod} {$reqPath}' requires one of roles [{$allowedList}], but authenticated user has role '{$currentRole}'.";
                $normalizedPath = preg_replace('#^/alumni#i', '', $reqPath) ?: '/';
                $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
                $isBrowserHtml = !str_starts_with($normalizedPath, '/api/')
                    && str_contains($accept, 'text/html')
                    && !str_contains($accept, 'application/json');

                if ($isBrowserHtml) {
                    $tpl = dirname(__DIR__, 2) . '/templates/403.html';
                    if (file_exists($tpl)) {
                        Response::html($tpl, [
                            'REASON' => htmlspecialchars($reason),
                            'METHOD' => htmlspecialchars($reqMethod),
                            'PATH' => htmlspecialchars($reqPath),
                            'CURRENT_ROLE' => htmlspecialchars($currentRole ?: 'none'),
                            'REQUIRED_ROLES' => htmlspecialchars($allowedList),
                        ], 403);
                        return null;
                    }
                }

                Response::json([
                    'status' => 'error',
                    'code' => 403,
                    'guardian' => 'Role Authorization Middleware',
                    'error' => 'Forbidden',
                    'error_code' => 'FORBIDDEN_ROLE',
                    'message' => "Access denied. Role '{$currentRole}' is not authorized for this resource.",
                    'reason' => $reason,
                    'current_role' => $currentRole,
                    'required_roles' => $normalizedAllowed,
                    'method' => $reqMethod,
                    'path' => $reqPath,
                    'timestamp' => date('c'),
                ], 403);
            }
            return null;
        }

        return $verified;
    }
}
