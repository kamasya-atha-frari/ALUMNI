<?php
declare(strict_types=1);

namespace AlumniIstü\Core;

use AlumniIstü\Middleware\MiddlewareInterface;
use AlumniIstü\Middleware\NotFoundGuardianMiddleware;
use AlumniIstü\Middleware\ServerErrorGuardianMiddleware;

class Router {
    private array $routes = [];
    private array $middlewares = [];
    private mixed $customNotFoundHandler = null;

    public function get(string $path, callable|array $handler): self {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): self {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): self {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): self {
        return $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, callable|array $handler): self {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler
        ];
        return $this;
    }

    public function dispatch(string $method, string $uri, bool $exitOnResult = true): bool {
        $cleanPath = parse_url($uri, PHP_URL_PATH) ?: '/';
        $cleanPath = rtrim($cleanPath, '/.') ?: '/';

        // Check for HTTP method override (e.g. via X-HTTP-Method-Override header or _method POST param)
        if ($method === 'POST') {
            $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? ($_POST['_method'] ?? null);
            if ($override) {
                $method = strtoupper((string)$override);
            }
        }

        // Pre-flight CORS
        if ($method === 'OPTIONS') {
            if ($exitOnResult) {
                Response::json(['status' => 'ok', 'options' => true], 204);
            }
            return true;
        }

        // Try primary path first, then normalized path if in subdirectory (e.g., /alumni)
        $candidatePaths = [$cleanPath];
        $subPath = preg_replace('#^/alumni#i', '', $cleanPath) ?: '/';
        if ($subPath !== $cleanPath) {
            $candidatePaths[] = $subPath;
        }

        foreach ($candidatePaths as $tryPath) {
            foreach ($this->routes as $route) {
                if ($route['method'] !== strtoupper($method)) {
                    continue;
                }

                // Convert {param} and :param placeholders to regex named groups
                $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $route['path']);
                $pattern = preg_replace('#:([a-zA-Z0-9_]+)#', '(?P<$1>[^/]+)', $pattern);
                $pattern = "#^{$pattern}$#";

                if (preg_match($pattern, $tryPath, $matches)) {
                    $params = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));

                    if (is_callable($route['handler'])) {
                        $result = call_user_func_array($route['handler'], $params);
                        if ($exitOnResult && (is_string($result) || is_numeric($result))) {
                            Response::text((string)$result);
                        }
                        return true;
                    }

                    if (is_array($route['handler'])) {
                        [$class, $action] = $route['handler'];
                        $controller = new $class();
                        $result = call_user_func_array([$controller, $action], $params);
                        if ($exitOnResult && (is_string($result) || is_numeric($result))) {
                            Response::text((string)$result);
                        }
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Add middleware to global router execution chain
     */
    public function use(MiddlewareInterface|callable $middleware): self {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Set a custom 404 Not Found handler
     */
    public function setNotFoundHandler(callable $handler): self {
        $this->customNotFoundHandler = $handler;
        return $this;
    }

    public function resolve(string $method, string $uri): void {
        try {
            // Execute registered middlewares if any
            if (!empty($this->middlewares)) {
                $runner = function (int $index) use (&$runner, $method, $uri) {
                    if ($index >= count($this->middlewares)) {
                        $matched = $this->dispatch($method, $uri, true);
                        if (!$matched) {
                            $this->handleNotFound($method, $uri);
                        }
                        return $matched;
                    }
                    $mw = $this->middlewares[$index];
                    if ($mw instanceof MiddlewareInterface) {
                        return $mw->handle($method, $uri, fn() => $runner($index + 1));
                    }
                    if (is_callable($mw)) {
                        return $mw($method, $uri, fn() => $runner($index + 1));
                    }
                    return $runner($index + 1);
                };
                $runner(0);
                return;
            }

            $matched = $this->dispatch($method, $uri, true);

            if (!$matched) {
                $this->handleNotFound($method, $uri);
            }
        } catch (\Throwable $e) {
            ServerErrorGuardianMiddleware::handleException($e, $method, $uri);
        }
    }

    /**
     * Delegate 404 handling to 404 Guardian Middleware or custom handler
     */
    private function handleNotFound(string $method, string $uri): void {
        if ($this->customNotFoundHandler !== null) {
            call_user_func($this->customNotFoundHandler, $method, $uri);
            return;
        }

        NotFoundGuardianMiddleware::guard($method, $uri);
    }
}
