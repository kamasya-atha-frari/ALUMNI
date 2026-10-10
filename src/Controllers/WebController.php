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
        Response::html(__DIR__ . '/../../templates/dashboard.html');
    }

    public function analytics(): void {
        Response::html(__DIR__ . '/../../templates/analytics.html');
    }

    public function swagger(): void {
        Response::html(__DIR__ . '/../../templates/swagger.html');
    }
}
