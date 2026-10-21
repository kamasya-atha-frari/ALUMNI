<?php
declare(strict_types=1);

namespace AlumniNexus\Controllers;

use AlumniNexus\Core\Response;
use AlumniNexus\Models\Company;

class CompanyApiController {
    /**
     * READ: List all companies
     * GET /api/v1/companies
     */
    public function index(): void {
        $companies = Company::all();
        $data = array_map(function (Company $c) {
            $arr = $c->toArray();
            $arr['open_jobs_count'] = count($c->jobPostings());
            return $arr;
        }, $companies);

        Response::json([
            'status' => 'success',
            'count' => count($data),
            'data' => $data
        ]);
    }

    /**
     * READ: Retrieve single company
     * GET /api/v1/companies/{id}
     */
    public function show(string $id): void {
        $company = Company::find((int)$id);
        if (!$company) {
            Response::json(['status' => 'error', 'message' => "Company #{$id} not found"], 404);
            return;
        }

        $result = $company->toArray();
        $jobs = $company->jobPostings();
        $result['job_postings'] = array_map(fn($j) => $j->toArray(), $jobs);
        $result['open_jobs_count'] = count($jobs);

        Response::json([
            'status' => 'success',
            'data' => $result
        ]);
    }

    /**
     * CREATE: Register a new hiring company
     * POST /api/v1/companies
     */
    public function create(): void {
        $input = $this->getRequestPayload();

        $name = $input['name'] ?? null;
        if (empty($name)) {
            Response::json([
                'status' => 'error',
                'message' => 'Validation error: Company name is required.'
            ], 422);
            return;
        }

        $company = Company::create($input);

        Response::json([
            'status' => 'success',
            'message' => 'Company created successfully',
            'data' => $company->toArray()
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
