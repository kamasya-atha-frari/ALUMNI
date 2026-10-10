<?php
/**
 * Front Controller & Router Dispatcher
 * Istanbul University MIS Alumni Portal
 */

require_once __DIR__ . '/routes/Router.php';

$router = new Router();

// Register Route Definitions
require_once __DIR__ . '/routes/web.php';
require_once __DIR__ . '/routes/api.php';

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri    = $_SERVER['REQUEST_URI'] ?? '/';

// Handle CORS pre-flight requests
if ($requestMethod === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}

// Dispatch to matched handler
$dispatched = $router->dispatch($requestMethod, $requestUri);

if (!$dispatched) {
    // If request asks for JSON
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (str_contains($accept, 'application/json') || str_starts_with($requestUri, '/api/')) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'error',
            'code' => 404,
            'message' => 'Resource or endpoint not found',
            'uri' => $requestUri
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo "<h1>404 Not Found</h1><p>The requested route does not exist.</p><p><a href='/'>Return to Home</a></p>";
    }
}
