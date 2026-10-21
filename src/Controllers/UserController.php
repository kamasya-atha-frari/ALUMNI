<?php
declare(strict_types=1);

namespace AlumniNexus\Controllers;

use AlumniNexus\Core\Response;
use AlumniNexus\Services\UserService;

/**
 * UserController - RESTful API Controller for User Management
 * 
 * Implements Week 03 Whiteboard specifications:
 * - POST   /api/users       (Creates user, no DB, returns 201 Created)
 * - GET    /api/users       (List all users)
 * - GET    /api/users/{id}  (Retrieve single user by ID)
 * - PUT    /api/users/{id}  (Replace entire user object)
 * - PATCH  /api/users/{id}  (Update specific user fields)
 * - DELETE /api/users/{id}  (Remove user by ID)
 */
class UserController {
    private UserService $service;

    public function __construct(?UserService $service = null) {
        $this->service = $service ?? new UserService();
    }

    /**
     * Parse incoming JSON request body or form payload.
     */
    private function getJsonInput(): array {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return !empty($_POST) ? $_POST : [];
    }

    /**
     * GET /api/users
     * List all users.
     */
    public function index(): void {
        $users = $this->service->getAll();
        Response::json($users, 200);
    }

    /**
     * GET /api/users/{id}
     * Get a specific user by ID.
     */
    public function show(string $id): void {
        $user = $this->service->getById((int)$id);
        if ($user === null) {
            Response::json([
                'error' => 'User not found',
                'message' => "No user found with ID {$id}"
            ], 404);
        }

        Response::json($user, 200);
    }

    /**
     * POST /api/users
     * Create a new user (in-memory, no database).
     */
    public function store(): void {
        $body = $this->getJsonInput();

        if (empty($body) || !isset($body['name'])) {
            Response::json([
                'error' => 'Validation failed',
                'message' => "The 'name' field is required in the request body."
            ], 400);
        }

        $created = $this->service->create($body);
        Response::json($created, 201);
    }

    /**
     * PUT /api/users/{id}
     * Full replacement of a user record.
     */
    public function update(string $id): void {
        $body = $this->getJsonInput();

        if (empty($body)) {
            Response::json([
                'error' => 'Validation failed',
                'message' => 'Request body cannot be empty for PUT replacement.'
            ], 400);
        }

        $updated = $this->service->replace((int)$id, $body);
        if ($updated === null) {
            Response::json([
                'error' => 'User not found',
                'message' => "Cannot replace user. No user found with ID {$id}"
            ], 404);
        }

        Response::json($updated, 200);
    }

    /**
     * PATCH /api/users/{id}
     * Partial update of specific user fields.
     */
    public function patch(string $id): void {
        $body = $this->getJsonInput();

        if (empty($body)) {
            Response::json([
                'error' => 'Validation failed',
                'message' => 'Request body cannot be empty for PATCH update.'
            ], 400);
        }

        $updated = $this->service->update((int)$id, $body);
        if ($updated === null) {
            Response::json([
                'error' => 'User not found',
                'message' => "Cannot update user. No user found with ID {$id}"
            ], 404);
        }

        Response::json($updated, 200);
    }

    /**
     * DELETE /api/users/{id}
     * Delete a user by ID.
     */
    public function destroy(string $id): void {
        $deleted = $this->service->delete((int)$id);
        if (!$deleted) {
            Response::json([
                'error' => 'User not found',
                'message' => "Cannot delete user. No user found with ID {$id}"
            ], 404);
        }

        Response::json([
            'status' => 'success',
            'message' => "User with ID {$id} was successfully deleted"
        ], 200);
    }
}
