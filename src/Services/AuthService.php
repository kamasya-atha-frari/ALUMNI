<?php
declare(strict_types=1);

namespace AlumniIstü\Services;

use AlumniIstü\Core\Database;
use AlumniIstü\Domain\Enums\UserRole;
use PDO;

/**
 * Registration, Authentication, and Account Lookup Service.
 * Uses prepared PDO statements against MySQL with automatic standby fallback.
 */
class AuthService {
    private static bool $schemaChecked = false;
    private string $fallbackStorePath;
    private TokenService $tokenService;

    public function __construct(?string $fallbackStorePath = null, ?TokenService $tokenService = null) {
        $this->fallbackStorePath = $fallbackStorePath
            ?? (sys_get_temp_dir() . '/alumni_istu_registered_users.json');
        $this->tokenService = $tokenService ?? new TokenService();
    }

    /**
     * Validate registration payload fields prior to CAPTCHA verification & persistence.
     *
     * @return array<string, string> Map of field => validation error message
     */
    public function validateRegistrationInput(array $input): array {
        $errors = [];

        $username = trim((string)($input['username'] ?? ''));
        if ($username === '') {
            $errors['username'] = "Validation error: 'username' is required.";
        } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            $errors['username'] = "Validation error: 'username' must be 3–50 characters using letters, numbers, '.', '_', or '-'.";
        }

        $email = trim((string)($input['email'] ?? ''));
        if ($email === '') {
            $errors['email'] = "Validation error: 'email' is required.";
        } elseif (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Validation error: 'email' must be a valid email address (max 150 chars).";
        }

        $fullName = trim((string)($input['full_name'] ?? $input['name'] ?? ''));
        if ($fullName === '') {
            $errors['full_name'] = "Validation error: 'full_name' is required.";
        } elseif (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 150) {
            $errors['full_name'] = "Validation error: 'full_name' must be between 2 and 150 characters.";
        }

        $password = (string)($input['password'] ?? '');
        if ($password === '') {
            $errors['password'] = "Validation error: 'password' is required.";
        } elseif (strlen($password) < 8 || strlen($password) > 128) {
            $errors['password'] = "Validation error: 'password' must be between 8 and 128 characters.";
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errors['password'] = "Validation error: 'password' must contain at least one letter and one number.";
        }

        if (array_key_exists('password_confirmation', $input) && $input['password_confirmation'] !== null && $input['password_confirmation'] !== '') {
            if ((string)$input['password_confirmation'] !== $password) {
                $errors['password_confirmation'] = "Validation error: 'password_confirmation' does not match 'password'.";
            }
        }

        $role = strtolower(trim((string)($input['role'] ?? UserRole::ALUMNI->value)));
        if ($role === '') {
            $role = UserRole::ALUMNI->value;
        }
        $allowedPublicRoles = UserRole::selfRegistrationRoles();
        if (!in_array($role, $allowedPublicRoles, true)) {
            $errors['role'] = "Validation error: Self-registration role must be one of: " . implode(', ', $allowedPublicRoles) . '.';
        }

        $gradYearRaw = $input['graduation_year'] ?? $input['cohort'] ?? null;
        if ($gradYearRaw !== null && $gradYearRaw !== '') {
            if (!is_numeric($gradYearRaw)) {
                $errors['graduation_year'] = "Validation error: 'graduation_year' must be a valid 4-digit year.";
            } else {
                $year = (int)$gradYearRaw;
                if ($year < 1950 || $year > 2035) {
                    $errors['graduation_year'] = "Validation error: 'graduation_year' must be between 1950 and 2035.";
                }
            }
        }

        if (isset($input['department_id']) && $input['department_id'] !== '' && $input['department_id'] !== null) {
            if (!is_numeric($input['department_id']) || (int)$input['department_id'] <= 0) {
                $errors['department_id'] = "Validation error: 'department_id' must be a positive integer.";
            }
        }

        $studentId = trim((string)($input['student_id'] ?? ''));
        if ($studentId !== '' && mb_strlen($studentId) > 50) {
            $errors['student_id'] = "Validation error: 'student_id' must not exceed 50 characters.";
        }

        $company = trim((string)($input['company'] ?? ''));
        if ($company !== '' && mb_strlen($company) > 150) {
            $errors['company'] = "Validation error: 'company' must not exceed 150 characters.";
        }

        $jobTitle = trim((string)($input['job_title'] ?? ''));
        if ($jobTitle !== '' && mb_strlen($jobTitle) > 150) {
            $errors['job_title'] = "Validation error: 'job_title' must not exceed 150 characters.";
        }

