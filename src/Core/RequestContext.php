<?php
declare(strict_types=1);

namespace AlumniIstü\Core;

/**
 * RequestContext - Per-request context container holding the authenticated user attached by AuthMiddleware.
 */
class RequestContext {
    /** @var array<string, mixed>|null */
    private static ?array $user = null;

    /**
     * Attach authenticated user metadata (id, username, email, role, jti, iat, exp) to request context.
     *
     * @param array<string, mixed> $user
     */
    public static function setUser(array $user): void {
        self::$user = $user;
        $_SERVER['AUTH_USER'] = $user;
    }

    /**
     * Retrieve the authenticated user for the current request, or null if guest/unauthenticated.
     *
     * @return array<string, mixed>|null
     */
    public static function user(): ?array {
        return self::$user;
    }

    public static function userId(): ?int {
        if (self::$user === null || !isset(self::$user['id'])) {
            return null;
        }
        return (int)self::$user['id'];
    }

    public static function userEmail(): ?string {
        if (self::$user === null || !isset(self::$user['email'])) {
            return null;
        }
        return (string)self::$user['email'];
    }

    public static function userRole(): ?string {
        if (self::$user === null || !isset(self::$user['role'])) {
            return null;
        }
        return (string)self::$user['role'];
    }

    public static function isAuthenticated(): bool {
        return self::$user !== null;
    }

    /**
     * Clear authenticated user state (useful between automated test invocations).
     */
    public static function clear(): void {
        self::$user = null;
        unset($_SERVER['AUTH_USER']);
    }
}
