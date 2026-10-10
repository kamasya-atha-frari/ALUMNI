<?php
/**
 * Database Abstraction Layer (DAL) & PDO Factory
 */

class Database {
    private static ?PDO $instance = null;
    private static ?string $lastError = null;

    public static function getConnection(): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require __DIR__ . '/config.php';
        $db = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['database'],
            $db['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ];

        try {
            self::$instance = new PDO($dsn, $db['username'], $db['password'], $options);
            return self::$instance;
        } catch (\PDOException $e) {
            self::$lastError = $e->getMessage();
            return null;
        }
    }

    public static function getLastError(): ?string {
        return self::$lastError;
    }
}
