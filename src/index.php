<?php
/**
 * Alumni Portal Entry Point & Service Health
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

$response = [
    'service' => 'Istanbul University MIS Alumni Portal API',
    'status' => 'online',
    'version' => '1.0.0',
    'database_connection' => $pdo ? 'connected' : 'standby / offline',
    'endpoints' => [
        'GET /' => 'Service status and system metadata',
        'GET /alumni.php' => 'Retrieve list of all alumni members',
        'GET /alumni.php?year={year}' => 'Filter alumni directory by graduation cohort'
    ],
    'timestamp' => date('c')
];

if (!$pdo && $dbError) {
    $response['database_notice'] = 'Database is initializing or not reachable yet. Endpoints will serve standby data.';
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
