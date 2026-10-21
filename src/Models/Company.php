<?php
declare(strict_types=1);

namespace AlumniNexus\Models;

use AlumniNexus\Core\Database;
use PDO;
use JsonSerializable;

class Company implements JsonSerializable {
    public ?int $id;
    public string $name;
    public ?string $industry;
    public ?string $location;
    public ?string $website;
    public ?string $description;
    public ?string $created_at;
    public ?string $updated_at;

    private static array $mockCompanies = [
        [
            'id' => 1,
            'name' => 'Trendyol Group',
            'industry' => 'E-Commerce & Logistics',
            'location' => 'Istanbul, Turkey',
            'website' => 'https://trendyol.com',
            'description' => 'Leading e-commerce and logistics platform in Turkey and Europe.',
            'created_at' => '2026-01-10 10:00:00',
            'updated_at' => '2026-01-10 10:00:00'
        ],
        [
            'id' => 2,
            'name' => 'Turkcell Tech',
            'industry' => 'Telecommunications & Cloud',
            'location' => 'Istanbul, Turkey',
            'website' => 'https://turkcell.com.tr',
            'description' => 'Pioneering telecommunications provider and cloud technology developer.',
            'created_at' => '2026-01-10 10:00:00',
            'updated_at' => '2026-01-10 10:00:00'
        ],
        [
            'id' => 3,
            'name' => 'Getir Global',
            'industry' => 'FinTech & Rapid Delivery',
            'location' => 'Amsterdam, Netherlands',
            'website' => 'https://getir.com',
            'description' => 'Ultra-fast grocery delivery pioneer expanding fintech solutions.',
            'created_at' => '2026-01-10 10:00:00',
            'updated_at' => '2026-01-10 10:00:00'
        ],
        [
            'id' => 4,
            'name' => 'Garanti BBVA Technology',
            'industry' => 'Banking & Digital Security',
            'location' => 'Istanbul, Turkey',
            'website' => 'https://garantibbvateknoloji.com.tr',
            'description' => 'Next-generation digital banking and fintech engineering center.',
            'created_at' => '2026-01-10 10:00:00',
            'updated_at' => '2026-01-10 10:00:00'
        ]
    ];

    public function __construct(array $attributes = []) {
        $this->id = isset($attributes['id']) ? (int)$attributes['id'] : null;
        $this->name = $attributes['name'] ?? '';
        $this->industry = $attributes['industry'] ?? null;
        $this->location = $attributes['location'] ?? null;
        $this->website = $attributes['website'] ?? null;
        $this->description = $attributes['description'] ?? null;
        $this->created_at = $attributes['created_at'] ?? null;
        $this->updated_at = $attributes['updated_at'] ?? null;
    }

    /**
     * Relationship: A company has many job postings
     */
    public function jobPostings(): array {
        if ($this->id === null) {
            return [];
        }
        return JobPosting::findByCompanyId($this->id);
    }

    /**
     * Retrieve all companies
     */
    public static function all(): array {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->query('SELECT * FROM companies ORDER BY name ASC');
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return array_map(fn($row) => new self($row), $rows);
            } catch (\Throwable $e) {
                // fall through to mock
            }
        }

        return array_map(fn($row) => new self($row), self::$mockCompanies);
    }

    /**
     * Find company by ID
     */
    public static function find(int $id): ?self {
        $pdo = Database::getConnection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM companies WHERE id = :id LIMIT 1');
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

        foreach (self::$mockCompanies as $row) {
            if ($row['id'] === $id) {
                return new self($row);
            }
        }
        return null;
    }

    /**
     * Create a new company
     */
    public static function create(array $data): self {
        $pdo = Database::getConnection();
        $name = $data['name'] ?? 'Unnamed Corporation';
        $industry = $data['industry'] ?? null;
        $location = $data['location'] ?? null;
        $website = $data['website'] ?? null;
        $description = $data['description'] ?? null;

        if ($pdo) {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO companies (name, industry, location, website, description)
                    VALUES (:name, :industry, :location, :website, :description)
                ');
                $stmt->execute([
                    'name' => $name,
                    'industry' => $industry,
                    'location' => $location,
                    'website' => $website,
                    'description' => $description
                ]);
                $newId = (int)$pdo->lastInsertId();
                return self::find($newId) ?? new self(array_merge($data, ['id' => $newId]));
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $newId = count(self::$mockCompanies) > 0 ? (max(array_column(self::$mockCompanies, 'id')) + 1) : 1;
        $record = [
            'id' => $newId,
            'name' => $name,
            'industry' => $industry,
            'location' => $location,
            'website' => $website,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        self::$mockCompanies[] = $record;
        return new self($record);
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'industry' => $this->industry,
            'location' => $this->location,
            'website' => $this->website,
            'description' => $this->description,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function jsonSerialize(): array {
        return $this->toArray();
    }
}
