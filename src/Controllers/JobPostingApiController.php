<?php
declare(strict_types=1);

namespace AlumniIstü\Controllers;

use AlumniIstü\Core\Response;
use AlumniIstü\Models\JobPosting;
use AlumniIstü\Models\Company;
use AlumniIstü\Models\Alumni;

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
            Response::json([
                'status' => 'error',
                'code' => 404,
                'message' => "Job posting #{$id} not found",
                'reason' => "The requested job posting #{$id} does not exist in the database."
            ], 404);
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

        $errors = $this->validateJobPostingInput($input, false);
        if (!empty($errors)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'message' => reset($errors),
                'errors' => $errors
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
            Response::json(['status' => 'error', 'code' => 404, 'message' => "Job posting #{$id} not found"], 404);
            return;
        }

        $input = $this->getRequestPayload();
        $errors = $this->validateJobPostingInput($input, true);
        if (!empty($errors)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'message' => reset($errors),
                'errors' => $errors
            ], 422);
            return;
        }

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
            Response::json([
                'status' => 'error',
                'code' => 404,
                'message' => "Job posting #{$id} not found",
                'reason' => "Cannot delete job posting #{$id} because it does not exist in the database."
            ], 404);
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
            Response::json([
                'status' => 'error',
                'code' => 404,
                'message' => "Company #{$id} not found",
                'reason' => "The company entity #{$id} does not exist in the database."
            ], 404);
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
            Response::json(['status' => 'error', 'code' => 404, 'message' => "Company #{$id} not found"], 404);
            return;
        }

        $input = $this->getRequestPayload();
        $input['company_id'] = $companyId;
        $input['company_name'] = $company->name;

        $errors = $this->validateJobPostingInput($input, false);
        if (!empty($errors)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'message' => reset($errors),
                'errors' => $errors
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
            Response::json(['status' => 'error', 'code' => 404, 'message' => "User / Alumni #{$id} not found"], 404);
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
            Response::json(['status' => 'error', 'code' => 404, 'message' => "User / Alumni #{$id} not found"], 404);
            return;
        }

        $input = $this->getRequestPayload();
        $input['user_id'] = $userId;
        if (empty($input['company_name']) && empty($input['company']) && !empty($user->company)) {
            $input['company_name'] = $user->company;
        }

        $errors = $this->validateJobPostingInput($input, false);
        if (!empty($errors)) {
            Response::json([
                'status' => 'error',
                'code' => 422,
                'message' => reset($errors),
                'errors' => $errors
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

    /**
     * Validate JobPosting input payload according to ddd.md rules (Whiteboard Item 2):
     * - title: required, max 120 characters
     * - company / company_name: required
     * - salary / salary_range: optional
     * - description: optional
     * - city / location: optional
     *
     * @param array $input
     * @param bool $isUpdate When true, allows partial updates
     * @return array<string, string> Validation errors map (empty if valid)
     */
    public function validateJobPostingInput(array $input, bool $isUpdate = false): array {
        $errors = [];

        // 1. Title validation: required, max 120 chars
        if (!$isUpdate || array_key_exists('title', $input)) {
            $title = isset($input['title']) ? trim((string)$input['title']) : '';
            if ($title === '') {
                $errors['title'] = "Validation error: 'title' is required.";
            } elseif (mb_strlen($title) > 120) {
                $errors['title'] = "Validation error: 'title' must not exceed 120 characters.";
            }
        }

        // 2. Company validation: required (company, company_name, or valid company_id)
        if (!$isUpdate || (array_key_exists('company', $input) || array_key_exists('company_name', $input) || array_key_exists('company_id', $input))) {
            $company = isset($input['company']) ? trim((string)$input['company']) : '';
            $companyName = isset($input['company_name']) ? trim((string)$input['company_name']) : '';
            $companyId = isset($input['company_id']) && $input['company_id'] !== '' ? (int)$input['company_id'] : null;

            if ($company === '' && $companyName === '' && empty($companyId)) {
                $errors['company'] = "Validation error: 'company' (or company_name) is required.";
            }
        }

        // 3. Salary, Description, City/Location are explicitly optional!

        return $errors;
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
