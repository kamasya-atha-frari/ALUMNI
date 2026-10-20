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

    /**
     * READ: List all alumni records with query parameters
     * GET /api/v1/alumni
     */
    public function index(): void {
        $filters = [
            'cohort'   => $_GET['cohort'] ?? null,
            'industry' => $_GET['industry'] ?? null,
            'mentor'   => $_GET['mentor'] ?? null,
            'search'   => $_GET['search'] ?? ($_GET['q'] ?? null),
        ];

        $results = $this->service->getAll($filters);

        Response::json([
            'status' => 'success',
            'version' => 'v1',
            'count' => count($results),
            'filters_applied' => array_filter($filters, fn($v) => $v !== null && $v !== ''),
            'data' => $results,
        ]);
    }

    /**
     * READ: Retrieve single alumni profile
     * GET /api/v1/alumni/{id}
     */
    public function show(string $id): void {
        $record = $this->service->findById((int)$id);
        if (!$record) {
            Response::json(['status' => 'error', 'message' => "Alumni record #{$id} not found"], 404);
            return;
        }

        Response::json([
            'status' => 'success',
            'data' => $record
        ]);
    }

    /**
     * CREATE: Store a new alumni record
     * POST /api/v1/alumni
     * Also supports slide example: POST /api/users {"name": "Ada", "email": "ada@cv.edu.tr"}
     */
    public function create(): void {
        $input = $this->getRequestPayload();

        $name = $input['full_name'] ?? $input['name'] ?? null;
        $email = $input['email'] ?? null;

        if (!$name || !$email) {
            Response::json([
                'status' => 'error',
                'message' => 'Validation error: Both name (or full_name) and email are required.'
            ], 422);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json([
                'status' => 'error',
                'message' => 'Validation error: Invalid email address format.'
            ], 422);
            return;
        }

        $record = $this->service->create($input);

        Response::json([
            'status' => 'success',
            'message' => 'Alumni record created successfully',
            'data' => $record
        ], 201);
    }

    /**
     * UPDATE: Update an existing alumni record
     * PUT/PATCH /api/v1/alumni/{id}
     */
    public function update(string $id): void {
        $targetId = (int)$id;
        $existing = $this->service->findById($targetId);
        if (!$existing) {
            Response::json(['status' => 'error', 'message' => "Alumni record #{$id} not found"], 404);
            return;
        }

        $input = $this->getRequestPayload();

        if (isset($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            Response::json([
                'status' => 'error',
                'message' => 'Validation error: Invalid email address format.'
            ], 422);
            return;
        }

        $updated = $this->service->update($targetId, $input);

        Response::json([
            'status' => 'success',
            'message' => 'Alumni record updated successfully',
            'data' => $updated
        ]);
    }

    /**
     * DELETE: Delete an alumni record
     * DELETE /api/v1/alumni/{id}
     */
    public function delete(string $id): void {
        $targetId = (int)$id;
        $existing = $this->service->findById($targetId);
        if (!$existing) {
            Response::json(['status' => 'error', 'message' => "Alumni record #{$id} not found"], 404);
            return;
        }

        $success = $this->service->delete($targetId);

        if ($success) {
            Response::json([
                'status' => 'success',
                'message' => "Alumni record #{$id} deleted successfully"
            ]);
        } else {
            Response::json([
                'status' => 'error',
                'message' => "Failed to delete alumni record #{$id}"
            ], 500);
        }
    }

    /**
     * ANALYTICS: Accreditation & career metrics
     * GET /api/v1/analytics
     */
    public function analytics(): void {
        $analytics = $this->service->getAnalytics();
        Response::json([
            'status' => 'success',
            'data' => $analytics
        ]);
    }

    /**
     * Helper to read JSON request body or form POST
     */
    private function getRequestPayload(): array {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }
}
