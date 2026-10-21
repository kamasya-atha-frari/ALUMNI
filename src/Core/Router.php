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

    public function resolve(string $method, string $uri): void {
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
            Response::json(['status' => 'ok', 'options' => true], 204);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            // Convert /api/v1/alumni/{id} and /companies/:id/job-postings to regex
            $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = preg_replace('#:([a-zA-Z0-9_]+)#', '(?P<$1>[^/]+)', $pattern);
            $pattern = "#^{$pattern}$#";

            if (preg_match($pattern, $cleanPath, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                if (is_callable($route['handler'])) {
                    call_user_func_array($route['handler'], $params);
                    return;
                }

                if (is_array($route['handler'])) {
                    [$class, $action] = $route['handler'];
                    $controller = new $class();
                    call_user_func_array([$controller, $action], $params);
                    return;
                }
            }
        }

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
