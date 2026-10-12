<?php
declare(strict_types=1);

namespace AlumniNexus\Core;

class Router {
    private array $routes = [];

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
        $cleanPath = rtrim($cleanPath, '/') ?: '/';

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

                // Convert {param} placeholders to regex named groups
                $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $route['path']);
                $pattern = "#^{$pattern}$#";

                if (preg_match($pattern, $tryPath, $matches)) {
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

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

    public function resolve(string $method, string $uri): void {
        $matched = $this->dispatch($method, $uri, true);

        if (!$matched) {
            $cleanPath = parse_url($uri, PHP_URL_PATH) ?: '/';
            $cleanPath = rtrim($cleanPath, '/') ?: '/';
            $cleanPath = preg_replace('#^/alumni#i', '', $cleanPath) ?: '/';

            // 404 Handler
            if (str_starts_with($cleanPath, '/api/')) {
                Response::json([
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'API endpoint not found',
                    'path' => $cleanPath
                ], 404);
            } else {
                Response::html(__DIR__ . '/../../templates/404.html', ['PATH' => $cleanPath], 404);
            }
        }
    }
}
