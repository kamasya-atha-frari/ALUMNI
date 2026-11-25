<?php
declare(strict_types=1);

namespace AlumniIstü\Services;

use AlumniIstü\Core\Database;
use PDO;

/**
 * AnnouncementService - Manages department announcements/posts from the Admin Panel.
 * Uses prepared PDO statements against MySQL with automatic standby file fallback.
 */
class AnnouncementService {
    private const ALLOWED_CATEGORIES = ['general', 'career', 'event', 'accreditation'];
    private const ALLOWED_STATUSES = ['published', 'draft'];

    private static bool $schemaChecked = false;
    private string $fallbackStorePath;

    public function __construct(?string $fallbackStorePath = null) {
        $this->fallbackStorePath = $fallbackStorePath
            ?? (sys_get_temp_dir() . '/alumni_istu_announcements.json');
    }

    /**
     * Validate announcement input payload.
     *
     * @return array<string, string>
     */
    public function validateInput(array $input, bool $isUpdate = false): array {
        $errors = [];

        if (!$isUpdate || array_key_exists('title', $input)) {
            $title = trim((string)($input['title'] ?? ''));
            if ($title === '') {
                $errors['title'] = "Validation error: 'title' is required.";
            } elseif (mb_strlen($title) > 150) {
                $errors['title'] = "Validation error: 'title' must not exceed 150 characters.";
            }
        }

        if (!$isUpdate || array_key_exists('body', $input)) {
            $body = trim((string)($input['body'] ?? ''));
            if ($body === '') {
                $errors['body'] = "Validation error: 'body' is required.";
            }
        }

        if (array_key_exists('category', $input) && $input['category'] !== null && $input['category'] !== '') {
            $cat = strtolower(trim((string)$input['category']));
            if (!in_array($cat, self::ALLOWED_CATEGORIES, true)) {
                $errors['category'] = "Validation error: 'category' must be one of: " . implode(', ', self::ALLOWED_CATEGORIES) . '.';
            }
        }

        if (array_key_exists('status', $input) && $input['status'] !== null && $input['status'] !== '') {
            $status = strtolower(trim((string)$input['status']));
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                $errors['status'] = "Validation error: 'status' must be one of: " . implode(', ', self::ALLOWED_STATUSES) . '.';
            }
        }

