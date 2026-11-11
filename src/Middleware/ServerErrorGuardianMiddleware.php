<?php
declare(strict_types=1);

namespace AlumniIstü\Middleware;

use AlumniIstü\Core\Response;
use Throwable;

/**
 * ServerErrorGuardianMiddleware (5xx Guardian)
 * 
 * Implements Task 4 & Task 5:
 * - Safely catches exceptions and fatal errors to prevent raw crashes.
 * - Automatically ensures a `logs/` directory exists and logs error details to `logs/error.logs`.
 * - Returns a clean, structured HTTP 500 response.
 */
class ServerErrorGuardianMiddleware implements MiddlewareInterface {
    /** @var string Path to error log file */
    private static ?string $logFilePath = null;

    /** @var bool Whether global error handlers have been registered */
    private static bool $registered = false;

    /**
     * Get or initialize the log file path (`logs/error.logs`).
     */
    public static function getLogFilePath(): string {
        if (self::$logFilePath === null) {
            $baseDir = dirname(__DIR__, 2);
            self::$logFilePath = $baseDir . '/logs/error.logs';
        }
        return self::$logFilePath;
    }

    /**
     * Override log file path (useful for testing).
     */
    public static function setLogFilePath(string $path): void {
        self::$logFilePath = $path;
    }

    /**
     * Register global exception, error, and shutdown handlers.
     */
    public static function register(): void {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        // Uncaught Exception Handler
        set_exception_handler(function (Throwable $e): void {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            self::handleException($e, $method, $uri);
        });

        // Error handler converting notices/warnings into exceptions if critical
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            if (in_array($severity, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
            return false;
        });

        // Shutdown handler for fatal errors
        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
                $uri = $_SERVER['REQUEST_URI'] ?? '/';
                $e = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
                self::handleException($e, $method, $uri);
            }
        });
    }

    /**
     * Standard middleware execution handler.
     */
    public function handle(string $method, string $uri, callable $next): mixed {
        try {
            return $next();
        } catch (Throwable $e) {
            self::handleException($e, $method, $uri);
            return false;
        }
    }

    /**
     * Centralized exception processor:
     * 1. Logs error to `logs/error.logs`
     * 2. Returns clean 500 response
     */
    public static function handleException(Throwable $e, string $method = 'GET', string $uri = '/'): void {
        $errorId = 'err_' . substr(bin2hex(random_bytes(6)), 0, 8);
        $cleanPath = parse_url($uri, PHP_URL_PATH) ?: '/';

        // 1. Log to file `logs/error.logs`
        self::logToFile($e, $method, $cleanPath, $errorId);

        // 2. Set HTTP 500 status code
        http_response_code(500);

        // 3. Determine client response format (JSON vs HTML)
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isApi = str_starts_with($cleanPath, '/api/') ||
                 str_contains($accept, 'application/json') ||
                 isset($_GET['api']) ||
                 isset($_GET['json']);

        if ($isApi) {
            Response::json([
                'status' => 'error',
                'code' => 500,
                'guardian' => '5xx Guardian Middleware',
                'error' => 'Internal Server Error',
                'message' => 'An unexpected server error occurred.',
                'reason' => 'An internal execution error was intercepted safely by 5xx Guardian Middleware. Details logged to logs/error.logs.',
                'error_id' => $errorId,
                'path' => $cleanPath,
                'method' => strtoupper($method),
                'timestamp' => date('c')
            ], 500);
            return;
        }

        // Web / HTML response
        $templatePath = dirname(__DIR__, 2) . '/templates/500.html';
        if (file_exists($templatePath)) {
            Response::html($templatePath, [
                'ERROR_ID' => $errorId,
                'PATH' => htmlspecialchars($cleanPath),
                'METHOD' => htmlspecialchars(strtoupper($method)),
                'REASON' => 'An unexpected server error occurred. The incident has been recorded in logs/error.logs.'
            ], 500);
            return;
        }

        Response::text("500 Internal Server Error [{$errorId}]: An unexpected error occurred.", 500);
    }

    /**
     * Ensure `logs/` directory exists and append error log entry into `logs/error.logs`.
     */
    public static function logToFile(Throwable $e, string $method, string $uri, string $errorId): string {
        $logFile = self::getLogFilePath();
        $logDir = dirname($logFile);

        // Automatically create `logs` folder if missing
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $exceptionClass = get_class($e);
        $message = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
        $trace = $e->getTraceAsString();

        $logEntry = sprintf(
            "[%s] [500] [ID:%s] [%s %s]\nError: %s: %s\nFile: %s:%d\nTrace:\n%s\n%s\n",
            $timestamp,
            $errorId,
            strtoupper($method),
            $uri,
            $exceptionClass,
            $message,
            $file,
            $line,
            $trace,
            str_repeat('-', 80)
        );

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Also output to terminal / server logs
        error_log("5xx Guardian [{$errorId}]: {$exceptionClass} - {$message} in {$file}:{$line}");

        return $logEntry;
    }
}
