<?php
declare(strict_types=1);

namespace AlumniNexus\Controllers;

use AlumniNexus\Core\Response;
use AlumniNexus\Services\AlumniService;

class WebController {
    private AlumniService $service;

    public function __construct() {
        $this->service = new AlumniService();
    }

    public function home(): void {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (isset($_GET['raw']) || isset($_GET['test']) || (str_starts_with($accept, 'text/plain') && !str_contains($accept, 'text/html'))) {
            Response::text("ok");
        }
        Response::html(__DIR__ . '/../../templates/dashboard.html');
    }

    public function about(): void {
        Response::html(__DIR__ . '/../../templates/about.html');
    }

    public function analytics(): void {
        Response::html(__DIR__ . '/../../templates/analytics.html');
    }

    public function swagger(): void {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($accept, 'application/json') || isset($_GET['json'])) {
            $file = __DIR__ . '/../../docs/openapi.json';
            if (file_exists($file)) {
                header('Content-Type: application/json; charset=utf-8');
                header('Access-Control-Allow-Origin: *');
                echo file_get_contents($file);
                exit;
            }
        }
        Response::html(__DIR__ . '/../../templates/swagger.html');
    }
}
