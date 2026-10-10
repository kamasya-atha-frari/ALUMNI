<?php
declare(strict_types=1);

namespace AlumniNexus\Controllers;

use AlumniNexus\Core\Response;
use AlumniNexus\Services\AlumniService;

class AlumniApiController {
    private AlumniService $service;

    public function __construct() {
        $this->service = new AlumniService();
    }

    public function index(): void {
        $filters = [
            'cohort' => $_GET['cohort'] ?? null,
            'industry' => $_GET['industry'] ?? null,
            'mentor' => $_GET['mentor'] ?? null,
        ];

        $results = $this->service->getAll($filters);

        Response::json([
            'status' => 'success',
            'version' => 'v1',
            'count' => count($results),
            'filters_applied' => array_filter($filters),
            'data' => $results,
        ]);
    }

    public function show(string $id): void {
        $record = $this->service->findById((int)$id);
        if (!$record) {
            Response::json(['status' => 'error', 'message' => 'Alumni profile not found'], 404);
        }

        Response::json([
            'status' => 'success',
            'data' => $record
        ]);
    }

    public function analytics(): void {
        $analytics = $this->service->getAnalytics();
        Response::json([
            'status' => 'success',
            'data' => $analytics
        ]);
    }
}
