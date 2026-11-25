<?php
declare(strict_types=1);

namespace AlumniIstü\Controllers;

use AlumniIstü\Core\RequestContext;
use AlumniIstü\Core\Response;
use AlumniIstü\Domain\Enums\UserRole;
use AlumniIstü\Models\Application;
use AlumniIstü\Models\JobPosting;
use AlumniIstü\Services\AlumniService;
use AlumniIstü\Services\AnnouncementService;
use AlumniIstü\Services\AuthService;

/**
 * UserProfileApiController - Authenticated User Profile & Activity Endpoints with Ownership Authorization.
 *
 * Security Invariants:
 *  - Every route is protected by AuthMiddleware (401 Unauthorized when unauthenticated).
 *  - Regular users may ONLY view/update their own profile (403 OWNERSHIP_VIOLATION on other IDs).
 *  - Privileged fields (`role`, `is_active`, `google_id`, `auth_provider`, `password_hash`) cannot be
 *    mutated through self-service profile endpoints (403 FORBIDDEN_FIELD_MUTATION).
 *  - Administrators (`role === 'admin'`) may view/update any user via /profile/users/{id} or /admin/*.
 */
class UserProfileApiController {
    private const RESTRICTED_SELF_FIELDS = ['role', 'is_active', 'google_id', 'auth_provider', 'password_hash', 'id'];

    private AuthService $authService;
    private AlumniService $alumniService;
    private AnnouncementService $announcementService;

    public function __construct(
        ?AuthService $authService = null,
        ?AlumniService $alumniService = null,
        ?AnnouncementService $announcementService = null
    ) {
        $this->authService = $authService ?? new AuthService();
        $this->alumniService = $alumniService ?? new AlumniService();
        $this->announcementService = $announcementService ?? new AnnouncementService();
    }

