<?php
declare(strict_types=1);

namespace AlumniNexus\Controllers;

use AlumniNexus\Core\Database;
use AlumniNexus\Core\Response;

class HealthController {
    public function check(): void {
        $db = Database::getConnection();
        $isDbUp = ($db !== null);

        Response::json([
            'status' => $isDbUp ? 'healthy' : 'degraded',
            'service' => 'AlumniNexus API Core',
            'version' => '1.2.0',
            'php_version' => PHP_VERSION,
            'container_environment' => getenv('DOCKER_ENV') ? 'docker-compose' : 'host-runtime',
            'database' => [
                'status' => $isDbUp ? 'connected' : 'unreachable',
                'engine' => 'MySQL 8.0',
                'error' => Database::getLastError()
            ],
            'system_telemetry' => [
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'timestamp' => date('c'),
            ]
        ]);
    }

    public function ping(): void {
        Response::json([
            'status' => 'ok',
            'message' => 'AlumniNexus API is running smoothly',
            'timestamp' => date('c')
        ]);
    }
}