        return $errors;
    }

    /**
     * Full registration workflow:
     *  1. Validate fields
     *  2. Enforce server-side CAPTCHA verification BEFORE user creation
     *  3. Enforce unique username / email / student_id checks (HTTP 409 Conflict)
     *  4. Hash password via password_hash()
     *  5. Persist account and issue Bearer access token
     *
     * @return array{ok:bool, status:int, error_code?:string, message?:string, errors?:array, user?:array, token?:array}
     */
    public function register(array $input, CaptchaService $captchaService): array {
        $errors = $this->validateRegistrationInput($input);

        // Enforce CAPTCHA presence & server-side verification BEFORE creating the user
        $captchaId = isset($input['captcha_id']) ? (string)$input['captcha_id'] : null;
        $captchaAnswer = isset($input['captcha_answer'])
            ? (string)$input['captcha_answer']
            : (isset($input['captcha']) ? (string)$input['captcha'] : null);

        if (!empty($errors)) {
            if (trim((string)($captchaId ?? '')) === '' || trim((string)($captchaAnswer ?? '')) === '') {
                $errors['captcha'] = 'CAPTCHA verification is required. Please provide both captcha_id and captcha_answer.';
            }
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => (string)reset($errors),
                'errors' => $errors,
            ];
        }

        $captchaCheck = $captchaService->verify($captchaId, $captchaAnswer);
        if (!$captchaCheck['valid']) {
            return [
                'ok' => false,
                'status' => $captchaCheck['http_status'],
                'error_code' => $captchaCheck['error_code'] ?? 'CAPTCHA_INVALID',
                'message' => $captchaCheck['message'] ?? 'CAPTCHA verification failed.',
                'errors' => [
                    'captcha' => $captchaCheck['message'] ?? 'CAPTCHA verification failed.',
                ],
            ];
        }

        $username = strtolower(trim((string)$input['username']));
        $email = strtolower(trim((string)$input['email']));
        $fullName = trim((string)($input['full_name'] ?? $input['name']));
        $passwordHash = password_hash((string)$input['password'], PASSWORD_DEFAULT);
        $role = strtolower(trim((string)($input['role'] ?? UserRole::ALUMNI->value)));
        if ($role === '') {
            $role = UserRole::ALUMNI->value;
        }

        $gradYearRaw = $input['graduation_year'] ?? $input['cohort'] ?? null;
        $graduationYear = ($gradYearRaw !== null && $gradYearRaw !== '') ? (int)$gradYearRaw : null;
        $departmentId = (isset($input['department_id']) && $input['department_id'] !== '' && $input['department_id'] !== null)
            ? (int)$input['department_id']
            : 1;
        $studentId = trim((string)($input['student_id'] ?? '')) ?: null;
        $company = trim((string)($input['company'] ?? '')) ?: null;
        $jobTitle = trim((string)($input['job_title'] ?? '')) ?: null;

        // Duplicate account checks
        if ($this->findRawByUsername($username) !== null) {
            return [
                'ok' => false,
                'status' => 409,
                'error_code' => 'DUPLICATE_USERNAME',
                'message' => "An account with username '{$username}' already exists.",
                'errors' => ['username' => "Username '{$username}' is already taken."],
            ];
        }

        if ($this->findRawByEmail($email) !== null) {
            return [
                'ok' => false,
                'status' => 409,
                'error_code' => 'DUPLICATE_EMAIL',
                'message' => "An account with email '{$email}' already exists.",
                'errors' => ['email' => "Email '{$email}' is already registered."],
            ];
        }

        if ($studentId !== null && $this->findRawByStudentId($studentId) !== null) {
            return [
                'ok' => false,
                'status' => 409,
                'error_code' => 'DUPLICATE_STUDENT_ID',
                'message' => "An account with student ID '{$studentId}' already exists.",
                'errors' => ['student_id' => "Student ID '{$studentId}' is already registered."],
            ];
        }

        $created = $this->insertUser([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'google_id' => null,
            'auth_provider' => 'local',
            'avatar_url' => null,
            'full_name' => $fullName,
            'role' => $role,
            'graduation_year' => $graduationYear,
            'department_id' => $departmentId,
            'student_id' => $studentId,
            'company' => $company,
            'job_title' => $jobTitle,
        ]);

        $sanitized = $this->sanitizeUser($created);
        $token = $this->tokenService->issueToken($sanitized);

        return [
            'ok' => true,
            'status' => 201,
            'user' => $sanitized,
            'token' => $token,
        ];
    }

    /**
     * Google OAuth 2.0 Registration / Account Linking / Sign-In:
     *  1. Verify email_verified === true
     *  2. Match by google_id -> log in existing user (200)
     *  3. Match by verified email -> link google_id & log in (200, account_linked: true)
     *  4. Otherwise create a new account (auth_provider='google', password_hash=NULL) -> 201 Created
     *  5. Issue the same application HS256 Bearer access token
     *
     * @return array{ok:bool, status:int, is_new_user?:bool, account_linked?:bool, user?:array, token?:array, error_code?:string, message?:string}
     */
    public function registerOrLoginWithGoogle(array $googleProfile, ?string $requestedRole = null): array {
        $googleId = trim((string)($googleProfile['google_id'] ?? ''));
        $email = strtolower(trim((string)($googleProfile['email'] ?? '')));
        $emailVerified = !empty($googleProfile['email_verified']);
        $fullName = trim((string)($googleProfile['full_name'] ?? ''));
        $avatarUrl = trim((string)($googleProfile['avatar_url'] ?? '')) ?: null;

        if ($googleId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'GOOGLE_PROFILE_INVALID',
                'message' => 'Google profile is missing required identifier or email address.',
            ];
        }

        if (!$emailVerified) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'GOOGLE_EMAIL_UNVERIFIED',
                'message' => 'Google email address must be verified to register or sign in.',
            ];
        }

        $role = strtolower(trim((string)($requestedRole ?? UserRole::ALUMNI->value)));
        if ($role === '') {
            $role = UserRole::ALUMNI->value;
        }
        $allowedPublicRoles = UserRole::selfRegistrationRoles();
        if (!in_array($role, $allowedPublicRoles, true)) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Self-registration role must be one of: ' . implode(', ', $allowedPublicRoles) . '.',
            ];
        }

        // 1. Existing account by google_id
        $existingByGoogle = $this->findRawByGoogleId($googleId);
        if ($existingByGoogle !== null) {
            if (isset($existingByGoogle['is_active']) && !$existingByGoogle['is_active']) {
                return [
                    'ok' => false,
                    'status' => 403,
                    'error_code' => 'ACCOUNT_INACTIVE',
                    'message' => 'This account is currently suspended or inactive.',
                ];
            }
            $sanitized = $this->sanitizeUser($existingByGoogle);
            return [
                'ok' => true,
                'status' => 200,
                'is_new_user' => false,
                'account_linked' => false,
                'user' => $sanitized,
                'token' => $this->tokenService->issueToken($sanitized),
            ];
        }

        // 2. Existing account by verified email -> link Google account safely
        $existingByEmail = $this->findRawByEmail($email);
        if ($existingByEmail !== null) {
            if (isset($existingByEmail['is_active']) && !$existingByEmail['is_active']) {
                return [
                    'ok' => false,
                    'status' => 403,
                    'error_code' => 'ACCOUNT_INACTIVE',
                    'message' => 'This account is currently suspended or inactive.',
                ];
            }

            $linked = $this->linkGoogleAccount((int)$existingByEmail['id'], $googleId, $avatarUrl);
            $sanitized = $this->sanitizeUser($linked ?? $existingByEmail);
            return [
                'ok' => true,
                'status' => 200,
                'is_new_user' => false,
                'account_linked' => true,
                'user' => $sanitized,
                'token' => $this->tokenService->issueToken($sanitized),
            ];
        }

        // 3. New Google OAuth user account
        $username = $this->generateUniqueUsername($email, $fullName);
        $created = $this->insertUser([
            'username' => $username,
            'email' => $email,
            'password_hash' => null,
            'google_id' => $googleId,
            'auth_provider' => 'google',
            'avatar_url' => $avatarUrl,
            'full_name' => $fullName !== '' ? $fullName : $username,
            'role' => $role,
            'graduation_year' => null,
            'department_id' => 1,
            'student_id' => null,
            'company' => null,
            'job_title' => null,
        ]);

        $sanitized = $this->sanitizeUser($created);
        return [
            'ok' => true,
            'status' => 201,
            'is_new_user' => true,
            'account_linked' => false,
            'user' => $sanitized,
            'token' => $this->tokenService->issueToken($sanitized),
        ];
    }

    /**
     * Authenticate a user by username or email + password and issue an HS256 Bearer token.
     */
    public function login(string $identifier, string $password): array {
        $cleanIdentifier = strtolower(trim($identifier));
        if ($cleanIdentifier === '' || $password === '') {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => "Both 'username' (or 'email') and 'password' are required.",
            ];
        }

        $record = str_contains($cleanIdentifier, '@')
            ? $this->findRawByEmail($cleanIdentifier)
            : $this->findRawByUsername($cleanIdentifier);

        if ($record === null) {
            $record = $this->findRawByEmail($cleanIdentifier) ?? $this->findRawByUsername($cleanIdentifier);
        }

        if ($record !== null && empty($record['password_hash']) && ($record['auth_provider'] ?? '') === 'google') {
            return [
                'ok' => false,
                'status' => 401,
                'error_code' => 'OAUTH_ACCOUNT_ONLY',
                'message' => 'This account is registered with Google Sign-In. Please continue with Google.',
            ];
        }

        if ($record === null || empty($record['password_hash']) || !password_verify($password, (string)$record['password_hash'])) {
            return [
                'ok' => false,
                'status' => 401,
                'error_code' => 'INVALID_CREDENTIALS',
                'message' => 'Invalid username/email or password.',
            ];
        }

        if (isset($record['is_active']) && !$record['is_active']) {
            return [
                'ok' => false,
                'status' => 403,
                'error_code' => 'ACCOUNT_INACTIVE',
                'message' => 'This account is currently suspended or inactive.',
            ];
        }

        $sanitized = $this->sanitizeUser($record);
        $token = $this->tokenService->issueToken($sanitized);

        return [
            'ok' => true,
            'status' => 200,
            'user' => $sanitized,
            'token' => $token,
        ];
    }

    public function findById(int $id): ?array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('
                    SELECT u.*, d.name AS department_name
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.id = :id
                    LIMIT 1
                ');
                $stmt->execute(['id' => $id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ? $this->sanitizeUser($row) : null;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        $store = $this->readFallbackStore();
        foreach ($store as $item) {
            if ((int)($item['id'] ?? 0) === $id) {
                return $this->sanitizeUser($item);
            }
        }
        return null;
    }

    /**
     * Admin RBAC: List all registered users (sanitized, without password_hash) with optional filtering.
     */
    public function listUsers(array $filters = []): array {
        $pdo = $this->getReadyPdo();
        $users = [];
        if ($pdo) {
            try {
                $stmt = $pdo->query('
                    SELECT u.*, d.name AS department_name
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    ORDER BY u.id ASC
                ');
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $users = array_map(fn(array $r): array => $this->sanitizeUser($r), $rows);
            } catch (\Throwable $e) {
                $users = array_map(fn(array $r): array => $this->sanitizeUser($r), $this->readFallbackStore());
            }
        } else {
            $users = array_map(fn(array $r): array => $this->sanitizeUser($r), $this->readFallbackStore());
        }

        if (!empty($filters['role'])) {
            $role = strtolower(trim((string)$filters['role']));
            $users = array_filter($users, static fn(array $u): bool => ($u['role'] ?? '') === $role);
        }
        if (isset($filters['status']) && $filters['status'] !== '') {
            $wantActive = in_array(strtolower((string)$filters['status']), ['active', '1', 'true'], true);
            $users = array_filter($users, static fn(array $u): bool => (bool)($u['is_active'] ?? true) === $wantActive);
        }
        if (!empty($filters['search'])) {
            $q = strtolower(trim((string)$filters['search']));
            $users = array_filter($users, static fn(array $u): bool =>
                str_contains(strtolower((string)($u['username'] ?? '')), $q)
                || str_contains(strtolower((string)($u['email'] ?? '')), $q)
                || str_contains(strtolower((string)($u['full_name'] ?? '')), $q)
            );
        }

        return array_values($users);
    }

    /**
     * Admin RBAC: Update a user's assigned role (e.g., promote to faculty, moderator, or admin).
     *
     * @return array{ok:bool, status:int, user?:array, error_code?:string, message?:string}
     */
    public function updateUserRole(int $userId, string $newRole, ?int $actingUserId = null): array {
        $normalized = strtolower(trim($newRole));
        $roleEnum = UserRole::tryFrom($normalized);
        if ($roleEnum === null) {
            $validRoles = array_map(static fn(UserRole $r): string => $r->value, UserRole::cases());
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'INVALID_ROLE',
                'message' => "Invalid role '{$newRole}'. Allowed roles: " . implode(', ', $validRoles) . '.',
            ];
        }

        if ($actingUserId !== null && $actingUserId === $userId && $roleEnum !== UserRole::ADMIN) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'CANNOT_DEMOTE_SELF',
                'message' => 'Administrators cannot demote their own active admin account.',
            ];
        }

        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $check = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
                $check->execute(['id' => $userId]);
                if (!$check->fetch(PDO::FETCH_ASSOC)) {
                    return [
                        'ok' => false,
                        'status' => 404,
                        'error_code' => 'USER_NOT_FOUND',
                        'message' => "User #{$userId} not found.",
                    ];
                }

                $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
                $stmt->execute([
                    'role' => $roleEnum->value,
                    'id' => $userId,
                ]);
                $updated = $this->findById($userId);
                return [
                    'ok' => true,
                    'status' => 200,
                    'user' => $updated ?? [],
                ];
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        $store = $this->readFallbackStore();
        foreach ($store as &$item) {
            if ((int)($item['id'] ?? 0) === $userId) {
                $item['role'] = $roleEnum->value;
                $item['updated_at'] = gmdate('Y-m-d H:i:s');
                $this->writeFallbackStore($store);
                return [
                    'ok' => true,
                    'status' => 200,
                    'user' => $this->sanitizeUser($item),
                ];
            }
        }

        return [
            'ok' => false,
            'status' => 404,
            'error_code' => 'USER_NOT_FOUND',
            'message' => "User #{$userId} not found.",
        ];
    }

    /**
     * Admin RBAC: Activate or deactivate (suspend) a user account.
     *
     * @return array{ok:bool, status:int, user?:array, error_code?:string, message?:string}
     */
    public function updateUserStatus(int $userId, bool $isActive, ?int $actingUserId = null): array {
        if ($actingUserId !== null && $actingUserId === $userId && !$isActive) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'CANNOT_DEACTIVATE_SELF',
                'message' => 'Administrators cannot deactivate their own active account.',
            ];
        }

        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $check = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
                $check->execute(['id' => $userId]);
                if (!$check->fetch(PDO::FETCH_ASSOC)) {
                    return [
                        'ok' => false,
                        'status' => 404,
                        'error_code' => 'USER_NOT_FOUND',
                        'message' => "User #{$userId} not found.",
                    ];
                }

                $stmt = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
                $stmt->execute([
                    'is_active' => $isActive ? 1 : 0,
                    'id' => $userId,
                ]);
                return [
                    'ok' => true,
                    'status' => 200,
                    'user' => $this->findById($userId) ?? [],
                ];
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        $store = $this->readFallbackStore();
        foreach ($store as &$item) {
            if ((int)($item['id'] ?? 0) === $userId) {
                $item['is_active'] = $isActive;
                $item['updated_at'] = gmdate('Y-m-d H:i:s');
                $this->writeFallbackStore($store);
                return [
                    'ok' => true,
                    'status' => 200,
                    'user' => $this->sanitizeUser($item),
                ];
            }
        }

        return [
            'ok' => false,
            'status' => 404,
            'error_code' => 'USER_NOT_FOUND',
            'message' => "User #{$userId} not found.",
        ];
    }

    /**
     * Admin RBAC: Permanently delete a user account (guards against self-deletion).
     *
     * @return array{ok:bool, status:int, error_code?:string, message?:string}
     */
    public function deleteUser(int $userId, ?int $actingUserId = null): array {
        if ($actingUserId !== null && $actingUserId === $userId) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'CANNOT_DELETE_SELF',
                'message' => 'Administrators cannot delete their own active account.',
            ];
        }

        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute(['id' => $userId]);
                if ($stmt->rowCount() === 0) {
                    return [
                        'ok' => false,
                        'status' => 404,
                        'error_code' => 'USER_NOT_FOUND',
                        'message' => "User #{$userId} not found.",
                    ];
                }
                return ['ok' => true, 'status' => 200];
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        $store = $this->readFallbackStore();
        $before = count($store);
        $store = array_values(array_filter($store, static fn(array $u): bool => (int)($u['id'] ?? 0) !== $userId));
        if (count($store) === $before) {
            return [
                'ok' => false,
                'status' => 404,
                'error_code' => 'USER_NOT_FOUND',
                'message' => "User #{$userId} not found.",
            ];
        }
        $this->writeFallbackStore($store);
        return ['ok' => true, 'status' => 200];
    }

    /**
     * Validate editable profile update payload (bio, graduation info, contact links, skills).
     *
     * @return array<string, string>
     */
    public function validateProfileUpdateInput(array $input): array {
        $errors = [];

        if (array_key_exists('full_name', $input)) {
            $name = trim((string)$input['full_name']);
            if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
                $errors['full_name'] = "Validation error: 'full_name' must be between 2 and 150 characters.";
            }
        }

        if (array_key_exists('graduation_year', $input) && $input['graduation_year'] !== null && $input['graduation_year'] !== '') {
            if (!is_numeric($input['graduation_year'])) {
                $errors['graduation_year'] = "Validation error: 'graduation_year' must be a valid 4-digit year.";
            } else {
                $yr = (int)$input['graduation_year'];
                if ($yr < 1950 || $yr > 2035) {
                    $errors['graduation_year'] = "Validation error: 'graduation_year' must be between 1950 and 2035.";
                }
            }
        }

        if (array_key_exists('department_id', $input) && $input['department_id'] !== null && $input['department_id'] !== '') {
            if (!is_numeric($input['department_id']) || (int)$input['department_id'] <= 0) {
                $errors['department_id'] = "Validation error: 'department_id' must be a positive integer.";
            }
        }

        foreach (['company' => 150, 'job_title' => 150, 'location' => 120, 'phone' => 30] as $field => $maxLen) {
            if (array_key_exists($field, $input) && $input[$field] !== null) {
                if (mb_strlen(trim((string)$input[$field])) > $maxLen) {
                    $errors[$field] = "Validation error: '{$field}' must not exceed {$maxLen} characters.";
                }
            }
        }

        if (array_key_exists('bio', $input) && $input['bio'] !== null) {
            if (mb_strlen(trim((string)$input['bio'])) > 1000) {
                $errors['bio'] = "Validation error: 'bio' must not exceed 1000 characters.";
            }
        }

        foreach (['linkedin_url', 'github_url', 'website_url'] as $urlField) {
            if (array_key_exists($urlField, $input) && trim((string)($input[$urlField] ?? '')) !== '') {
                $url = trim((string)$input[$urlField]);
                if (
                    mb_strlen($url) > 255
                    || !filter_var($url, FILTER_VALIDATE_URL)
                    || (!str_starts_with(strtolower($url), 'http://') && !str_starts_with(strtolower($url), 'https://'))
                ) {
                    $errors[$urlField] = "Validation error: '{$urlField}' must be a valid http(s) URL (max 255 chars).";
                }
            }
        }

        if (array_key_exists('skills', $input) && $input['skills'] !== null && !is_array($input['skills']) && !is_string($input['skills'])) {
            $errors['skills'] = "Validation error: 'skills' must be an array of strings or comma-separated string.";
        }

        return $errors;
    }

    /**
     * Update a user's editable profile fields (with optional sync to linked alumni directory record).
     *
     * @return array{ok:bool, status:int, user?:array, error_code?:string, message?:string, errors?:array}
     */
    public function updateUserProfile(int $userId, array $input): array {
        $errors = $this->validateProfileUpdateInput($input);
        if (!empty($errors)) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => (string)reset($errors),
                'errors' => $errors,
            ];
        }

        $existing = $this->findById($userId);
        if ($existing === null) {
            return [
                'ok' => false,
                'status' => 404,
                'error_code' => 'USER_NOT_FOUND',
                'message' => "User #{$userId} not found.",
            ];
        }

        $normalizedSkills = null;
        $hasSkills = array_key_exists('skills', $input);
        if ($hasSkills) {
            if (is_array($input['skills'])) {
                $normalizedSkills = array_values(array_filter(array_map(
                    static fn($s): string => trim((string)$s),
                    $input['skills']
                ), static fn(string $s): bool => $s !== ''));
            } elseif (is_string($input['skills'])) {
                $normalizedSkills = array_values(array_filter(array_map(
                    static fn($s): string => trim($s),
                    explode(',', $input['skills'])
                ), static fn(string $s): bool => $s !== ''));
            } else {
                $normalizedSkills = [];
            }
        }

        $allowedScalar = [
            'full_name', 'graduation_year', 'department_id', 'company',
            'job_title', 'bio', 'location', 'linkedin_url', 'github_url',
            'website_url', 'phone',
        ];

        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $fields = [];
                $params = ['id' => $userId];

                foreach ($allowedScalar as $col) {
                    if (!array_key_exists($col, $input)) {
                        continue;
                    }
                    $val = $input[$col];
                    if ($col === 'graduation_year' || $col === 'department_id') {
                        $params[$col] = ($val === '' || $val === null) ? null : (int)$val;
                    } else {
                        $trimmed = trim((string)($val ?? ''));
                        $params[$col] = ($trimmed === '' && $col !== 'full_name') ? null : $trimmed;
                    }
                    $fields[] = "`{$col}` = :{$col}";
                }

                if ($hasSkills) {
                    $fields[] = '`skills` = :skills';
                    $params['skills'] = json_encode($normalizedSkills, JSON_UNESCAPED_UNICODE);
                }

                if (!empty($fields)) {
                    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id');
                    $stmt->execute($params);
                }

                // Keep linked alumni directory profile synchronized when present
                if (!empty($existing['alumni_id'])) {
                    $alumniSync = [];
                    if (array_key_exists('full_name', $input)) $alumniSync['full_name'] = trim((string)$input['full_name']);
                    if (array_key_exists('graduation_year', $input) && $input['graduation_year'] !== null && $input['graduation_year'] !== '') {
                        $alumniSync['cohort'] = (int)$input['graduation_year'];
                    }
                    if (array_key_exists('company', $input)) $alumniSync['company'] = trim((string)($input['company'] ?? ''));
                    if (array_key_exists('job_title', $input)) $alumniSync['role'] = trim((string)($input['job_title'] ?? ''));
                    if (array_key_exists('location', $input)) $alumniSync['location'] = trim((string)($input['location'] ?? ''));
                    if ($hasSkills) $alumniSync['skills'] = $normalizedSkills;

                    if (!empty($alumniSync)) {
                        (new AlumniService())->update((int)$existing['alumni_id'], $alumniSync);
                    }
                }

                return [
                    'ok' => true,
                    'status' => 200,
                    'user' => $this->findById($userId) ?? $existing,
                ];
            } catch (\Throwable $e) {
                // Fall through to standby store
            }
        }

        $store = $this->readFallbackStore();
        foreach ($store as &$item) {
            if ((int)($item['id'] ?? 0) === $userId) {
                foreach ($allowedScalar as $col) {
                    if (!array_key_exists($col, $input)) {
                        continue;
                    }
                    $val = $input[$col];
                    if ($col === 'graduation_year' || $col === 'department_id') {
                        $item[$col] = ($val === '' || $val === null) ? null : (int)$val;
                    } else {
                        $trimmed = trim((string)($val ?? ''));
                        $item[$col] = ($trimmed === '' && $col !== 'full_name') ? null : $trimmed;
                    }
                }
                if ($hasSkills) {
                    $item['skills'] = $normalizedSkills;
                }
                $item['updated_at'] = gmdate('Y-m-d H:i:s');
                $this->writeFallbackStore($store);
                return [
                    'ok' => true,
                    'status' => 200,
                    'user' => $this->sanitizeUser($item),
                ];
            }
        }

        return [
            'ok' => false,
            'status' => 404,
            'error_code' => 'USER_NOT_FOUND',
            'message' => "User #{$userId} not found.",
        ];
    }

    /**
     * List available academic departments for the Registration UI dropdown.
     */
    public function getDepartments(): array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->query('SELECT id, name, faculty, code FROM departments ORDER BY id ASC');
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    return array_map(static fn(array $r): array => [
                        'id' => (int)$r['id'],
                        'name' => (string)$r['name'],
                        'faculty' => (string)$r['faculty'],
                        'code' => (string)$r['code'],
                    ], $rows);
                }
            } catch (\Throwable $e) {
                // Fall back to static list
            }
        }

        return [
            ['id' => 1, 'name' => 'Management Information Systems (YBS)', 'faculty' => 'Faculty of Economics', 'code' => 'MIS-YBS'],
            ['id' => 2, 'name' => 'Computer Engineering', 'faculty' => 'Faculty of Engineering', 'code' => 'CENG'],
            ['id' => 3, 'name' => 'Industrial Engineering', 'faculty' => 'Faculty of Engineering', 'code' => 'IE'],
            ['id' => 4, 'name' => 'Software Engineering', 'faculty' => 'Faculty of Engineering', 'code' => 'SE'],
        ];
    }

    private function insertUser(array $data): array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            // Optionally link to existing alumni profile if email matches
            $alumniId = null;
            try {
                $alumniStmt = $pdo->prepare('SELECT id FROM alumni WHERE email = :email LIMIT 1');
                $alumniStmt->execute(['email' => $data['email']]);
                $foundAlumni = $alumniStmt->fetch(PDO::FETCH_ASSOC);
                if ($foundAlumni && isset($foundAlumni['id'])) {
                    $alumniId = (int)$foundAlumni['id'];
                }
            } catch (\Throwable $e) {
                $alumniId = null;
            }

            // Verify department exists to avoid FK failure if seeders haven't run yet
            $departmentId = $data['department_id'];
            if ($departmentId !== null) {
                try {
                    $deptCheck = $pdo->prepare('SELECT id FROM departments WHERE id = :id LIMIT 1');
                    $deptCheck->execute(['id' => $departmentId]);
                    if (!$deptCheck->fetch(PDO::FETCH_ASSOC)) {
                        $departmentId = null;
                    }
                } catch (\Throwable $e) {
                    $departmentId = null;
                }
            }

            $stmt = $pdo->prepare('
                INSERT INTO users (
                    username, email, password_hash, google_id, auth_provider, avatar_url, full_name, role,
                    graduation_year, department_id, student_id, company, job_title, alumni_id, is_active
                ) VALUES (
                    :username, :email, :password_hash, :google_id, :auth_provider, :avatar_url, :full_name, :role,
                    :graduation_year, :department_id, :student_id, :company, :job_title, :alumni_id, 1
                )
            ');
            $stmt->execute([
                'username' => $data['username'],
                'email' => $data['email'],
                'password_hash' => $data['password_hash'],
                'google_id' => $data['google_id'] ?? null,
                'auth_provider' => $data['auth_provider'] ?? 'local',
                'avatar_url' => $data['avatar_url'] ?? null,
                'full_name' => $data['full_name'],
                'role' => $data['role'],
                'graduation_year' => $data['graduation_year'],
                'department_id' => $departmentId,
                'student_id' => $data['student_id'],
                'company' => $data['company'],
                'job_title' => $data['job_title'],
                'alumni_id' => $alumniId,
            ]);

            $newId = (int)$pdo->lastInsertId();
            $fetch = $pdo->prepare('
                SELECT u.*, d.name AS department_name
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.id = :id
                LIMIT 1
            ');
            $fetch->execute(['id' => $newId]);
            $row = $fetch->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        // Standby fallback store
        $store = $this->readFallbackStore();
        $nextId = empty($store) ? 1 : (max(array_map(static fn($u) => (int)($u['id'] ?? 0), $store)) + 1);
        $now = gmdate('Y-m-d H:i:s');
        $departments = $this->getDepartments();
        $deptName = null;
        foreach ($departments as $d) {
            if ((int)$d['id'] === (int)($data['department_id'] ?? 0)) {
                $deptName = $d['name'];
                break;
            }
        }

        $record = [
            'id' => $nextId,
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'google_id' => $data['google_id'] ?? null,
            'auth_provider' => $data['auth_provider'] ?? 'local',
            'avatar_url' => $data['avatar_url'] ?? null,
            'full_name' => $data['full_name'],
            'role' => $data['role'],
            'graduation_year' => $data['graduation_year'],
            'department_id' => $data['department_id'],
            'department_name' => $deptName,
            'student_id' => $data['student_id'],
            'company' => $data['company'],
            'job_title' => $data['job_title'],
            'alumni_id' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $store[] = $record;
        $this->writeFallbackStore($store);
        return $record;
    }

    private function linkGoogleAccount(int $userId, string $googleId, ?string $avatarUrl): ?array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('
                    UPDATE users
                    SET google_id = :google_id,
                        avatar_url = COALESCE(avatar_url, :avatar_url)
                    WHERE id = :id
                ');
                $stmt->execute([
                    'google_id' => $googleId,
                    'avatar_url' => $avatarUrl,
                    'id' => $userId,
                ]);
                $fetch = $pdo->prepare('
                    SELECT u.*, d.name AS department_name
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.id = :id
                    LIMIT 1
                ');
                $fetch->execute(['id' => $userId]);
                $row = $fetch->fetch(PDO::FETCH_ASSOC);
                return $row ?: null;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        $store = $this->readFallbackStore();
        foreach ($store as &$item) {
            if ((int)($item['id'] ?? 0) === $userId) {
                $item['google_id'] = $googleId;
                if (empty($item['avatar_url']) && $avatarUrl !== null) {
                    $item['avatar_url'] = $avatarUrl;
                }
                $item['updated_at'] = gmdate('Y-m-d H:i:s');
                $this->writeFallbackStore($store);
                return $item;
            }
        }
        return null;
    }

    private function generateUniqueUsername(string $email, string $fullName): string {
        $localPart = explode('@', $email)[0] ?? $fullName;
        $base = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '.', $localPart) ?? 'user');
        $base = trim(preg_replace('/\.{2,}/', '.', $base) ?? 'user', '._-');
        if (strlen($base) < 3) {
            $base = 'alumni.' . bin2hex(random_bytes(3));
        }
        $base = substr($base, 0, 42);

        $candidate = $base;
        $suffix = 1;
        while ($this->findRawByUsername($candidate) !== null) {
            $candidate = substr($base, 0, 42) . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    private function findRawByGoogleId(string $googleId): ?array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('
                    SELECT u.*, d.name AS department_name
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.google_id = :google_id
                    LIMIT 1
                ');
                $stmt->execute(['google_id' => $googleId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ?: null;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        foreach ($this->readFallbackStore() as $item) {
            if ((string)($item['google_id'] ?? '') === $googleId) {
                return $item;
            }
        }
        return null;
    }

    private function findRawByUsername(string $username): ?array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('
                    SELECT u.*, d.name AS department_name
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE LOWER(u.username) = :username
                    LIMIT 1
                ');
                $stmt->execute(['username' => strtolower($username)]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ?: null;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        foreach ($this->readFallbackStore() as $item) {
            if (strtolower((string)($item['username'] ?? '')) === strtolower($username)) {
                return $item;
            }
        }
        return null;
    }

    private function findRawByEmail(string $email): ?array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('
                    SELECT u.*, d.name AS department_name
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE LOWER(u.email) = :email
                    LIMIT 1
                ');
                $stmt->execute(['email' => strtolower($email)]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ?: null;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        foreach ($this->readFallbackStore() as $item) {
            if (strtolower((string)($item['email'] ?? '')) === strtolower($email)) {
                return $item;
            }
        }
        return null;
    }

    private function findRawByStudentId(string $studentId): ?array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE student_id = :student_id LIMIT 1');
                $stmt->execute(['student_id' => $studentId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return $row ?: null;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        foreach ($this->readFallbackStore() as $item) {
            if ((string)($item['student_id'] ?? '') === $studentId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Strip sensitive password_hash and normalize types for API responses.
     */
    private function sanitizeUser(array $record): array {
        $roleValue = (string)($record['role'] ?? UserRole::ALUMNI->value);
        $roleEnum = UserRole::tryFrom($roleValue) ?? UserRole::ALUMNI;

        return [
            'id' => (int)($record['id'] ?? 0),
            'username' => (string)($record['username'] ?? ''),
            'email' => (string)($record['email'] ?? ''),
            'full_name' => (string)($record['full_name'] ?? ''),
            'role' => $roleEnum->value,
            'role_label' => $roleEnum->label(),
            'permissions' => $roleEnum->permissions(),
            'auth_provider' => (string)($record['auth_provider'] ?? 'local'),
            'google_linked' => !empty($record['google_id']),
            'avatar_url' => $record['avatar_url'] ?? null,
            'graduation_year' => isset($record['graduation_year']) && $record['graduation_year'] !== null
                ? (int)$record['graduation_year']
                : null,
            'department_id' => isset($record['department_id']) && $record['department_id'] !== null
                ? (int)$record['department_id']
                : null,
            'department' => $record['department_name'] ?? null,
            'student_id' => $record['student_id'] ?? null,
            'company' => $record['company'] ?? null,
            'job_title' => $record['job_title'] ?? null,
            'bio' => $record['bio'] ?? null,
            'location' => $record['location'] ?? null,
            'linkedin_url' => $record['linkedin_url'] ?? null,
            'github_url' => $record['github_url'] ?? null,
            'website_url' => $record['website_url'] ?? null,
            'phone' => $record['phone'] ?? null,
            'skills' => is_array($record['skills'] ?? null)
                ? $record['skills']
                : (is_string($record['skills'] ?? null) ? (json_decode($record['skills'], true) ?: []) : []),
            'alumni_id' => isset($record['alumni_id']) && $record['alumni_id'] !== null
                ? (int)$record['alumni_id']
                : null,
            'is_active' => isset($record['is_active']) ? (bool)$record['is_active'] : true,
            'created_at' => $record['created_at'] ?? null,
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
                    CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) NOT NULL UNIQUE,
                        email VARCHAR(150) NOT NULL UNIQUE,
                        password_hash VARCHAR(255) NULL,
                        google_id VARCHAR(255) NULL UNIQUE,
                        auth_provider ENUM('local', 'google') NOT NULL DEFAULT 'local',
                        avatar_url VARCHAR(512) NULL,
                        full_name VARCHAR(150) NOT NULL,
                        role ENUM('alumni', 'student', 'faculty', 'employer', 'moderator', 'admin') NOT NULL DEFAULT 'alumni',
                        graduation_year INT NULL,
                        department_id INT NULL,
                        student_id VARCHAR(50) NULL UNIQUE,
                        company VARCHAR(150) NULL,
                        job_title VARCHAR(150) NULL,
                        alumni_id INT NULL,
                        is_active BOOLEAN NOT NULL DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_users_role (role),
                        INDEX idx_users_auth_provider (auth_provider),
                        INDEX idx_users_graduation_year (graduation_year),
                        INDEX idx_users_department (department_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");

                // Ensure Google OAuth columns exist if upgrading an older users table
                $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
                if (is_array($cols)) {
                    if (!in_array('google_id', $cols, true)) {
                        $pdo->exec("ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL");
                        $pdo->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL UNIQUE AFTER password_hash");
                    }
                    if (!in_array('auth_provider', $cols, true)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN auth_provider ENUM('local', 'google') NOT NULL DEFAULT 'local' AFTER google_id");
                    }
                    if (!in_array('avatar_url', $cols, true)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(512) NULL AFTER auth_provider");
                    }
                    if (!in_array('bio', $cols, true)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER job_title");
                    }
                    if (!in_array('location', $cols, true)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN location VARCHAR(120) NULL AFTER bio");
                    }
                    if (!in_array('linkedin_url', $cols, true)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN linkedin_url VARCHAR(255) NULL AFTER location");
                    }
                    if (!in_array('github_url', $cols, true)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN github_url VARCHAR(255) NULL AFTER linkedin_url");
                    }
                    if (!in_array('website_url', $cols, true)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN website_url VARCHAR(255) NULL AFTER github_url");
                    }
                    if (!in_array('phone', $cols, true)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER website_url");
                    }
                    if (!in_array('skills', $cols, true)) {
                        $pdo->exec("ALTER TABLE users ADD COLUMN skills JSON NULL AFTER phone");
                    }
                }
                self::$schemaChecked = true;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return $pdo;
    }

    private function readFallbackStore(): array {
        if (!file_exists($this->fallbackStorePath)) {
            return [];
        }
        $raw = @file_get_contents($this->fallbackStorePath);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeFallbackStore(array $store): void {
        @file_put_contents(
            $this->fallbackStorePath,
            json_encode($store, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
