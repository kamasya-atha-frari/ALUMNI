<?php
declare(strict_types=1);

namespace AlumniIstü\Controllers;

use AlumniIstü\Core\RequestContext;
use AlumniIstü\Core\Response;
use AlumniIstü\Models\Application;
use AlumniIstü\Models\JobPosting;
use AlumniIstü\Services\AlumniService;
use AlumniIstü\Services\AnnouncementService;
use AlumniIstü\Services\AuthService;

/**
 * AdminApiController - Protected Administrative Control Plane for AlumniIstü.
 * Every admin route is guarded by AuthMiddleware + RoleMiddleware::requireRole(['admin']).
 */
class AdminApiController {
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
     * GET /api/v1/admin/overview
     * Executive system telemetry and entity counts for the Admin Panel dashboard.
     */
    public function overview(): void {
        $users = $this->authService->listUsers();
        $alumni = $this->alumniService->getAll();
        $jobs = JobPosting::all();
        $applications = Application::all();
        $announcements = $this->announcementService->getAll();

        $roleCounts = [];
        $activeUsers = 0;
        $googleUsers = 0;
        foreach ($users as $u) {
            $r = (string)($u['role'] ?? 'alumni');
            $roleCounts[$r] = ($roleCounts[$r] ?? 0) + 1;
            if (!empty($u['is_active'])) {
                $activeUsers++;
            }
            if (($u['auth_provider'] ?? '') === 'google' || !empty($u['google_linked'])) {
                $googleUsers++;
            }
        }

        $activeJobs = count(array_filter($jobs, static fn(JobPosting $j): bool => $j->status === 'active'));
        $publishedAnnouncements = count(array_filter($announcements, static fn(array $a): bool => ($a['status'] ?? '') === 'published'));

        Response::json([
            'status' => 'success',
            'admin' => RequestContext::user(),
            'data' => [
                'users_total' => count($users),
                'users_active' => $activeUsers,
                'users_inactive' => count($users) - $activeUsers,
                'users_google_linked' => $googleUsers,
                'role_distribution' => $roleCounts,
                'alumni_total' => count($alumni),
                'mentors_active' => count(array_filter($alumni, static fn(array $a): bool => !empty($a['is_mentor']))),
                'job_postings_total' => count($jobs),
                'job_postings_active' => $activeJobs,
                'applications_total' => count($applications),
                'announcements_total' => count($announcements),
                'announcements_published' => $publishedAnnouncements,
            ],
        ], 200);
    }

    /**
     * GET /api/v1/admin/users
     * List all registered users with optional role/status/search filters.
     */
    public function listUsers(): void {
        $filters = [
            'role' => $_GET['role'] ?? null,
            'status' => $_GET['status'] ?? null,
            'search' => $_GET['search'] ?? ($_GET['q'] ?? null),
        ];
        $users = $this->authService->listUsers($filters);

        $roleCounts = [];
        foreach ($users as $u) {
            $r = (string)($u['role'] ?? 'alumni');
            $roleCounts[$r] = ($roleCounts[$r] ?? 0) + 1;
        }

        Response::json([
            'status' => 'success',
            'requested_by' => RequestContext::user(),
            'count' => count($users),
            'role_distribution' => $roleCounts,
            'data' => $users,
        ], 200);
    }

