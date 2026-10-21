<?php
declare(strict_types=1);

namespace AlumniNexus\Models;

use AlumniNexus\Core\Database;
use PDO;
use JsonSerializable;

class Application implements JsonSerializable {
    public ?int $id;
    public int $job_posting_id;
    public ?int $user_id;
    public string $applicant_name;
    public string $applicant_email;
    public ?string $resume_url;
    public ?string $cover_letter;
    public string $status;
    public ?string $created_at;
    public ?string $updated_at;

    private static array $mockApplications = [
        [
            'id' => 1,
            'job_posting_id' => 1,
            'user_id' => 4,
            'applicant_name' => 'Aylin Yildiz',
            'applicant_email' => 'aylin.yildiz@ogr.iu.edu.tr',
            'resume_url' => 'https://drive.google.com/file/d/aylin-cv',
            'cover_letter' => 'Passionate about secure cloud and big data engineering pipelines.',
            'status' => 'under_review',
            'created_at' => '2026-02-15 14:20:00',
            'updated_at' => '2026-02-15 14:20:00'
        ],
        [
            'id' => 2,
            'job_posting_id' => 2,
            'user_id' => null,
            'applicant_name' => 'Caner Erkin',
            'applicant_email' => 'caner.erkin@ogr.iu.edu.tr',
            'resume_url' => 'https://linkedin.com/in/caner-erkin',
            'cover_letter' => 'Recent MIS graduate with proven product analytics and SQL experience.',
            'status' => 'submitted',
            'created_at' => '2026-02-18 16:45:00',
            'updated_at' => '2026-02-18 16:45:00'
        ]
    ];

    public function __construct(array $attributes = []) {
        $this->id = isset($attributes['id']) ? (int)$attributes['id'] : null;
        $this->job_posting_id = isset($attributes['job_posting_id']) ? (int)$attributes['job_posting_id'] : 0;
        $this->user_id = isset($attributes['user_id']) && $attributes['user_id'] !== '' ? (int)$attributes['user_id'] : null;
        $this->applicant_name = $attributes['applicant_name'] ?? ($attributes['name'] ?? 'Candidate');
        $this->applicant_email = $attributes['applicant_email'] ?? ($attributes['email'] ?? 'applicant@ogr.iu.edu.tr');
        $this->resume_url = $attributes['resume_url'] ?? null;
        $this->cover_letter = $attributes['cover_letter'] ?? null;
        $this->status = $attributes['status'] ?? 'submitted';
        $this->created_at = $attributes['created_at'] ?? null;
        $this->updated_at = $attributes['updated_at'] ?? null;
    }

    /**
     * Relationship: An Application belongs to a Job Posting
     */
    public function jobPosting(): ?JobPosting {
        if ($this->job_posting_id > 0) {
            return JobPosting::find($this->job_posting_id);
        }
        return null;
    }

    /**
     * Relationship: An Application belongs to an Alumni / User (Applicant)
     */
    public function applicant(): ?Alumni {
        if ($this->user_id !== null) {
            return Alumni::find($this->user_id);
        }
        return null;
    }

    /**
     * Alias for applicant()
     */
    public function user(): ?Alumni {
        return $this->applicant();
    }

    /**
     * Retrieve all applications with optional filtering
     */
    public static function all(array $filters = []): array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $sql = 'SELECT * FROM applications WHERE 1=1';
                $params = [];

                if (!empty($filters['job_posting_id'])) {
                    $sql .= ' AND job_posting_id = :job_posting_id';
                    $params['job_posting_id'] = (int)$filters['job_posting_id'];
                }
                if (!empty($filters['user_id'])) {
                    $sql .= ' AND user_id = :user_id';
                    $params['user_id'] = (int)$filters['user_id'];
                }
                if (!empty($filters['status'])) {
                    $sql .= ' AND status = :status';
                    $params['status'] = $filters['status'];
                }
                if (!empty($filters['search'])) {
                    $sql .= ' AND (applicant_name LIKE :search OR applicant_email LIKE :search OR cover_letter LIKE :search)';
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
        $data = self::$mockApplications;
        if (!empty($filters['job_posting_id'])) {
            $data = array_filter($data, fn($a) => (int)$a['job_posting_id'] === (int)$filters['job_posting_id']);
        }
        if (!empty($filters['user_id'])) {
            $data = array_filter($data, fn($a) => (int)($a['user_id'] ?? 0) === (int)$filters['user_id']);
        }
        if (!empty($filters['status'])) {
            $data = array_filter($data, fn($a) => $a['status'] === $filters['status']);
        }
        if (!empty($filters['search'])) {
            $s = strtolower($filters['search']);
            $data = array_filter($data, fn($a) =>
                str_contains(strtolower($a['applicant_name']), $s) ||
                str_contains(strtolower($a['applicant_email']), $s)
            );
        }

        return array_map(fn($row) => new self($row), array_values($data));
    }

