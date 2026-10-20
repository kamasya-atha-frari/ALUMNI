<?php
declare(strict_types=1);

namespace AlumniNexus\Core;

use PDO;
use PDOException;

class Database {
    private static ?PDO $connection = null;
    private static ?string $lastError = null;

    public static function getConnection(): ?PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        Env::load();

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $db   = Env::get('DB_NAME', 'alumni_nexus');
        $user = Env::get('DB_USER', 'alumni_admin');
        $pass = Env::get('DB_PASS', 'alumni_secure_2026');
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

        try {
            self::$connection = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 2,
            ]);
            return self::$connection;
        } catch (PDOException $e) {
            self::$lastError = $e->getMessage();
            return null;
        }
    }

    public static function resetConnection(): void {
        self::$connection = null;
        self::$lastError = null;
    }

    public static function getLastError(): ?string {
        return self::$lastError;
    }
}