    /**
     * PATCH /api/v1/admin/users/{id}/role
     */
    public function updateUserRole(string $id): void {
        $input = $this->getRequestPayload();
        $newRole = trim((string)($input['role'] ?? ''));
        if ($newRole === '') {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => "Validation error: 'role' is required.",
                'errors' => ['role' => "Validation error: 'role' is required."],
            ], 422);
            return;
        }

        $result = $this->authService->updateUserRole((int)$id, $newRole, RequestContext::userId());
        if (!$result['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $result['status'],
                'error_code' => $result['error_code'] ?? 'ROLE_UPDATE_FAILED',
                'message' => $result['message'] ?? 'Failed to update user role.',
            ], $result['status']);
            return;
        }

        Response::json([
            'status' => 'success',
            'message' => "User #{$id} role updated to '{$newRole}'.",
            'updated_by' => RequestContext::user(),
            'data' => $result['user'],
        ], 200);
    }

    /**
     * PATCH /api/v1/admin/users/{id}/status
     * Activate or deactivate a user account.
     */
    public function updateUserStatus(string $id): void {
        $input = $this->getRequestPayload();
        if (!array_key_exists('is_active', $input)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => "Validation error: boolean field 'is_active' is required.",
                'errors' => ['is_active' => "Validation error: 'is_active' must be true or false."],
            ], 422);
            return;
        }

        $isActive = filter_var($input['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isActive === null) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => "Validation error: 'is_active' must be a boolean value.",
                'errors' => ['is_active' => "Validation error: 'is_active' must be true or false."],
            ], 422);
            return;
        }

        $result = $this->authService->updateUserStatus((int)$id, $isActive, RequestContext::userId());
        if (!$result['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $result['status'],
                'error_code' => $result['error_code'] ?? 'STATUS_UPDATE_FAILED',
                'message' => $result['message'] ?? 'Failed to update user status.',
            ], $result['status']);
            return;
        }

        $label = $isActive ? 'activated' : 'deactivated';
        Response::json([
            'status' => 'success',
            'message' => "User #{$id} has been {$label}.",
            'updated_by' => RequestContext::user(),
            'data' => $result['user'],
        ], 200);
    }

    /**
     * DELETE /api/v1/admin/users/{id}
     */
    public function deleteUser(string $id): void {
        $result = $this->authService->deleteUser((int)$id, RequestContext::userId());
        if (!$result['ok']) {
            Response::json([
                'status' => 'error',
                'code' => $result['status'],
                'error_code' => $result['error_code'] ?? 'DELETE_USER_FAILED',
                'message' => $result['message'] ?? 'Failed to delete user.',
            ], $result['status']);
            return;
        }

        Response::json([
            'status' => 'success',
            'message' => "User #{$id} deleted permanently.",
            'deleted_by' => RequestContext::user(),
        ], 200);
    }

    /**
     * POST /api/v1/admin/alumni
     * Create an alumni directory profile with admin validation.
     */
    public function createAlumni(): void {
        $input = $this->getRequestPayload();
        $name = trim((string)($input['full_name'] ?? $input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $errors = [];

        if ($name === '') {
            $errors['full_name'] = "Validation error: 'full_name' is required.";
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Validation error: a valid 'email' is required.";
        }

        if (!empty($errors)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => (string)reset($errors),
                'errors' => $errors,
            ], 422);
            return;
        }

        $created = $this->alumniService->create($input);
        Response::json([
            'status' => 'success',
            'message' => 'Alumni profile created by administrator.',
            'data' => $created,
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/admin/alumni/{id}
     */
    public function updateAlumni(string $id): void {
        $targetId = (int)$id;
        if (!$this->alumniService->findById($targetId)) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'ALUMNI_NOT_FOUND',
                'message' => "Alumni profile #{$id} not found.",
            ], 404);
            return;
        }

        $input = $this->getRequestPayload();
        if (isset($input['email']) && !filter_var((string)$input['email'], FILTER_VALIDATE_EMAIL)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => "Validation error: Invalid 'email' format.",
            ], 422);
            return;
        }

        $updated = $this->alumniService->update($targetId, $input);
        Response::json([
            'status' => 'success',
            'message' => "Alumni profile #{$id} updated successfully.",
            'data' => $updated,
        ], 200);
    }

    /**
     * DELETE /api/v1/admin/alumni/{id}
     */
    public function deleteAlumni(string $id): void {
        $targetId = (int)$id;
        if (!$this->alumniService->findById($targetId)) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'ALUMNI_NOT_FOUND',
                'message' => "Alumni profile #{$id} not found.",
            ], 404);
            return;
        }

        $this->alumniService->delete($targetId);
        Response::json([
            'status' => 'success',
            'message' => "Alumni profile #{$id} removed by administrator.",
        ], 200);
    }

    /**
     * GET /api/v1/admin/job-postings
     */
    public function listJobs(): void {
        $postings = JobPosting::all();
        Response::json([
            'status' => 'success',
            'count' => count($postings),
            'data' => array_map(static fn(JobPosting $j): array => $j->toArray(), $postings),
        ], 200);
    }

    /**
     * PATCH /api/v1/admin/job-postings/{id}
     * Moderate job posting fields or lifecycle status (active / closed / draft).
     */
    public function updateJob(string $id): void {
        $posting = JobPosting::find((int)$id);
        if (!$posting) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'JOB_NOT_FOUND',
                'message' => "Job posting #{$id} not found.",
            ], 404);
            return;
        }

        $input = $this->getRequestPayload();
        if (isset($input['status'])) {
            $status = strtolower(trim((string)$input['status']));
            if (!in_array($status, ['active', 'closed', 'draft'], true)) {
                Response::json([
                    'status' => 'error',
                    'code' => 422,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => "Validation error: 'status' must be one of active, closed, draft.",
                ], 422);
                return;
            }
            $input['status'] = $status;
        }

        $jobCtrl = new JobPostingApiController();
        $errors = $jobCtrl->validateJobPostingInput($input, true);
        if (!empty($errors)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => (string)reset($errors),
                'errors' => $errors,
            ], 422);
            return;
        }

        $posting->update($input);
        Response::json([
            'status' => 'success',
            'message' => "Job posting #{$id} updated by administrator.",
            'data' => $posting->toArray(),
        ], 200);
    }

    /**
     * DELETE /api/v1/admin/job-postings/{id}
     */
    public function deleteJob(string $id): void {
        $posting = JobPosting::find((int)$id);
        if (!$posting) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'JOB_NOT_FOUND',
                'message' => "Job posting #{$id} not found.",
            ], 404);
            return;
        }

        $posting->delete();
        Response::json([
            'status' => 'success',
            'message' => "Job posting #{$id} deleted by administrator.",
        ], 200);
    }

    /**
     * GET /api/v1/announcements (Public published notices)
     */
    public function publicAnnouncements(): void {
        $items = $this->announcementService->getAll(['status' => 'published']);
        Response::json([
            'status' => 'success',
            'count' => count($items),
            'data' => $items,
        ], 200);
    }

    /**
     * GET /api/v1/admin/announcements (All announcements including drafts)
     */
    public function listAnnouncements(): void {
        $items = $this->announcementService->getAll([
            'status' => $_GET['status'] ?? null,
            'category' => $_GET['category'] ?? null,
        ]);
        Response::json([
            'status' => 'success',
            'count' => count($items),
            'data' => $items,
        ], 200);
    }

    /**
     * POST /api/v1/admin/announcements
     */
    public function createAnnouncement(): void {
        $input = $this->getRequestPayload();
        $errors = $this->announcementService->validateInput($input, false);
        if (!empty($errors)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => (string)reset($errors),
                'errors' => $errors,
            ], 422);
            return;
        }

        $created = $this->announcementService->create($input, RequestContext::user());
        Response::json([
            'status' => 'success',
            'message' => 'Announcement published successfully.',
            'data' => $created,
        ], 201);
    }

    /**
     * PUT/PATCH /api/v1/admin/announcements/{id}
     */
    public function updateAnnouncement(string $id): void {
        $targetId = (int)$id;
        if (!$this->announcementService->findById($targetId)) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'ANNOUNCEMENT_NOT_FOUND',
                'message' => "Announcement #{$id} not found.",
            ], 404);
            return;
        }

        $input = $this->getRequestPayload();
        $errors = $this->announcementService->validateInput($input, true);
        if (!empty($errors)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'error_code' => 'VALIDATION_ERROR',
                'message' => (string)reset($errors),
                'errors' => $errors,
            ], 422);
            return;
        }

        $updated = $this->announcementService->update($targetId, $input);
        Response::json([
            'status' => 'success',
            'message' => "Announcement #{$id} updated successfully.",
            'data' => $updated,
        ], 200);
    }

    /**
     * DELETE /api/v1/admin/announcements/{id}
     */
    public function deleteAnnouncement(string $id): void {
        if (!$this->announcementService->delete((int)$id)) {
            Response::json([
                'status' => 'error',
                'code' => 404,
                'error_code' => 'ANNOUNCEMENT_NOT_FOUND',
                'message' => "Announcement #{$id} not found.",
            ], 404);
            return;
        }

        Response::json([
            'status' => 'success',
            'message' => "Announcement #{$id} deleted successfully.",
        ], 200);
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
