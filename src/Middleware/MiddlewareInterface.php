<?php
declare(strict_types=1);

namespace AlumniIstü\Middleware;

/**
 * MiddlewareInterface - Standard contract for AlumniIstü HTTP Middlewares.
 */
interface MiddlewareInterface {
    /**
     * Process an incoming request and delegate to the next handler.
     *
     * @param string $method HTTP Request Method (GET, POST, etc.)
     * @param string $uri Request URI
     * @param callable $next The next middleware or route handler in chain
     * @return mixed
     */
    public function handle(string $method, string $uri, callable $next): mixed;
}
