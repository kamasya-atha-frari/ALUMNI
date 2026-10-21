<?php
declare(strict_types=1);

namespace AlumniNexus\Models;

use AlumniNexus\Core\Database;
use AlumniNexus\Services\AlumniService;
use PDO;
use JsonSerializable;

class Alumni implements JsonSerializable {
    public ?int $id;
    public string $student_id;
    public string $full_name;
    public string $email;
    public int $cohort;
    public ?int $department_id;
    public ?string $department;
    public ?string $company;
    public ?string $role;
    public ?string $industry;
    public ?string $location;
    public array $skills;
    public bool $is_mentor;
    public int $mentor_capacity;
    public ?string $created_at;
    public ?string $updated_at;

    public function __construct(array $attributes = []) {
        $this->id = isset($attributes['id']) ? (int)$attributes['id'] : null;
        $this->student_id = $attributes['student_id'] ?? '';
        $this->full_name = $attributes['full_name'] ?? $attributes['name'] ?? '';
        $this->email = $attributes['email'] ?? '';
        $this->cohort = isset($attributes['cohort']) ? (int)$attributes['cohort'] : (int)date('Y');
        $this->department_id = isset($attributes['department_id']) ? (int)$attributes['department_id'] : null;
        $this->department = $attributes['department'] ?? null;
        $this->company = $attributes['company'] ?? null;
        $this->role = $attributes['role'] ?? null;
        $this->industry = $attributes['industry'] ?? null;
        $this->location = $attributes['location'] ?? null;
        $this->skills = is_array($attributes['skills'] ?? null) ? $attributes['skills'] : (is_string($attributes['skills'] ?? null) ? (json_decode($attributes['skills'], true) ?: []) : []);
        $this->is_mentor = !empty($attributes['is_mentor']);
        $this->mentor_capacity = isset($attributes['mentor_capacity']) ? (int)$attributes['mentor_capacity'] : 0;
        $this->created_at = $attributes['created_at'] ?? null;
        $this->updated_at = $attributes['updated_at'] ?? null;
    }

    /**
     * Relationship: An Alumni/User has many authored Job Postings
     */
    public function jobPostings(): array {
        if ($this->id === null) {
            return [];
        }
        return JobPosting::findByUserId($this->id);
    }

    /**
     * Relationship: An Alumni/User has many submitted Applications
     */
    public function applications(): array {
        if ($this->id === null) {
            return [];
        }
        return Application::findByUserId($this->id);
    }

    /**
     * Find alumni/user by ID
     */
    public static function find(int $id): ?self {
        $service = new AlumniService();
        $record = $service->findById($id);
        if ($record) {
            return new self($record);
        }
        return null;
    }

    /**
     * Retrieve all alumni records
     */
    public static function all(array $filters = []): array {
        $service = new AlumniService();
        $records = $service->getAll($filters);
        return array_map(fn($r) => new self($r), $records);
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'cohort' => $this->cohort,
            'department_id' => $this->department_id,
            'department' => $this->department,
            'company' => $this->company,
            'role' => $this->role,
            'industry' => $this->industry,
            'location' => $this->location,
            'skills' => $this->skills,
            'is_mentor' => $this->is_mentor,
            'mentor_capacity' => $this->mentor_capacity,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }
}
