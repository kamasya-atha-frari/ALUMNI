<?php
declare(strict_types=1);

namespace AlumniIstü\Middleware;

/**
 * LoggerMiddleware
 * 
 * Implements Whiteboard Item 3 & Task 2:
 * "Logger Middleware: write log on the terminal
 *  ex. row: time method route res.type duration
 *  10:20:30 Post /user 201 18 ms"
 * 
 * Format: [time] [method] [route] [res.type] [duration]
 * Example: 12:20:30 Post /user 201 18 ms
 */
class LoggerMiddleware implements MiddlewareInterface {
    /** @var array<string> Internal log history for test assertions */
    private static array $logBuffer = [];

    /** @var bool Whether shutdown handler has been registered */
    private static bool $shutdownRegistered = false;

    /** @var float|null Active request start timestamp */
    private static ?float $activeStartTime = null;

    /** @var string|null Active request method */
    private static ?string $activeMethod = null;

    /** @var string|null Active request route */
    private static ?string $activeRoute = null;

    /** @var bool Whether current request log has already been emitted */
    private static bool $activeLogged = false;

    /**
     * Start monitoring and timing an incoming HTTP request.
     * Registers a shutdown hook to ensure logs are always output to the terminal,
     * even if controllers call exit; via Response::json() or similar.
     */
    public static function start(string $method, string $uri): void {
        self::$activeStartTime = microtime(true);
        self::$activeMethod = $method;
        self::$activeRoute = parse_url($uri, PHP_URL_PATH) ?: '/';
        self::$activeLogged = false;

        if (!self::$shutdownRegistered) {
            self::$shutdownRegistered = true;
            register_shutdown_function(function (): void {
                if (!self::$activeLogged && self::$activeStartTime !== null) {
                    $code = http_response_code();
                    if ($code === false || $code === 0) {
                        $code = 200;
                    }
                    self::record(
                        self::$activeMethod ?? 'GET',
                        self::$activeRoute ?? '/',
                        (int)$code,
                        self::$activeStartTime
                    );
                }
            });
        }
    }

    /**
     * Standard middleware handle method for pipeline execution.
     */
    public function handle(string $method, string $uri, callable $next): mixed {
        self::start($method, $uri);
        $startTime = microtime(true);

        try {
            $result = $next();
            $statusCode = http_response_code() ?: 200;
            self::record($method, $uri, (int)$statusCode, $startTime);
            return $result;
        } catch (\Throwable $e) {
            $statusCode = 500;
            self::record($method, $uri, $statusCode, $startTime);
            throw $e;
        }
    }

    /**
     * Format and record log entry directly to the terminal.
     *
     * Format: [time] [method] [route] [res.type] [duration]
     * E.g.: 12:20:30 Post /user 201 18 ms
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $uri Requested URI
     * @param int $statusCode HTTP response status code
     * @param float $startTime Request start time from microtime(true)
     * @return string The formatted log line
     */
    public static function record(string $method, string $uri, int $statusCode, float $startTime): string {
        $timeStr = date('H:i:s');
        $formattedMethod = ucfirst(strtolower($method));
        $route = parse_url($uri, PHP_URL_PATH) ?: '/';
        $durationMs = max(1, (int)round((microtime(true) - $startTime) * 1000));

        // Format: [time] [method] [route] [res.type] [duration]
        $logLine = sprintf(
            "%s %s %s %d %d ms",
            $timeStr,
            $formattedMethod,
            $route,
            $statusCode,
            $durationMs
        );

        self::$activeLogged = true;
        self::$logBuffer[] = $logLine;

        // Output directly to terminal
        self::writeToTerminal($logLine);

        return $logLine;
    }

    /**
     * Write formatted log line directly to terminal stdout/stderr and system log.
     */
    private static function writeToTerminal(string $message): void {
        // Output to stdout for terminal view
        $stdout = @fopen('php://stdout', 'w');
        if ($stdout) {
            @fwrite($stdout, $message . PHP_EOL);
            @fclose($stdout);
        }

        // Output to stderr for container logs
        $stderr = @fopen('php://stderr', 'w');
        if ($stderr) {
            @fwrite($stderr, $message . PHP_EOL);
            @fclose($stderr);
        }

        // Output to Apache error log
        error_log($message);
    }

    /**
     * Get all recorded logs (useful for verification and automated tests)
     * @return array<string>
     */
    public static function getLogs(): array {
        return self::$logBuffer;
    }

    /**
     * Get the most recent log line
     */
    public static function getLastLog(): ?string {
        if (empty(self::$logBuffer)) {
            return null;
        }
        return self::$logBuffer[count(self::$logBuffer) - 1];
    }

    /**
     * Clear recorded logs buffer
     */
    public static function clearLogs(): void {
        self::$logBuffer = [];
        self::$activeLogged = false;
        self::$activeStartTime = null;
    }
}
