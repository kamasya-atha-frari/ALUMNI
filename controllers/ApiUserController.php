<?php
/**
 * ApiUserController - REST API Controller
 */

require_once __DIR__ . '/../models/User.php';

class ApiUserController {
    private function jsonResponse(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function index(): void {
        $users = User::all();
        $this->jsonResponse([
            'status' => 'success',
            'count' => count($users),
            'data' => $users
        ]);
    }

    public function show(int $id): void {
        $user = User::findById($id);
        if (!$user) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }
        $this->jsonResponse([
            'status' => 'success',
            'data' => $user
        ]);
    }

    public function store(array $payload): void {
        if (empty($payload['email']) || empty($payload['first_name'])) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Validation error: first_name and email are required.'
            ], 422);
        }

        $existing = User::findByEmail($payload['email']);
        if ($existing) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Email already registered.'
            ], 409);
        }

        $created = User::create($payload);
        $this->jsonResponse([
            'status' => 'success',
            'message' => 'User created successfully',
            'data' => $created
        ], 201);
    }

    public function update(int $id, array $payload): void {
        $user = User::findById($id);
        if (!$user) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        $updated = User::update($id, $payload);
        $this->jsonResponse([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $updated
        ]);
    }

    public function destroy(int $id): void {
        $user = User::findById($id);
        if (!$user) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }

        User::delete($id);
        $this->jsonResponse([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    }
}