        return $errors;
    }

    public function getAll(array $filters = []): array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $sql = 'SELECT * FROM announcements WHERE 1=1';
                $params = [];

                if (!empty($filters['status'])) {
                    $sql .= ' AND status = :status';
                    $params['status'] = strtolower(trim((string)$filters['status']));
                }
                if (!empty($filters['category'])) {
                    $sql .= ' AND category = :category';
                    $params['category'] = strtolower(trim((string)$filters['category']));
                }

                $sql .= ' ORDER BY is_pinned DESC, created_at DESC, id DESC';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return array_map(fn(array $r): array => $this->normalizeRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        $items = $this->readFallbackStore();
        if (!empty($filters['status'])) {
            $st = strtolower(trim((string)$filters['status']));
            $items = array_filter($items, static fn(array $a): bool => ($a['status'] ?? '') === $st);
        }
        if (!empty($filters['category'])) {
            $cat = strtolower(trim((string)$filters['category']));
            $items = array_filter($items, static fn(array $a): bool => ($a['category'] ?? '') === $cat);
        }
        usort($items, static fn(array $a, array $b): int => ((int)!empty($b['is_pinned']) <=> (int)!empty($a['is_pinned'])) ?: ((int)$b['id'] <=> (int)$a['id']));
        return array_values(array_map(fn(array $r): array => $this->normalizeRow($r), $items));
    }

    public function findById(int $id): ?array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ? $this->normalizeRow($row) : null;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        foreach ($this->readFallbackStore() as $item) {
            if ((int)($item['id'] ?? 0) === $id) {
                return $this->normalizeRow($item);
            }
        }
        return null;
    }

    public function create(array $input, ?array $author = null): array {
        $title = trim((string)($input['title'] ?? ''));
        $body = trim((string)($input['body'] ?? ''));
        $category = strtolower(trim((string)($input['category'] ?? 'general'))) ?: 'general';
        $status = strtolower(trim((string)($input['status'] ?? 'published'))) ?: 'published';
        $isPinned = !empty($input['is_pinned']);
        $authorId = isset($author['id']) ? (int)$author['id'] : null;
        $authorName = (string)($author['username'] ?? $author['email'] ?? 'Administrator');

        $pdo = $this->getReadyPdo();
        if ($pdo) {
            $stmt = $pdo->prepare('
                INSERT INTO announcements (title, body, category, status, is_pinned, author_user_id, author_name)
                VALUES (:title, :body, :category, :status, :is_pinned, :author_user_id, :author_name)
            ');
            $stmt->execute([
                'title' => $title,
                'body' => $body,
                'category' => $category,
                'status' => $status,
                'is_pinned' => $isPinned ? 1 : 0,
                'author_user_id' => $authorId,
                'author_name' => $authorName,
            ]);
            $newId = (int)$pdo->lastInsertId();
            $created = $this->findById($newId);
            if ($created !== null) {
                return $created;
            }
        }

        $store = $this->readFallbackStore();
        $nextId = empty($store) ? 1 : (max(array_map(static fn($a) => (int)($a['id'] ?? 0), $store)) + 1);
        $now = gmdate('Y-m-d H:i:s');
        $record = [
            'id' => $nextId,
            'title' => $title,
            'body' => $body,
            'category' => $category,
            'status' => $status,
            'is_pinned' => $isPinned,
            'author_user_id' => $authorId,
            'author_name' => $authorName,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $store[] = $record;
        $this->writeFallbackStore($store);
        return $this->normalizeRow($record);
    }

    public function update(int $id, array $input): ?array {
        $existing = $this->findById($id);
        if ($existing === null) {
            return null;
        }

        $pdo = $this->getReadyPdo();
        if ($pdo) {
            $fields = [];
            $params = ['id' => $id];

            if (array_key_exists('title', $input)) {
                $fields[] = 'title = :title';
                $params['title'] = trim((string)$input['title']);
            }
            if (array_key_exists('body', $input)) {
                $fields[] = 'body = :body';
                $params['body'] = trim((string)$input['body']);
            }
            if (array_key_exists('category', $input)) {
                $fields[] = 'category = :category';
                $params['category'] = strtolower(trim((string)$input['category']));
            }
            if (array_key_exists('status', $input)) {
                $fields[] = 'status = :status';
                $params['status'] = strtolower(trim((string)$input['status']));
            }
            if (array_key_exists('is_pinned', $input)) {
                $fields[] = 'is_pinned = :is_pinned';
                $params['is_pinned'] = !empty($input['is_pinned']) ? 1 : 0;
            }

            if (!empty($fields)) {
                $stmt = $pdo->prepare('UPDATE announcements SET ' . implode(', ', $fields) . ' WHERE id = :id');
                $stmt->execute($params);
            }
            return $this->findById($id);
        }

        $store = $this->readFallbackStore();
        foreach ($store as &$item) {
            if ((int)($item['id'] ?? 0) === $id) {
                if (array_key_exists('title', $input)) $item['title'] = trim((string)$input['title']);
                if (array_key_exists('body', $input)) $item['body'] = trim((string)$input['body']);
                if (array_key_exists('category', $input)) $item['category'] = strtolower(trim((string)$input['category']));
                if (array_key_exists('status', $input)) $item['status'] = strtolower(trim((string)$input['status']));
                if (array_key_exists('is_pinned', $input)) $item['is_pinned'] = !empty($input['is_pinned']);
                $item['updated_at'] = gmdate('Y-m-d H:i:s');
                $this->writeFallbackStore($store);
                return $this->normalizeRow($item);
            }
        }
        return null;
    }

    public function delete(int $id): bool {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = :id');
                $stmt->execute(['id' => $id]);
                return $stmt->rowCount() > 0;
            } catch (\Throwable $e) {
                return false;
            }
        }

        $store = $this->readFallbackStore();
        $before = count($store);
        $store = array_values(array_filter($store, static fn(array $a): bool => (int)($a['id'] ?? 0) !== $id));
        if (count($store) < $before) {
            $this->writeFallbackStore($store);
            return true;
        }
        return false;
    }

    private function normalizeRow(array $row): array {
        return [
            'id' => (int)($row['id'] ?? 0),
            'title' => (string)($row['title'] ?? ''),
            'body' => (string)($row['body'] ?? ''),
            'category' => (string)($row['category'] ?? 'general'),
            'status' => (string)($row['status'] ?? 'published'),
            'is_pinned' => !empty($row['is_pinned']),
            'author_user_id' => isset($row['author_user_id']) && $row['author_user_id'] !== null ? (int)$row['author_user_id'] : null,
            'author_name' => $row['author_name'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function getReadyPdo(): ?PDO {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }
        if (!self::$schemaChecked) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS announcements (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(150) NOT NULL,
                        body TEXT NOT NULL,
                        category ENUM('general', 'career', 'event', 'accreditation') NOT NULL DEFAULT 'general',
                        status ENUM('published', 'draft') NOT NULL DEFAULT 'published',
                        is_pinned BOOLEAN NOT NULL DEFAULT FALSE,
                        author_user_id INT NULL,
                        author_name VARCHAR(150) NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_announcements_status (status),
                        INDEX idx_announcements_category (category),
                        INDEX idx_announcements_pinned (is_pinned)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
                self::$schemaChecked = true;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return $pdo;
    }

    private function readFallbackStore(): array {
        if (!file_exists($this->fallbackStorePath)) {
            return [
                [
                    'id' => 1,
                    'title' => 'Welcome to AlumniIstü 2026 Career & Mentorship Cycle',
                    'body' => 'Applications for Spring 2026 alumni-student mentorship pairings are now open for Istanbul University MIS (YBS) cohorts.',
                    'category' => 'general',
                    'status' => 'published',
                    'is_pinned' => true,
                    'author_user_id' => null,
                    'author_name' => 'Kamasya Atha Frari',
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ],
            ];
        }
        $raw = @file_get_contents($this->fallbackStorePath);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeFallbackStore(array $store): void {
        @file_put_contents($this->fallbackStorePath, json_encode($store, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
