<?php
/**
 * Dedicated Alumni Resource Controller & Fallback Handler
 * Route: /alumni
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/models/User.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$filterYear = isset($_GET['year']) ? (int)$_GET['year'] : null;

$allUsers = User::all();
$alumniList = array_values(array_filter($allUsers, function ($u) {
    return ($u['role'] ?? '') === 'alumni' || !empty($u['graduation_year']);
}));

if ($filterYear !== null) {
    $alumniList = array_values(array_filter($alumniList, function ($u) use ($filterYear) {
        return (int)($u['graduation_year'] ?? 0) === $filterYear;
    }));
}

echo json_encode([
    'status' => 'success',
    'total' => count($alumniList),
    'data' => $alumniList,
    'database_connected' => (Database::getConnection() !== null),
    'institution' => 'Istanbul University - Faculty of Economics - MIS'
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
