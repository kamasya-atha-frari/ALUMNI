<?php
declare(strict_types=1);

namespace AlumniNexus\Services;

/**
 * UserService - In-Memory User Management Service
 * 
 * Implements Week 03 REST API requirements:
 * "POST /api/users (don't use any database yet)"
 * Includes in-memory seed records from lecture slides and an optional
 * persistent state cache across independent PHP processes.
 */
class UserService {
    /**
     * Default seed dataset matching lecture slides:
     * - Elif Kaya (2024)
     * - Mert Aydın (2021)
     * - Zeynep Arslan (2019)
     * - Can Öztürk (2023)
     */
    private static array $defaultUsers = [
        [
            'id' => 1,
            'name' => 'Elif Kaya',
            'graduationYear' => 2024
        ],
        [
            'id' => 2,
            'name' => 'Mert Aydın',
            'graduationYear' => 2021
        ],
        [
            'id' => 3,
            'name' => 'Zeynep Arslan',
            'graduationYear' => 2019
        ],
        [
            'id' => 4,
            'name' => 'Can Öztürk',
            'graduationYear' => 2023
        ]
    ];

    private static ?array $inMemoryStore = null;
    private string $storageFile;

    public function __construct(?string $customStorageFile = null) {
        $this->storageFile = $customStorageFile ?? (sys_get_temp_dir() . '/alumni_users_store.json');
        $this->load();
    }

    /**
     * Load users from persistent cache file or initialize with seed data.
     */
    private function load(): void {
        if (self::$inMemoryStore !== null) {
            return;
        }

        if (file_exists($this->storageFile)) {
            $raw = @file_get_contents($this->storageFile);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    self::$inMemoryStore = $decoded;
                    return;
                }
            }
        }

        self::$inMemoryStore = self::$defaultUsers;
    }

    /**
     * Persist state to local cache file for cross-request continuity in testing.
     */
    private function persist(): void {
        if (self::$inMemoryStore === null) {
            return;
        }

        @file_put_contents(
            $this->storageFile,
            json_encode(self::$inMemoryStore, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Retrieve all users.
     *
     * @return array<int, array>
     */
    public function getAll(): array {
        return array_values(self::$inMemoryStore ?? self::$defaultUsers);
    }

    /**
     * Find a user by ID.
     */
    public function getById(int $id): ?array {
        foreach (self::$inMemoryStore ?? [] as $user) {
            if ((int)($user['id'] ?? 0) === $id) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Create a new user record.
     * Generates an incremental ID if not provided.
     */
    public function create(array $data): array {
        $existingIds = array_map(fn($u) => (int)($u['id'] ?? 0), self::$inMemoryStore ?? []);
        $nextId = !empty($existingIds) ? (max($existingIds) + 1) : 1;

        $newUser = [
            'id' => (isset($data['id']) && is_numeric($data['id'])) ? (int)$data['id'] : $nextId,
            'name' => (string)($data['name'] ?? 'Unnamed User'),
            'graduationYear' => isset($data['graduationYear']) ? (int)$data['graduationYear'] : (int)date('Y')
        ];

        // Retain any additional attributes passed in the payload
        foreach ($data as $key => $val) {
            if (!in_array($key, ['id', 'name', 'graduationYear'], true)) {
                $newUser[$key] = $val;
            }
        }

        self::$inMemoryStore[] = $newUser;
        $this->persist();

        return $newUser;
    }

    /**
     * PUT: Full replacement of an existing user record.
     */
    public function replace(int $id, array $data): ?array {
        foreach (self::$inMemoryStore ?? [] as $index => $user) {
            if ((int)($user['id'] ?? 0) === $id) {
                $replacedUser = [
                    'id' => $id,
                    'name' => (string)($data['name'] ?? $user['name'] ?? 'Unnamed User'),
                    'graduationYear' => isset($data['graduationYear']) ? (int)$data['graduationYear'] : ($user['graduationYear'] ?? (int)date('Y'))
                ];

                // Retain other custom fields from replacement payload
                foreach ($data as $key => $val) {
                    if (!in_array($key, ['id', 'name', 'graduationYear'], true)) {
                        $replacedUser[$key] = $val;
                    }
                }

                self::$inMemoryStore[$index] = $replacedUser;
                $this->persist();
                return $replacedUser;
            }
        }

        return null;
    }

    /**
     * PATCH: Partial update of specific fields in an existing user record.
     */
    public function update(int $id, array $data): ?array {
        foreach (self::$inMemoryStore ?? [] as $index => $user) {
            if ((int)($user['id'] ?? 0) === $id) {
                $updatedUser = $user;

                foreach ($data as $key => $val) {
                    if ($key === 'id') {
                        continue; // Protect primary identifier
                    }
                    if ($key === 'graduationYear') {
                        $updatedUser['graduationYear'] = (int)$val;
                    } else {
                        $updatedUser[$key] = $val;
                    }
                }

                self::$inMemoryStore[$index] = $updatedUser;
                $this->persist();
                return $updatedUser;
            }
        }

        return null;
    }

    /**
     * DELETE: Remove user by ID.
     */
    public function delete(int $id): bool {
        foreach (self::$inMemoryStore ?? [] as $index => $user) {
            if ((int)($user['id'] ?? 0) === $id) {
                unset(self::$inMemoryStore[$index]);
                self::$inMemoryStore = array_values(self::$inMemoryStore);
                $this->persist();
                return true;
            }
        }

        return false;
    }

    /**
     * Reset store back to the lecture slides default mock dataset.
     */
    public function reset(): void {
        self::$inMemoryStore = self::$defaultUsers;
        $this->persist();
    }
}
