<?php
declare(strict_types=1);

namespace AlumniNexus\Controllers;

use AlumniNexus\Core\Response;
use AlumniNexus\Models\JobPosting;
use AlumniNexus\Models\Company;
use AlumniNexus\Models\Alumni;

class JobPostingApiController {
    /**
     * READ: List all job postings
     * GET /api/v1/job-postings
     */
    public function index(): void {
        $filters = [
            'status'     => $_GET['status'] ?? null,
            'job_type'   => $_GET['job_type'] ?? ($_GET['type'] ?? null),
            'company_id' => $_GET['company_id'] ?? null,
            'user_id'    => $_GET['user_id'] ?? null,
            'search'     => $_GET['search'] ?? ($_GET['q'] ?? null),
        ];

        $postings = JobPosting::all(array_filter($filters, fn($v) => $v !== null && $v !== ''));

        $data = array_map(function (JobPosting $j) {
            $arr = $j->toArray();
            $arr['applications_count'] = count($j->applications());
            return $arr;
        }, $postings);

        Response::json([
            'status' => 'success',
            'count' => count($data),
            'filters_applied' => array_filter($filters, fn($v) => $v !== null && $v !== ''),
            'data' => $data
        ]);
    }

    /**
     * READ: Retrieve single job posting
     * GET /api/v1/job-postings/{id}
     */
    public function show(string $id): void {
        $posting = JobPosting::find((int)$id);
        if (!$posting) {
            Response::json(['status' => 'error', 'message' => "Job posting #{$id} not found"], 404);
            return;
        }

        $result = $posting->toArray();
        $company = $posting->company();
        $result['company'] = $company ? $company->toArray() : null;
        $postedBy = $posting->postedBy();
        $result['posted_by_user'] = $postedBy ? [
            'id' => $postedBy->id,
            'full_name' => $postedBy->full_name,
            'email' => $postedBy->email,
            'role' => $postedBy->role
        ] : null;
        $result['applications_count'] = count($posting->applications());

        Response::json([
            'status' => 'success',
            'data' => $result
        ]);
    }

    /**
     * CREATE: Create new job posting
     * POST /api/v1/job-postings
     */
    public function create(): void {
        $input = $this->getRequestPayload();

        $title = $input['title'] ?? null;
        $description = $input['description'] ?? null;

        if (empty($title) || empty($description)) {
            Response::json([
                'status' => 'error',
                'message' => 'Validation error: Both title and description are required.'
            ], 422);
            return;
        }

        $posting = JobPosting::create($input);

        Response::json([
            'status' => 'success',
            'message' => 'Job posting created successfully',
            'data' => $posting->toArray()
        ], 201);
    }

    /**
     * UPDATE: Modify existing job posting
     * PUT/PATCH /api/v1/job-postings/{id}
     */
    public function update(string $id): void {
        $targetId = (int)$id;
        $posting = JobPosting::find($targetId);
        if (!$posting) {
            Response::json(['status' => 'error', 'message' => "Job posting #{$id} not found"], 404);
            return;
        }

        $input = $this->getRequestPayload();
        $success = $posting->update($input);

        if ($success) {
            Response::json([
                'status' => 'success',
                'message' => 'Job posting updated successfully',
                'data' => $posting->toArray()
            ]);
        } else {
            Response::json(['status' => 'error', 'message' => 'Failed to update job posting'], 500);
        }
    }

    /**
     * DELETE: Delete job posting
     * DELETE /api/v1/job-postings/{id}
     */
    public function delete(string $id): void {
        $targetId = (int)$id;
        $posting = JobPosting::find($targetId);
        if (!$posting) {
            Response::json(['status' => 'error', 'message' => "Job posting #{$id} not found"], 404);
            return;
        }

        $success = $posting->delete();
        if ($success) {
            Response::json([
                'status' => 'success',
                'message' => "Job posting #{$id} deleted successfully"
            ]);
        } else {
            Response::json(['status' => 'error', 'message' => "Failed to delete job posting #{$id}"], 500);
        }
    }

    /**
     * NESTED ROUTE: Get job postings for a specific company
     * GET /api/v1/companies/{id}/job-postings
     */
    public function byCompany(string $id): void {
        $companyId = (int)$id;
        $company = Company::find($companyId);
        if (!$company) {
            Response::json(['status' => 'error', 'message' => "Company #{$id} not found"], 404);
            return;
        }

        $postings = JobPosting::findByCompanyId($companyId);

        Response::json([
            'status' => 'success',
            'company' => $company->toArray(),
            'count' => count($postings),
            'data' => array_map(fn($p) => $p->toArray(), $postings)
        ]);
    }

    /**
     * NESTED ROUTE: Create job posting directly under a company
     * POST /api/v1/companies/{id}/job-postings
     */
    public function createForCompany(string $id): void {
        $companyId = (int)$id;
        $company = Company::find($companyId);
        if (!$company) {
            Response::json(['status' => 'error', 'message' => "Company #{$id} not found"], 404);
            return;
        }

        $input = $this->getRequestPayload();
        $input['company_id'] = $companyId;
        $input['company_name'] = $company->name;

        if (empty($input['title']) || empty($input['description'])) {
            Response::json([
                'status' => 'error',
                'message' => 'Validation error: Both title and description are required.'
            ], 422);
            return;
        }

        $posting = JobPosting::create($input);

        Response::json([
            'status' => 'success',
            'message' => "Job posting published for {$company->name}",
            'data' => $posting->toArray()
        ], 201);
    }

    /**
     * NESTED ROUTE: Get job postings posted by a user/alumni
     * GET /api/v1/users/{id}/job-postings
     */
    public function byUser(string $id): void {
        $userId = (int)$id;
        $user = Alumni::find($userId);
        if (!$user) {
            Response::json(['status' => 'error', 'message' => "User / Alumni #{$id} not found"], 404);
            return;
        }

        $postings = JobPosting::findByUserId($userId);

        Response::json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'company' => $user->company,
                'role' => $user->role
            ],
            'count' => count($postings),
            'data' => array_map(fn($p) => $p->toArray(), $postings)
        ]);
    }

    /**
     * NESTED ROUTE: Create job posting published by a user/alumni
     * POST /api/v1/users/{id}/job-postings
     */
    public function createForUser(string $id): void {
        $userId = (int)$id;
        $user = Alumni::find($userId);
        if (!$user) {
            Response::json(['status' => 'error', 'message' => "User / Alumni #{$id} not found"], 404);
            return;
        }

        $input = $this->getRequestPayload();
        $input['user_id'] = $userId;
        if (empty($input['company_name']) && !empty($user->company)) {
            $input['company_name'] = $user->company;
        }

        if (empty($input['title']) || empty($input['description'])) {
            Response::json([
                'status' => 'error',
                'message' => 'Validation error: Both title and description are required.'
            ], 422);
            return;
        }

        $posting = JobPosting::create($input);

        Response::json([
            'status' => 'success',
            'message' => "Job posting published by {$user->full_name}",
            'data' => $posting->toArray()
        ], 201);
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
