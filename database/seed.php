<?php
declare(strict_types=1);

/**
 * AlumniIstü Database Seeder CLI Runner
 * Usage:
 *   php database/seed.php [--count=20]
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniIstü\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) === 0) {
        $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // Support database namespace
    $dbPrefix = 'AlumniIstü\\Database\\';
    $dbLen = strlen($dbPrefix);
    if (strncmp($dbPrefix, $class, $dbLen) === 0) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, $dbLen)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/factories/AlumniFactory.php';
require_once __DIR__ . '/factories/JobPostingFactory.php';
require_once __DIR__ . '/factories/ApplicationFactory.php';
require_once __DIR__ . '/seeders/DatabaseSeeder.php';

use AlumniIstü\Core\Database;
use AlumniIstü\Core\Env;
use AlumniIstü\Database\Seeders\DatabaseSeeder;

Env::load();

echo "\033[1;36m==================================================\033[0m\n";
echo "\033[1;36m        AlumniIstü Database Seeder Tool          \033[0m\n";
echo "\033[1;36m==================================================\033[0m\n\n";

$count = 15;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--count=')) {
        $count = (int)substr($arg, 8);
    }
}

DatabaseSeeder::run($count);

echo "\n\033[1;32m✔ Database seeding completed successfully.\033[0m\n\n";
