<?php
/**
 * Core OOP Router Engine
 * Handles pattern matching, parameter extraction, and route dispatching.
 */

class Router {
    private array $routes = [];

    public function get(string $pattern, $handler): void {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, $handler): void {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, $handler): void {
        $this->addRoute('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, $handler): void {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    private function addRoute(string $method, string $pattern, $handler): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    public function dispatch(string $method, string $uri): bool {
        // Strip query string and base path
        $path = parse_url($uri, PHP_URL_PATH);
        $path = rtrim($path, '/') ?: '/';

        // Normalize base url if running in subdirectory
        $path = preg_replace('#^/Alumni#i', '', $path) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            // Convert {param} placeholders to regex named groups
            $regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $route['pattern']);
            $regex = '#^' . $regex . '$#';

            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                if (is_callable($route['handler'])) {
                    call_user_func_array($route['handler'], $params);
                    return true;
                }

                if (is_array($route['handler']) && count($route['handler']) === 2) {
                    [$class, $action] = $route['handler'];
                    if (is_string($class)) {
                        $instance = new $class();
                    } else {
                        $instance = $class;
                    }
                    call_user_func_array([$instance, $action], $params);
                    return true;
                }
            }
        }

        return false;
    }
}
