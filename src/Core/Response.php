<?php
declare(strict_types=1);

namespace AlumniNexus\Core;

class Response {
    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function html(string $filePath, array $variables = [], int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');

        if (!file_exists($filePath)) {
            self::json(['error' => 'View template not found: ' . basename($filePath)], 404);
        }

        $content = file_get_contents($filePath);
        foreach ($variables as $key => $val) {
            $content = str_replace("{{{$key}}}", (string)$val, $content);
        }
        echo $content;
        exit;
    }

    public static function text(string $content, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: text/plain; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo $content;
        exit;
    }

    public static function redirect(string $url): void {
        header("Location: {$url}");
        exit;
    }
}
