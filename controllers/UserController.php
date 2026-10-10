<?php
/**
 * UserController - Web Interface Controller
 */

require_once __DIR__ . '/../models/User.php';

class UserController {
    public function index(): void {
        $users = User::all();
        $totalUsers = User::count();
        $config = require __DIR__ . '/../config/config.php';

        $viewFile = __DIR__ . '/../views/users.html';
        if (file_exists($viewFile)) {
            $content = file_get_contents($viewFile);
            // Inject dynamic user data into client script
            $jsonData = json_encode($users, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $content = str_replace('/* {{USERS_JSON}} */ []', $jsonData, $content);
            $content = str_replace('{{TOTAL_USERS}}', (string)$totalUsers, $content);
            echo $content;
        } else {
            header('Content-Type: text/plain');
            echo "Users list:\n" . print_r($users, true);
        }
    }

    public function show(int $id): void {
        $user = User::findById($id);
        if (!$user) {
            http_response_code(404);
            echo "User not found";
            return;
        }
        header('Content-Type: application/json');
        echo json_encode($user, JSON_PRETTY_PRINT);
    }

    public function create(): void {
        $this->index();
    }

    public function store(array $postData): void {
        if (empty($postData['email']) || empty($postData['first_name'])) {
            header('Location: /users?error=missing_fields');
            exit;
        }

        User::create($postData);
        header('Location: /users?success=created');
        exit;
    }

    public function update(int $id, array $postData): void {
        User::update($id, $postData);
        header('Location: /users?success=updated');
        exit;
    }

    public function delete(int $id): void {
        User::delete($id);
        header('Location: /users?success=deleted');
        exit;
    }
}
