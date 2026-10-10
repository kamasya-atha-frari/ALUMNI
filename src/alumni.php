<?php
/**
 * Alumni Resource API Endpoint
 * Handles directory listing and cohort filtering.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

// In-memory fallback dataset (ensures the endpoint works even before DB runs)
$standbyDataset = [
    [
        'id' => 1,
        'student_id' => '130120001',
        'full_name' => 'Ahmet Yilmaz',
        'email' => 'ahmet.yilmaz@ogr.iu.edu.tr',
        'graduation_year' => 2023,
        'department' => 'Management Information Systems',
        'faculty' => 'Faculty of Economics',
        'company' => 'Turkcell',
        'job_title' => 'Business Intelligence Specialist',
        'is_mentor' => true
    ],
    [
        'id' => 2,
        'student_id' => '130120002',
        'full_name' => 'Zeynep Kaya',
        'email' => 'zeynep.kaya@ogr.iu.edu.tr',
        'graduation_year' => 2022,
        'department' => 'Management Information Systems',
        'faculty' => 'Faculty of Economics',
        'company' => 'Trendyol',
        'job_title' => 'Software Engineer',
        'is_mentor' => true
    ],
    [
        'id' => 3,
        'student_id' => '130120003',
        'full_name' => 'Can Demir',
        'email' => 'can.demir@ogr.iu.edu.tr',
        'graduation_year' => 2024,
        'department' => 'Management Information Systems',
        'faculty' => 'Faculty of Economics',
        'company' => 'Getir',
        'job_title' => 'Cloud Infrastructure Analyst',
        'is_mentor' => false
    ]
];

$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : null;

if (!$pdo) {
    // Serve from standby data
    $filtered = $standbyDataset;
    if ($filterYear) {
        $filtered = array_values(array_filter($standbyDataset, function ($item) use ($filterYear) {
            return $item['graduation_year'] === $filterYear;
        }));
    }
    echo json_encode([
        'source' => 'standby_in_memory',
        'total' => count($filtered),
        'data' => $filtered
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($filterYear) {
        $stmt = $pdo->prepare('
            SELECT u.id, u.student_id, u.full_name, u.email, u.graduation_year,
                   d.name AS department, d.faculty, u.company, u.job_title, u.is_mentor
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.graduation_year = :year
            ORDER BY u.full_name ASC
        ');
        $stmt->execute(['year' => $filterYear]);
    } else {
        $stmt = $pdo->query('
            SELECT u.id, u.student_id, u.full_name, u.email, u.graduation_year,
                   d.name AS department, d.faculty, u.company, u.job_title, u.is_mentor
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            ORDER BY u.full_name ASC
        ');
    }

    $results = $stmt->fetchAll();
    echo json_encode([
        'source' => 'mysql_database',
        'total' => count($results),
        'data' => $results
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database query failed',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
