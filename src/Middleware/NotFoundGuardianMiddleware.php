<?php
declare(strict_types=1);

namespace AlumniIstü\Middleware;

use AlumniIstü\Core\Response;

/**
 * NotFoundGuardianMiddleware (404 Guardian)
 * 
 * Implements Whiteboard Item 4 & Task 3:
 * "404 Guardian Middleware: return 404 errors with a reason."
 * 
 * Intercepts missing routes, invalid endpoints, and non-existent entity lookups,
 * ensuring a consistent 404 response with an explicit, human-readable reason.
 */
class NotFoundGuardianMiddleware implements MiddlewareInterface {
    /**
     * Standard middleware handler in request pipeline.
     */
    public function handle(string $method, string $uri, callable $next): mixed {
        $result = $next();
        if ($result === false || $result === null) {
            self::guard($method, $uri);
        }
        return $result;
    }

    /**
     * Terminate the request with an HTTP 404 response containing a clear descriptive reason.
     *
     * @param string $method HTTP Method (GET, POST, etc.)
     * @param string $uri The requested URI path
     * @param string|null $customReason Optional explicit reason for the 404
     * @param array $extraData Optional additional payload fields
     */
    public static function guard(
        string $method,
        string $uri,
        ?string $customReason = null,
        array $extraData = []
    ): void {
        http_response_code(404);

        $cleanPath = parse_url($uri, PHP_URL_PATH) ?: '/';
        $cleanPath = rtrim($cleanPath, '/.') ?: '/';
        $normalizedPath = preg_replace('#^/alumni#i', '', $cleanPath) ?: '/';
        $formattedMethod = strtoupper($method);

        $reason = $customReason ?? "The requested endpoint '{$formattedMethod} {$cleanPath}' does not exist on this server. Please verify the URL path, parameters, and HTTP method against API documentation.";

        // Determine if client expects JSON or requested an API route
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isApi = str_starts_with($normalizedPath, '/api/') ||
                 str_contains($accept, 'application/json') ||
                 isset($_GET['api']) ||
                 isset($_GET['json']);

        if ($isApi) {
            Response::json(array_merge([
                'status' => 'error',
                'code' => 404,
                'guardian' => '404 Guardian Middleware',
                'message' => 'Resource or endpoint not found',
                'reason' => $reason,
                'method' => $formattedMethod,
                'path' => $cleanPath,
                'timestamp' => date('c')
            ], $extraData), 404);
            return;
        }

        // HTML Browser View Fallback
        $templatePath = __DIR__ . '/../../templates/404.html';
        if (file_exists($templatePath)) {
            Response::html($templatePath, [
                'PATH' => $cleanPath,
                'REASON' => $reason,
                'METHOD' => $formattedMethod
            ], 404);
            return;
        }

        // Plain text fallback
        Response::text("404 Not Found - {$reason}", 404);
    }

    /**
     * Abort request with 404 from any controller or service with a descriptive reason.
     */
    public static function abort(string $reason, array $extraData = []): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        self::guard($method, $uri, $reason, $extraData);
    }
}
