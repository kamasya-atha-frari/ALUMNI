<?php
declare(strict_types=1);

namespace AlumniNexus\Models;

use AlumniNexus\Core\Database;
use PDO;
use JsonSerializable;

class JobPosting implements JsonSerializable {
    public ?int $id;
    public string $title;
    public ?int $company_id;
    public string $company_name;
    public ?int $user_id;
    public string $description;
    public ?string $requirements;
    public ?string $location;
    public string $job_type;
    public ?string $salary_range;
    public ?string $experience_level;
    public string $status;
    public ?string $created_at;
    public ?string $updated_at;

    private static array $mockJobPostings = [
        [
            'id' => 1,
            'title' => 'Senior Cloud & Data Engineer',
            'company_id' => 2,
            'company_name' => 'Turkcell Tech',
            'user_id' => 1,
            'description' => 'Design scalable real-time streaming architectures using Spark and Kafka.',
            'requirements' => 'Strong proficiency in Python, SQL, Docker, Kafka, and Databricks.',
            'location' => 'Istanbul, Turkey (Hybrid)',
            'job_type' => 'full-time',
            'salary_range' => '₺95,000 - ₺130,000 / month',
            'experience_level' => 'Senior',
            'status' => 'active',
            'created_at' => '2026-02-01 10:00:00',
            'updated_at' => '2026-02-01 10:00:00'
        ],
        [
            'id' => 2,
            'title' => 'Associate Product Manager',
            'company_id' => 1,
            'company_name' => 'Trendyol Group',
            'user_id' => 2,
            'description' => 'Drive next-generation e-commerce search, recommendation, and checkout experiences.',
            'requirements' => 'Bachelor in MIS or Computer Engineering. Strong analytical and SQL skills.',
            'location' => 'Istanbul, Turkey (On-site)',
            'job_type' => 'full-time',
            'salary_range' => '₺60,000 - ₺85,000 / month',
            'experience_level' => 'Entry',
            'status' => 'active',
            'created_at' => '2026-02-05 11:30:00',
            'updated_at' => '2026-02-05 11:30:00'
        ],
        [
            'id' => 3,
            'title' => 'Graduate DevOps Intern',
            'company_id' => 3,
            'company_name' => 'Getir Global',
            'user_id' => 3,
            'description' => 'Automate multi-region AWS infrastructure and container pipelines.',
            'requirements' => 'Basic knowledge of Docker, Linux, CI/CD, and Git.',
            'location' => 'Remote',
            'job_type' => 'internship',
            'salary_range' => '₺25,000 - ₺35,000 / month',
            'experience_level' => 'Entry',
            'status' => 'active',
            'created_at' => '2026-02-10 09:15:00',
            'updated_at' => '2026-02-10 09:15:00'
        ]
    ];

    public function __construct(array $attributes = []) {
        $this->id = isset($attributes['id']) ? (int)$attributes['id'] : null;
        $this->title = $attributes['title'] ?? 'Untitled Position';
        $this->company_id = isset($attributes['company_id']) && $attributes['company_id'] !== '' ? (int)$attributes['company_id'] : null;
        $this->company_name = $attributes['company_name'] ?? ($attributes['company'] ?? 'Tech Partner');
        $this->user_id = isset($attributes['user_id']) && $attributes['user_id'] !== '' ? (int)$attributes['user_id'] : (isset($attributes['posted_by']) ? (int)$attributes['posted_by'] : null);
        $this->description = $attributes['description'] ?? '';
        $this->requirements = $attributes['requirements'] ?? null;
        $this->location = $attributes['location'] ?? 'Istanbul, Turkey';
        $this->job_type = $attributes['job_type'] ?? 'full-time';
        $this->salary_range = $attributes['salary_range'] ?? null;
        $this->experience_level = $attributes['experience_level'] ?? 'Mid-Level';
        $this->status = $attributes['status'] ?? 'active';
        $this->created_at = $attributes['created_at'] ?? null;
        $this->updated_at = $attributes['updated_at'] ?? null;
    }

    /**
     * Relationship: Job Posting belongs to a Company
     */
    public function company(): ?Company {
        if ($this->company_id !== null) {
            return Company::find($this->company_id);
        }
        return null;
    }

