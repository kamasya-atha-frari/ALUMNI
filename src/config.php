<?php
/**
 * Database Configuration & PDO Connection Factory
 *
 * Reads database credentials from environment variables supplied by Docker Compose.
 * Gracefully handles connection states.
 */

$host    = getenv('DB_HOST') ?: 'db';
$db      = getenv('DB_NAME') ?: 'alumni_db';
$user    = getenv('DB_USER') ?: 'alumni_user';
$pass    = getenv('DB_PASS') ?: 'alumni_secret';
$port    = getenv('DB_PORT') ?: '3306';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null;
$dbError = null;

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If the database container is still warming up or unavailable
    $pdo = null;
    $dbError = $e->getMessage();
}
