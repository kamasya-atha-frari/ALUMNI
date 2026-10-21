<?php
declare(strict_types=1);

namespace AlumniNexus\Controllers;

use AlumniNexus\Core\Response;
use AlumniNexus\Models\Application;
use AlumniNexus\Models\JobPosting;
use AlumniNexus\Models\Alumni;

class ApplicationApiController {
    /**
     * READ: List all job applications
     * GET /api/v1/applications
     */
    public function index(): void {
        $filters = [
            'job_posting_id' => $_GET['job_posting_id'] ?? null,
            'user_id'        => $_GET['user_id'] ?? null,
            'status'         => $_GET['status'] ?? null,
            'search'         => $_GET['search'] ?? ($_GET['q'] ?? null),
        ];

        $applications = Application::all(array_filter($filters, fn($v) => $v !== null && $v !== ''));

        $data = array_map(function (Application $app) {
            $arr = $app->toArray();
            $job = $app->jobPosting();
            $arr['job_title'] = $job ? $job->title : null;
            $arr['company_name'] = $job ? $job->company_name : null;
            return $arr;
        }, $applications);

        Response::json([
            'status' => 'success',
            'count' => count($data),
            'filters_applied' => array_filter($filters, fn($v) => $v !== null && $v !== ''),
            'data' => $data
        ]);
    }

    /**
     * READ: Retrieve single application
     * GET /api/v1/applications/{id}
     */
    public function show(string $id): void {
        $app = Application::find((int)$id);
        if (!$app) {
            Response::json(['status' => 'error', 'message' => "Application #{$id} not found"], 404);
            return;
        }

        $result = $app->toArray();
        $job = $app->jobPosting();
        $result['job_posting'] = $job ? $job->toArray() : null;
        $applicant = $app->applicant();
        $result['applicant_profile'] = $applicant ? [
            'id' => $applicant->id,
            'full_name' => $applicant->full_name,
            'email' => $applicant->email,
            'cohort' => $applicant->cohort,
            'skills' => $applicant->skills
        ] : null;

        Response::json([
            'status' => 'success',
            'data' => $result
        ]);
    }

    /**
     * CREATE: Submit a new application
     * POST /api/v1/applications
     */
    public function create(): void {
        $input = $this->getRequestPayload();

        $jobId = isset($input['job_posting_id']) ? (int)$input['job_posting_id'] : (isset($input['job_id']) ? (int)$input['job_id'] : 0);
        $name = $input['applicant_name'] ?? ($input['name'] ?? null);
        $email = $input['applicant_email'] ?? ($input['email'] ?? null);

        if ($jobId <= 0 || empty($name) || empty($email)) {
            Response::json([
                'status' => 'error',
                'message' => 'Validation error: job_posting_id, applicant_name, and applicant_email are required.'
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

        $job = JobPosting::find($jobId);
        if (!$job) {
            Response::json(['status' => 'error', 'message' => "Job posting #{$jobId} does not exist"], 404);
            return;
        }

        $input['job_posting_id'] = $jobId;
        $app = Application::create($input);

        Response::json([
            'status' => 'success',
            'message' => 'Application submitted successfully',
            'data' => $app->toArray()
        ], 201);
    }

    /**
     * UPDATE: Update application attributes (e.g. status transition)
     * PUT/PATCH /api/v1/applications/{id}
     */
    public function update(string $id): void {
        $targetId = (int)$id;
        $app = Application::find($targetId);
        if (!$app) {
            Response::json(['status' => 'error', 'message' => "Application #{$id} not found"], 404);
            return;
        }

        $input = $this->getRequestPayload();

        if (isset($input['status'])) {
            $validStatuses = ['submitted', 'under_review', 'interview', 'accepted', 'rejected'];
            if (!in_array($input['status'], $validStatuses, true)) {
                Response::json([
                    'status' => 'error',
                    'message' => 'Validation error: status must be one of: ' . implode(', ', $validStatuses)
                ], 422);
                return;
            }
        }

        $success = $app->update($input);

        if ($success) {
            Response::json([
                'status' => 'success',
                'message' => 'Application updated successfully',
                'data' => $app->toArray()
            ]);
        } else {
            Response::json(['status' => 'error', 'message' => 'Failed to update application'], 500);
        }
    }

    /**
     * DELETE: Remove application
     * DELETE /api/v1/applications/{id}
     */
    public function delete(string $id): void {
        $targetId = (int)$id;
        $app = Application::find($targetId);
        if (!$app) {
            Response::json(['status' => 'error', 'message' => "Application #{$id} not found"], 404);
            return;
        }

        $success = $app->delete();
        if ($success) {
            Response::json([
                'status' => 'success',
                'message' => "Application #{$id} deleted successfully"
            ]);
        } else {
            Response::json(['status' => 'error', 'message' => "Failed to delete application #{$id}"], 500);
        }
    }

    /**
     * NESTED ROUTE: Get applications for a specific job posting
     * GET /api/v1/job-postings/{id}/applications
     */
    public function byJobPosting(string $id): void {
        $jobId = (int)$id;
        $job = JobPosting::find($jobId);
        if (!$job) {
            Response::json(['status' => 'error', 'message' => "Job posting #{$id} not found"], 404);
            return;
        }

        $applications = Application::findByJobPostingId($jobId);

        Response::json([
            'status' => 'success',
            'job_posting' => [
                'id' => $job->id,
                'title' => $job->title,
                'company_name' => $job->company_name,
                'status' => $job->status
            ],
            'count' => count($applications),
            'data' => array_map(fn($a) => $a->toArray(), $applications)
        ]);
    }

    /**
     * NESTED ROUTE: Submit application directly to a specific job posting
     * POST /api/v1/job-postings/{id}/applications
     */
    public function createForJobPosting(string $id): void {
        $jobId = (int)$id;
        $job = JobPosting::find($jobId);
        if (!$job) {
            Response::json(['status' => 'error', 'message' => "Job posting #{$id} not found"], 404);
            return;
        }

        $input = $this->getRequestPayload();
        $input['job_posting_id'] = $jobId;

        $name = $input['applicant_name'] ?? ($input['name'] ?? null);
        $email = $input['applicant_email'] ?? ($input['email'] ?? null);

        if (empty($name) || empty($email)) {
            Response::json([
                'status' => 'error',
                'message' => 'Validation error: Both applicant_name and applicant_email are required.'
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

        $app = Application::create($input);

        Response::json([
            'status' => 'success',
            'message' => "Application submitted successfully for position: {$job->title}",
            'data' => $app->toArray()
        ], 201);
    }

    /**
     * NESTED ROUTE: Get applications submitted by a user/alumni
     * GET /api/v1/users/{id}/applications
     */
    public function byUser(string $id): void {
        $userId = (int)$id;
        $user = Alumni::find($userId);
        if (!$user) {
            Response::json(['status' => 'error', 'message' => "User / Alumni #{$id} not found"], 404);
            return;
        }

        $applications = Application::findByUserId($userId);

        $data = array_map(function (Application $app) {
            $arr = $app->toArray();
            $job = $app->jobPosting();
            $arr['job_title'] = $job ? $job->title : null;
            $arr['company_name'] = $job ? $job->company_name : null;
            return $arr;
        }, $applications);

        Response::json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email
            ],
            'count' => count($data),
            'data' => $data
        ]);
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