    /**
     * Relationship: Job Posting belongs to an Alumni / User (Poster)
     */
    public function postedBy(): ?Alumni {
        if ($this->user_id !== null) {
            return Alumni::find($this->user_id);
        }
        return null;
    }

    /**
     * Alias for postedBy()
     */
    public function user(): ?Alumni {
        return $this->postedBy();
    }

    /**
     * Relationship: Job Posting has many Applications
     */
    public function applications(): array {
        if ($this->id === null) {
            return [];
        }
        return Application::findByJobPostingId($this->id);
    }

    /**
     * Retrieve all job postings with optional filtering
     */
    public static function all(array $filters = []): array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $sql = 'SELECT * FROM job_postings WHERE 1=1';
                $params = [];

                if (!empty($filters['status'])) {
                    $sql .= ' AND status = :status';
                    $params['status'] = $filters['status'];
                }
                if (!empty($filters['job_type'])) {
                    $sql .= ' AND job_type = :job_type';
                    $params['job_type'] = $filters['job_type'];
                }
                if (!empty($filters['company_id'])) {
                    $sql .= ' AND company_id = :company_id';
                    $params['company_id'] = (int)$filters['company_id'];
                }
                if (!empty($filters['user_id'])) {
                    $sql .= ' AND user_id = :user_id';
                    $params['user_id'] = (int)$filters['user_id'];
                }
                if (!empty($filters['search'])) {
                    $sql .= ' AND (title LIKE :search OR company_name LIKE :search OR location LIKE :search OR description LIKE :search)';
                    $params['search'] = '%' . $filters['search'] . '%';
                }