    /**
     * Find application by ID
     */
    public static function find(int $id): ?self {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = :id LIMIT 1');
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

        foreach (self::$mockApplications as $row) {
            if ($row['id'] === $id) {
                return new self($row);
            }
        }
        return null;
    }

    /**
     * Find applications by job posting ID
     */
    public static function findByJobPostingId(int $jobPostingId): array {
        return self::all(['job_posting_id' => $jobPostingId]);
    }

    /**
     * Find applications by user ID
     */
    public static function findByUserId(int $userId): array {
        return self::all(['user_id' => $userId]);
    }

    /**
     * Create a new application
     */
    public static function create(array $data): self {
        $jobPostingId = (int)($data['job_posting_id'] ?? 0);
        $userId = isset($data['user_id']) && $data['user_id'] !== '' ? (int)$data['user_id'] : null;
        $name = $data['applicant_name'] ?? ($data['name'] ?? 'Candidate');
        $email = $data['applicant_email'] ?? ($data['email'] ?? 'applicant@ogr.iu.edu.tr');
        $resumeUrl = $data['resume_url'] ?? null;
        $coverLetter = $data['cover_letter'] ?? null;
        $status = $data['status'] ?? 'submitted';

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $sql = '
                    INSERT INTO applications (
                        job_posting_id, user_id, applicant_name, applicant_email,
                        resume_url, cover_letter, status
                    ) VALUES (
                        :job_posting_id, :user_id, :applicant_name, :applicant_email,
                        :resume_url, :cover_letter, :status
                    )
                ';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'job_posting_id'  => $jobPostingId,
                    'user_id'         => $userId,
                    'applicant_name'  => $name,
                    'applicant_email' => $email,
                    'resume_url'      => $resumeUrl,
                    'cover_letter'    => $coverLetter,
                    'status'          => $status
                ]);
                $newId = (int)$pdo->lastInsertId();
                return self::find($newId) ?? new self(array_merge($data, ['id' => $newId]));
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $newId = count(self::$mockApplications) > 0 ? (max(array_column(self::$mockApplications, 'id')) + 1) : 1;
        $record = [
            'id' => $newId,
            'job_posting_id' => $jobPostingId,
            'user_id' => $userId,
            'applicant_name' => $name,
            'applicant_email' => $email,
            'resume_url' => $resumeUrl,
            'cover_letter' => $coverLetter,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        self::$mockApplications[] = $record;
        return new self($record);
    }

    /**
     * Update application attributes
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
                $allowed = ['status', 'cover_letter', 'resume_url', 'applicant_name', 'applicant_email'];

                foreach ($allowed as $col) {
                    if (array_key_exists($col, $data)) {
                        $fields[] = "`{$col}` = :{$col}";
                        $params[$col] = $data[$col];
                    }
                }

                if (!empty($fields)) {
                    $sql = 'UPDATE applications SET ' . implode(', ', $fields) . ' WHERE id = :id';
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

        foreach (self::$mockApplications as &$item) {
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
     * Delete application
     */
    public function delete(): bool {
        if ($this->id === null) {
            return false;
        }

        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('DELETE FROM applications WHERE id = :id');
                $stmt->execute(['id' => $this->id]);
                return $stmt->rowCount() > 0;
            } catch (\Throwable $e) {
                return false;
            }
        }

        $initial = count(self::$mockApplications);
        self::$mockApplications = array_values(array_filter(self::$mockApplications, fn($a) => $a['id'] !== $this->id));
        return count(self::$mockApplications) < $initial;
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'job_posting_id' => $this->job_posting_id,
            'user_id' => $this->user_id,
            'applicant_name' => $this->applicant_name,
            'applicant_email' => $this->applicant_email,
            'resume_url' => $this->resume_url,
            'cover_letter' => $this->cover_letter,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }
}