    /**
     * GET /api/v1/profile/me (alias: GET /api/profile/me)
     * Retrieve the authenticated user's own editable profile + linked alumni directory entry.
     */
    public function showMe(): void {
        $userId = RequestContext::userId();
        if ($userId === null || $userId <= 0) {
            Response::json([
                'status' => 'error',
                'code' => 401,
                'guardian' => 'Auth Middleware',
                'error_code' => 'TOKEN_MISSING',
                'message' => 'Authentication required.',
            ], 401);
            return;
        }

        $user = $this->authService->findById($userId);
        if ($user === null) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'USER_NOT_FOUND',
                'message' => 'User profile not found.',
            ], 404);
            return;
        }

        $linkedAlumni = !empty($user['alumni_id'])
            ? $this->alumniService->findById((int)$user['alumni_id'])
            : null;

        Response::json([
            'status' => 'success',
            'data' => [
                'user' => $user,
                'linked_alumni' => $linkedAlumni,
            ],
        ], 200);
    }

    /**
     * PUT/PATCH /api/v1/profile/me (alias: PUT/PATCH /api/profile/me)
     * Update the authenticated user's own profile details (bio, graduation info, contact links, skills).
     */
    public function updateMe(): void {
        $userId = RequestContext::userId();
        if ($userId === null || $userId <= 0) {
            Response::json([
                'status' => 'error',
                'code' => 401,
                'guardian' => 'Auth Middleware',
                'error_code' => 'TOKEN_MISSING',
                'message' => 'Authentication required.',
            ], 401);
            return;
        }

        $input = $this->getRequestPayload();
        $forbiddenAttempt = $this->detectRestrictedFields($input);
        if (!empty($forbiddenAttempt)) {
            Response::json([
                'status' => 'error',
                'code' => 403,
                'guardian' => 'Role Authorization Middleware',
                'error' => 'Forbidden',
                'error_code' => 'FORBIDDEN_FIELD_MUTATION',
                'message' => 'Restricted account fields cannot be modified via self-service profile endpoints: ' . implode(', ', $forbiddenAttempt) . '.',
                'restricted_fields' => $forbiddenAttempt,
            ], 403);
            return;
        }

        $result = $this->authService->updateUserProfile($userId, $input);
        if (!$result['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $result['status'],
                'error_code' => $result['error_code'] ?? 'PROFILE_UPDATE_FAILED',
                'message' => $result['message'] ?? 'Profile update failed.',
                'errors' => $result['errors'] ?? [],
            ], $result['status']);
            return;
        }

        Response::json([
            'status' => 'success',
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => $result['user'],
            ],
        ], 200);
    }

    /**
     * GET /api/v1/profile/me/activity (alias: GET /api/profile/me/activity)
     * Return the logged-in user's own submitted job applications, authored job postings, and announcements.
     */
    public function myActivity(): void {
        $userId = RequestContext::userId();
        if ($userId === null || $userId <= 0) {
            Response::json([
                'status' => 'error',
                'code' => 401,
                'guardian' => 'Auth Middleware',
                'error_code' => 'TOKEN_MISSING',
                'message' => 'Authentication required.',
            ], 401);
            return;
        }

        $user = $this->authService->findById($userId);
        if ($user === null) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'USER_NOT_FOUND',
                'message' => 'User account not found.',
            ], 404);
            return;
        }

        $userEmail = strtolower((string)($user['email'] ?? ''));
        $alumniId = !empty($user['alumni_id']) ? (int)$user['alumni_id'] : null;

        // Collect applications owned by this user (by alumni/user ID or matching applicant_email)
        $allApps = Application::all();
        $myApps = [];
        foreach ($allApps as $app) {
            $appArr = $app->toArray();
            $matchesEmail = $userEmail !== '' && strtolower((string)($appArr['applicant_email'] ?? '')) === $userEmail;
            $matchesId = ($alumniId !== null && (int)($appArr['user_id'] ?? 0) === $alumniId)
                || (int)($appArr['user_id'] ?? 0) === $userId;
            if ($matchesEmail || $matchesId) {
                $myApps[] = $appArr;
            }
        }

        // Collect job postings authored by this user
        $allJobs = JobPosting::all();
        $myJobs = [];
        foreach ($allJobs as $job) {
            $jobArr = $job->toArray();
            $posterId = (int)($jobArr['user_id'] ?? 0);
            if (($alumniId !== null && $posterId === $alumniId) || $posterId === $userId) {
                $myJobs[] = $jobArr;
            }
        }

        $announcements = $this->announcementService->getAll(['status' => 'published']);

        Response::json([
            'status' => 'success',
            'data' => [
                'counts' => [
                    'applications' => count($myApps),
                    'job_postings' => count($myJobs),
                    'announcements' => count($announcements),
                ],
                'applications' => $myApps,
                'job_postings' => $myJobs,
                'announcements' => $announcements,
            ],
        ], 200);
    }

    /**
     * GET /api/v1/profile/users/{id} (Ownership-Enforced: own ID or admin)
     */
    public function showById(string $id): void {
        $targetId = (int)$id;
        if (!$this->enforceOwnershipOrAdmin($targetId, 'GET', "/api/v1/profile/users/{$id}")) {
            return;
        }

        $user = $this->authService->findById($targetId);
        if ($user === null) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'USER_NOT_FOUND',
                'message' => "User #{$id} not found.",
            ], 404);
            return;
        }

        Response::json([
            'status' => 'success',
            'data' => ['user' => $user],
        ], 200);
    }

    /**
     * PUT/PATCH /api/v1/profile/users/{id} (Ownership-Enforced: own ID or admin)
     */
    public function updateById(string $id): void {
        $targetId = (int)$id;
        if (!$this->enforceOwnershipOrAdmin($targetId, 'PATCH', "/api/v1/profile/users/{$id}")) {
            return;
        }

        $input = $this->getRequestPayload();
        $isAdmin = RequestContext::userRole() === UserRole::ADMIN->value;
        if (!$isAdmin) {
            $forbiddenAttempt = $this->detectRestrictedFields($input);
            if (!empty($forbiddenAttempt)) {
                Response::json([
                    'status' => 'error',
                    'code' => 403,
                    'guardian' => 'Role Authorization Middleware',
                    'error' => 'Forbidden',
                    'error_code' => 'FORBIDDEN_FIELD_MUTATION',
                    'message' => 'Regular users cannot modify restricted account fields: ' . implode(', ', $forbiddenAttempt) . '.',
                    'restricted_fields' => $forbiddenAttempt,
                ], 403);
                return;
            }
        }

        $result = $this->authService->updateUserProfile($targetId, $input);
        if (!$result['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $result['status'],
                'error_code' => $result['error_code'] ?? 'PROFILE_UPDATE_FAILED',
                'message' => $result['message'] ?? 'Profile update failed.',
                'errors' => $result['errors'] ?? [],
            ], $result['status']);
            return;
        }

        Response::json([
            'status' => 'success',
            'message' => "User #{$id} profile updated successfully.",
            'data' => ['user' => $result['user']],
        ], 200);
    }

    /**
     * Ownership authorization check: allows only the resource owner (sub === targetId) or 'admin'.
     */
    private function enforceOwnershipOrAdmin(int $targetId, string $method, string $path): bool {
        $actingId = RequestContext::userId();
        $actingRole = strtolower((string)(RequestContext::userRole() ?? ''));

        if ($actingId === null || $actingId <= 0) {
            Response::json([
                'status' => 'error',
                'code' => 401,
                'guardian' => 'Auth Middleware',
                'error' => 'Unauthorized',
                'error_code' => 'TOKEN_MISSING',
                'message' => 'Authentication required.',
                'method' => $method,
                'path' => $path,
                'timestamp' => date('c'),
            ], 401);
            return false;
        }

        if ($actingId !== $targetId && $actingRole !== UserRole::ADMIN->value) {
            Response::json([
                'status' => 'error',
                'code' => 403,
                'guardian' => 'Role Authorization Middleware',
                'error' => 'Forbidden',
                'error_code' => 'OWNERSHIP_VIOLATION',
                'message' => 'Access denied. You can only view or modify your own user profile.',
                'reason' => "User #{$actingId} (role '{$actingRole}') attempted to access user #{$targetId}'s private profile.",
                'owner_id' => $targetId,
                'acting_user_id' => $actingId,
                'method' => $method,
                'path' => $path,
                'timestamp' => date('c'),
            ], 403);
            return false;
        }

        return true;
    }

    /**
     * @return string[]
     */
    private function detectRestrictedFields(array $input): array {
        $found = [];
        foreach (self::RESTRICTED_SELF_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $found[] = $field;
            }
        }
        return $found;
    }

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