                $sql .= ' ORDER BY created_at DESC, id DESC';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return array_map(fn($row) => new self($row), $rows);
            } catch (\Throwable $e) {
                // fall through to mock
            }
        }

        // In-memory fallback
        $data = self::$mockJobPostings;
        if (!empty($filters['status'])) {
            $data = array_filter($data, fn($j) => $j['status'] === $filters['status']);
        }
        if (!empty($filters['job_type'])) {
            $data = array_filter($data, fn($j) => $j['job_type'] === $filters['job_type']);
        }
        if (!empty($filters['company_id'])) {
            $data = array_filter($data, fn($j) => (int)($j['company_id'] ?? 0) === (int)$filters['company_id']);
        }
        if (!empty($filters['user_id'])) {
            $data = array_filter($data, fn($j) => (int)($j['user_id'] ?? 0) === (int)$filters['user_id']);
        }
        if (!empty($filters['search'])) {
            $s = strtolower($filters['search']);
            $data = array_filter($data, fn($j) =>
                str_contains(strtolower($j['title']), $s) ||
                str_contains(strtolower($j['company_name']), $s) ||
                str_contains(strtolower($j['description']), $s)
            );
        }

        return array_map(fn($row) => new self($row), array_values($data));
    }

    /**
     * Find single job posting by ID
     */
    public static function find(int $id): ?self {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM job_postings WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return new self($row);
                }
                return null;
            } catch (\Throwable $e) {
                // fall through
            }
        }

        foreach (self::$mockJobPostings as $row) {
            if ($row['id'] === $id) {
                return new self($row);
            }
        }
        return null;
    }

    /**
     * Find job postings by company ID
     */
    public static function findByCompanyId(int $companyId): array {
        return self::all(['company_id' => $companyId]);
    }

    /**
     * Find job postings by user ID
     */
    public static function findByUserId(int $userId): array {
        return self::all(['user_id' => $userId]);
    }

    /**
     * Create a new job posting
     */
    public static function create(array $data): self {
        $companyName = $data['company_name'] ?? ($data['company'] ?? null);
        $companyId = isset($data['company_id']) && $data['company_id'] !== '' ? (int)$data['company_id'] : null;

        // If company_id provided but no company_name, look up company name
        if ($companyId && empty($companyName)) {
            $comp = Company::find($companyId);
            if ($comp) {
                $companyName = $comp->name;
            }
        }
        if (empty($companyName)) {
            $companyName = 'Partner Corporation';
        }

        $userId = isset($data['user_id']) && $data['user_id'] !== '' ? (int)$data['user_id'] : (isset($data['posted_by']) ? (int)$data['posted_by'] : null);
        $title = $data['title'] ?? 'Software Professional';
        $description = $data['description'] ?? 'Position description pending.';
        $requirements = $data['requirements'] ?? null;
        $location = $data['location'] ?? 'Istanbul, Turkey';
        $jobType = $data['job_type'] ?? 'full-time';
        $salaryRange = $data['salary_range'] ?? null;
        $experienceLevel = $data['experience_level'] ?? 'Mid-Level';
        $status = $data['status'] ?? 'active';

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $sql = '
                    INSERT INTO job_postings (
                        title, company_id, company_name, user_id, description,
                        requirements, location, job_type, salary_range, experience_level, status
                    ) VALUES (
                        :title, :company_id, :company_name, :user_id, :description,
                        :requirements, :location, :job_type, :salary_range, :experience_level, :status
                    )
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'title' => $title,
                    'company_id' => $companyId,
                    'company_name' => $companyName,
                    'user_id' => $userId,
                    'description' => $description,
                    'requirements' => $requirements,
                    'location' => $location,
                    'job_type' => $jobType,
                    'salary_range' => $salaryRange,
                    'experience_level' => $experienceLevel,
                    'status' => $status
                ]);
                $newId = (int)$pdo->lastInsertId();
                return self::find($newId) ?? new self(array_merge($data, ['id' => $newId, 'company_name' => $companyName]));
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $newId = count(self::$mockJobPostings) > 0 ? (max(array_column(self::$mockJobPostings, 'id')) + 1) : 1;
        $record = [
            'id' => $newId,
            'title' => $title,
            'company_id' => $companyId,
            'company_name' => $companyName,
            'user_id' => $userId,
            'description' => $description,
            'requirements' => $requirements,
            'location' => $location,
            'job_type' => $jobType,
            'salary_range' => $salaryRange,
            'experience_level' => $experienceLevel,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        self::$mockJobPostings[] = $record;
        return new self($record);
    }

    /**
     * Update an existing job posting
     */
    public function update(array $data): bool {
        if ($this->id === null) {
            return false;
        }

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $fields = [];
                $params = ['id' => $this->id];
                $allowed = [
                    'title', 'company_id', 'company_name', 'user_id', 'description',
                    'requirements', 'location', 'job_type', 'salary_range', 'experience_level', 'status'
                ];

                foreach ($allowed as $col) {
                    if (array_key_exists($col, $data)) {
                        $fields[] = "`{$col}` = :{$col}";
                        if ($col === 'company_id' || $col === 'user_id') {
                            $params[$col] = $data[$col] !== null && $data[$col] !== '' ? (int)$data[$col] : null;
                        } else {
                            $params[$col] = $data[$col];
                        }
                    }
                }

                if (!empty($fields)) {
                    $sql = 'UPDATE job_postings SET ' . implode(', ', $fields) . ' WHERE id = :id';
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                }

                $fresh = self::find($this->id);
                if ($fresh) {
                    foreach (get_object_vars($fresh) as $prop => $val) {
                        $this->$prop = $val;
                    }
                }
                return true;
            } catch (\Throwable $e) {
                // fall through
            }
        }

        // Mock update
        foreach (self::$mockJobPostings as &$item) {
            if ($item['id'] === $this->id) {
                foreach ($data as $k => $v) {
                    if (isset($item[$k])) {
                        $item[$k] = $v;
                    }
                    if (property_exists($this, $k)) {
                        $this->$k = $v;
                    }
                }
                $item['updated_at'] = date('Y-m-d H:i:s');
                $this->updated_at = $item['updated_at'];
                return true;
            }
        }
        return false;
    }

    /**
     * Delete job posting
     */
    public function delete(): bool {
        if ($this->id === null) {
            return false;
        }

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('DELETE FROM job_postings WHERE id = :id');
                $stmt->execute(['id' => $this->id]);
                return $stmt->rowCount() > 0;
            } catch (\Throwable $e) {
                return false;
            }
        }

        $initial = count(self::$mockJobPostings);
        self::$mockJobPostings = array_values(array_filter(self::$mockJobPostings, fn($j) => $j['id'] !== $this->id));
        return count(self::$mockJobPostings) < $initial;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'company_id' => $this->company_id,
            'company_name' => $this->company_name,
            'user_id' => $this->user_id,
            'description' => $this->description,
            'requirements' => $this->requirements,
            'location' => $this->location,
            'job_type' => $this->job_type,
            'salary_range' => $this->salary_range,
            'experience_level' => $this->experience_level,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }
}
