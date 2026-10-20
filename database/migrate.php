<?php
declare(strict_types=1);

/**
 * AlumniNexus Database Migration Runner
 * Usage:
 *   php database/migrate.php [up|status|fresh]
 */

// Autoload core classes
spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniNexus\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require_once $file;
});

use AlumniNexus\Core\Database;
use AlumniNexus\Core\Env;

Env::load();

$action = $argv[1] ?? 'up';

echo "\033[1;36m==================================================\033[0m\n";
echo "\033[1;36m      AlumniNexus Database Migration Engine       \033[0m\n";
echo "\033[1;36m==================================================\033[0m\n\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "\033[1;31m[ERROR] Unable to connect to MySQL database.\033[0m\n";
    echo "Reason: " . (Database::getLastError() ?? 'Unknown connection error') . "\n";
    echo "Check your environment variables in .env (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS).\n";
    exit(1);
}

// 1. Ensure migrations tracking table exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        batch INT NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

if (empty($files)) {
    echo "\033[1;33m[NOTICE] No migration files found in {$migrationsDir}\033[0m\n";
    exit(0);
}

if ($action === 'status') {
    $stmt = $pdo->query("SELECT migration, batch, executed_at FROM migrations ORDER BY id ASC");
    $applied = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $appliedMap = [];
    foreach ($applied as $row) {
        $appliedMap[$row['migration']] = $row;
    }

    echo "Migration Status:\n";
    echo sprintf("%-40s | %-8s | %-20s\n", "Migration File", "Status", "Executed At");
    echo str_repeat("-", 75) . "\n";
    foreach ($files as $file) {
        $base = basename($file);
        if (isset($appliedMap[$base])) {
            echo sprintf("\033[32m%-40s | %-8s | %-20s\033[0m\n", $base, "APPLIED", $appliedMap[$base]['executed_at']);
        } else {
            echo sprintf("\033[33m%-40s | %-8s | %-20s\033[0m\n", $base, "PENDING", "-");
        }
    }
    echo "\n";
    exit(0);
}

if ($action === 'fresh') {
    echo "\033[1;33m[WARNING] Dropping existing database tables...\033[0m\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`;");
        echo "  Dropped table: {$table}\n";
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Recreate migrations table
    $pdo->exec("
        CREATE TABLE migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "\033[32m✔ Tables wiped clean. Running all migrations fresh...\033[0m\n\n";
}

// 2. Fetch already executed migrations
$stmt = $pdo->query("SELECT migration FROM migrations");
$executed = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Calculate next batch number
$batchStmt = $pdo->query("SELECT MAX(batch) as max_batch FROM migrations");
$currentBatch = (int)($batchStmt->fetch(PDO::FETCH_ASSOC)['max_batch'] ?? 0);
$nextBatch = $currentBatch + 1;

$appliedCount = 0;
foreach ($files as $file) {
    $baseName = basename($file);
    if (in_array($baseName, $executed, true)) {
        continue;
    }

    echo "Migrating: \033[1;34m{$baseName}\033[0m ... ";
    $sql = file_get_contents($file);

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $insertStmt = $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)");
        $insertStmt->execute([
            'migration' => $baseName,
            'batch' => $nextBatch
        ]);
        $pdo->commit();
        echo "\033[32mDONE ✔\033[0m\n";
        $appliedCount++;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "\033[31mFAILED ✖\033[0m\n";
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($appliedCount === 0) {
    echo "\033[32m✔ Nothing to migrate. Database schema is up to date.\033[0m\n\n";
} else {
    echo "\n\033[1;32m✔ Successfully applied {$appliedCount} migration(s) (Batch {$nextBatch}).\033[0m\n\n";
}
