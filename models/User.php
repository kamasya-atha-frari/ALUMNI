<?php
/**
 * User Domain Model
 * Supports persistent database operations via PDO with seamless in-memory fallback.
 */

require_once __DIR__ . '/../config/db.php';

class User {
    private static array $defaultUsers = [
        [
            'id' => 1,
            'student_id' => '130120001',
            'first_name' => 'Ahmet',
            'last_name' => 'Yilmaz',
            'email' => 'ahmet.yilmaz@ogr.iu.edu.tr',
            'role' => 'alumni',
            'graduation_year' => 2023,
            'department' => 'Management Information Systems (YBS)',
            'company' => 'Turkcell',
            'job_title' => 'Business Intelligence Specialist',
            'is_mentor' => 1,
            'created_at' => '2023-09-01 10:00:00'
        ],
        [
            'id' => 2,
            'student_id' => '130120002',
            'first_name' => 'Zeynep',
            'last_name' => 'Kaya',
            'email' => 'zeynep.kaya@ogr.iu.edu.tr',
            'role' => 'alumni',
            'graduation_year' => 2022,
            'department' => 'Management Information Systems (YBS)',
            'company' => 'Trendyol',
            'job_title' => 'Software Engineer',
            'is_mentor' => 1,
            'created_at' => '2022-09-01 10:00:00'
        ],
        [
            'id' => 3,
            'student_id' => '130120003',
            'first_name' => 'Can',
            'last_name' => 'Demir',
            'email' => 'can.demir@ogr.iu.edu.tr',
            'role' => 'student',
            'graduation_year' => 2025,
            'department' => 'Management Information Systems (YBS)',
            'company' => 'Getir',
            'job_title' => 'Cloud Infrastructure Intern',
            'is_mentor' => 0,
            'created_at' => '2024-01-15 12:30:00'
        ]
    ];

    private static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (!isset($_SESSION['users_data'])) {
            $_SESSION['users_data'] = self::$defaultUsers;
        }
    }

    public static function all(): array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->query('
                    SELECT u.*, p.graduation_year, p.company, p.job_title, p.is_mentor, p.department
                    FROM users u
                    LEFT JOIN alumni_profiles p ON u.id = p.user_id
                    ORDER BY u.id ASC
                ');
                return $stmt->fetchAll();
            } catch (\Exception $e) {
                // fallback to memory
            }
        }

        self::initSession();
        return $_SESSION['users_data'];
    }

    public static function findById(int $id): ?array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('
                    SELECT u.*, p.graduation_year, p.company, p.job_title, p.is_mentor, p.department
                    FROM users u
                    LEFT JOIN alumni_profiles p ON u.id = p.user_id
                    WHERE u.id = :id
                    LIMIT 1
                ');
                $stmt->execute(['id' => $id]);
                $user = $stmt->fetch();
                return $user ?: null;
            } catch (\Exception $e) {
                // fallback to memory
            }
        }

        self::initSession();
        foreach ($_SESSION['users_data'] as $user) {
            if ($user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }

    public static function findByEmail(string $email): ?array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
                $stmt->execute(['email' => $email]);
                $user = $stmt->fetch();
                return $user ?: null;
            } catch (\Exception $e) {
                // fallback
            }
        }

        self::initSession();
        foreach ($_SESSION['users_data'] as $user) {
            if (strtolower($user['email']) === strtolower($email)) {
                return $user;
            }
        }
        return null;
    }

    public static function create(array $data): array {
        self::initSession();
        
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO users (student_id, first_name, last_name, email, role)
                    VALUES (:student_id, :first_name, :last_name, :email, :role)
                ');
                $stmt->execute([
                    'student_id' => $data['student_id'] ?? ('STU' . time()),
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                    'email' => $data['email'] ?? '',
                    'role' => $data['role'] ?? 'alumni'
                ]);
                $newId = (int)$pdo->lastInsertId();

                $stmtProfile = $pdo->prepare('
                    INSERT INTO alumni_profiles (user_id, graduation_year, department, company, job_title, is_mentor)
                    VALUES (:user_id, :graduation_year, :department, :company, :job_title, :is_mentor)
                ');
                $stmtProfile->execute([
                    'user_id' => $newId,
                    'graduation_year' => $data['graduation_year'] ?? date('Y'),
                    'department' => $data['department'] ?? 'Management Information Systems (YBS)',
                    'company' => $data['company'] ?? null,
                    'job_title' => $data['job_title'] ?? null,
                    'is_mentor' => !empty($data['is_mentor']) ? 1 : 0
                ]);

                return self::findById($newId);
            } catch (\Exception $e) {
                // fallback
            }
        }

        // In-memory creation
        $maxId = 0;
        foreach ($_SESSION['users_data'] as $u) {
            if ($u['id'] > $maxId) {
                $maxId = $u['id'];
            }
        }
        $newId = $maxId + 1;

        $record = [
            'id' => $newId,
            'student_id' => $data['student_id'] ?? ('STU' . $newId),
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'] ?? '',
            'role' => $data['role'] ?? 'alumni',
            'graduation_year' => (int)($data['graduation_year'] ?? date('Y')),
            'department' => $data['department'] ?? 'Management Information Systems (YBS)',
            'company' => $data['company'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'is_mentor' => !empty($data['is_mentor']) ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $_SESSION['users_data'][] = $record;
        return $record;
    }

    public static function update(int $id, array $data): ?array {
        self::initSession();

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('
                    UPDATE users
                    SET first_name = :first_name, last_name = :last_name, role = :role
                    WHERE id = :id
                ');
                $stmt->execute([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'role' => $data['role'] ?? 'alumni',
                    'id' => $id
                ]);

                $stmtProfile = $pdo->prepare('
                    UPDATE alumni_profiles
                    SET graduation_year = :graduation_year, company = :company, job_title = :job_title, is_mentor = :is_mentor
                    WHERE user_id = :user_id
                ');
                $stmtProfile->execute([
                    'graduation_year' => $data['graduation_year'] ?? date('Y'),
                    'company' => $data['company'] ?? null,
                    'job_title' => $data['job_title'] ?? null,
                    'is_mentor' => !empty($data['is_mentor']) ? 1 : 0,
                    'user_id' => $id
                ]);

                return self::findById($id);
            } catch (\Exception $e) {
                // fallback
            }
        }

        foreach ($_SESSION['users_data'] as &$user) {
            if ($user['id'] === $id) {
                $user = array_merge($user, $data);
                return $user;
            }
        }
        return null;
    }

    public static function delete(int $id): bool {
        self::initSession();

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                return $stmt->execute(['id' => $id]);
            } catch (\Exception $e) {
                // fallback
            }
        }

        foreach ($_SESSION['users_data'] as $index => $user) {
            if ($user['id'] === $id) {
                unset($_SESSION['users_data'][$index]);
                $_SESSION['users_data'] = array_values($_SESSION['users_data']);
                return true;
            }
        }
        return false;
    }

    public static function reset(): void {
        self::initSession();
        $_SESSION['users_data'] = self::$defaultUsers;
    }

    public static function count(): int {
        return count(self::all());
    }
}
