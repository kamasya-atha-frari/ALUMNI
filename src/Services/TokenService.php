<?php
declare(strict_types=1);

namespace AlumniIstü\Services;

use AlumniIstü\Core\Database;
use AlumniIstü\Core\Env;
use PDO;

/**
 * Stateless HMAC-SHA256 JWT Bearer Token Service with Server-Side Logout Revocation (jti blocklist).
 */
class TokenService {
    private static bool $schemaChecked = false;
    private string $fallbackRevokedPath;

    public function __construct(?string $fallbackRevokedPath = null) {
        $this->fallbackRevokedPath = $fallbackRevokedPath
            ?? (sys_get_temp_dir() . '/alumni_istu_revoked_tokens.json');
    }

    public function getTtlSeconds(): int {
        $ttl = (int)Env::get('AUTH_TOKEN_TTL_SECONDS', 3600);
        return max(300, $ttl);
    }

    private function getSecret(): string {
        $secret = (string)Env::get('AUTH_TOKEN_SECRET', '');
        if ($secret !== '') {
            return $secret;
        }
        return 'alumni-istu-hs256-dev-secret-change-in-production-2026';
    }

    /**
     * Mint a signed HS256 JWT access token with a unique jti for revocation support.
     */
    public function issueToken(array $user): array {
        $now = time();
        $ttl = $this->getTtlSeconds();
        $exp = $now + $ttl;
        $jti = 'tok_' . bin2hex(random_bytes(16));

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => 'alumni-istu',
            'jti' => $jti,
            'sub' => (int)($user['id'] ?? 0),
            'username' => (string)($user['username'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
            'role' => (string)($user['role'] ?? 'alumni'),
            'iat' => $now,
            'exp' => $exp,
        ];

        $encodedHeader = self::base64UrlEncode((string)json_encode($header));
        $encodedPayload = self::base64UrlEncode((string)json_encode($payload));
        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $this->getSecret(), true);
        $encodedSig = self::base64UrlEncode($signature);

        return [
            'access_token' => "{$encodedHeader}.{$encodedPayload}.{$encodedSig}",
            'token_type' => 'Bearer',
            'token_id' => $jti,
            'expires_in' => $ttl,
            'expires_at' => gmdate('c', $exp),
        ];
    }

    /**
     * Validate JWT signature, expiration, and server-side revocation status.
     * Returns decoded claims or null if invalid/expired/revoked.
     */
    public function verifyToken(string $token, bool $checkRevocation = true): ?array {
        $claims = $this->decodeAndVerifySignature($token);
        if ($claims === null) {
            return null;
        }

        if ($checkRevocation && $this->isRevoked($claims, $token)) {
            return null;
        }

        return $claims;
    }

    /**
     * Inspect token validity with explicit error reason (valid | invalid | expired | revoked).
     *
     * @return array{valid:bool, reason:?string, claims:?array}
     */
    public function inspectToken(string $token): array {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return ['valid' => false, 'reason' => 'TOKEN_INVALID_OR_EXPIRED', 'claims' => null];
        }

        [$encodedHeader, $encodedPayload, $encodedSig] = $parts;
        $headerJson = self::base64UrlDecode($encodedHeader);
        $payloadJson = self::base64UrlDecode($encodedPayload);
        $signature = self::base64UrlDecode($encodedSig);

        if ($headerJson === false || $payloadJson === false || $signature === false) {
            return ['valid' => false, 'reason' => 'TOKEN_INVALID_OR_EXPIRED', 'claims' => null];
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);
        if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'HS256') {
            return ['valid' => false, 'reason' => 'TOKEN_INVALID_OR_EXPIRED', 'claims' => null];
        }

        $expectedSig = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $this->getSecret(), true);
        if (!hash_equals($expectedSig, $signature)) {
            return ['valid' => false, 'reason' => 'TOKEN_INVALID_OR_EXPIRED', 'claims' => null];
        }

        if (!isset($payload['exp']) || (int)$payload['exp'] < time()) {
            return ['valid' => false, 'reason' => 'TOKEN_INVALID_OR_EXPIRED', 'claims' => null];
        }

        if ($this->isRevoked($payload, $token)) {
            return ['valid' => false, 'reason' => 'TOKEN_REVOKED', 'claims' => null];
        }

        return ['valid' => true, 'reason' => null, 'claims' => $payload];
    }

    /**
     * Revoke a valid JWT on logout so any subsequent request using it is rejected with 401 TOKEN_REVOKED.
     *
     * @return array{revoked:bool, token_id:string, revoked_at:string}
     */
    public function revokeToken(array $claims, string $rawToken): array {
        $jti = trim((string)($claims['jti'] ?? ''));
        if ($jti === '') {
            $jti = 'hash_' . hash('sha256', $rawToken);
        }
        $userId = isset($claims['sub']) ? (int)$claims['sub'] : null;
        $expTs = isset($claims['exp']) ? (int)$claims['exp'] : (time() + $this->getTtlSeconds());
        $expiresSql = gmdate('Y-m-d H:i:s', $expTs);
        $revokedAtIso = gmdate('c');

        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                // Opportunistic cleanup of expired revocations
                $pdo->prepare('DELETE FROM revoked_tokens WHERE expires_at < :now')
                    ->execute(['now' => gmdate('Y-m-d H:i:s')]);

                $stmt = $pdo->prepare('
                    INSERT INTO revoked_tokens (jti, user_id, expires_at)
                    VALUES (:jti, :user_id, :expires_at)
                    ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)
                ');
                $stmt->execute([
                    'jti' => $jti,
                    'user_id' => $userId,
                    'expires_at' => $expiresSql,
                ]);
                return [
                    'revoked' => true,
                    'token_id' => $jti,
                    'revoked_at' => $revokedAtIso,
                ];
            } catch (\Throwable $e) {
                // Fall back to local file store
            }
        }

        $store = $this->readFallbackStore();
        $now = time();
        foreach ($store as $key => $item) {
            if ((int)($item['exp'] ?? 0) < $now) {
                unset($store[$key]);
            }
        }
        $store[$jti] = [
            'jti' => $jti,
            'user_id' => $userId,
            'exp' => $expTs,
            'revoked_at' => $revokedAtIso,
        ];
        $this->writeFallbackStore($store);

        return [
            'revoked' => true,
            'token_id' => $jti,
            'revoked_at' => $revokedAtIso,
        ];
    }

    /**
     * Check whether a token's jti has been revoked via logout.
     */
    public function isRevoked(array $claims, string $rawToken = ''): bool {
        $jti = trim((string)($claims['jti'] ?? ''));
        if ($jti === '' && $rawToken !== '') {
            $jti = 'hash_' . hash('sha256', $rawToken);
        }
        if ($jti === '') {
            return false;
        }

        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT 1 FROM revoked_tokens WHERE jti = :jti LIMIT 1');
                $stmt->execute(['jti' => $jti]);
                if ($stmt->fetchColumn() !== false) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Fall through to file store
            }
        }

        $store = $this->readFallbackStore();
        return isset($store[$jti]);
    }

    /**
     * Extract Bearer token from HTTP Authorization header or fallback auth cookie.
     */
    public static function extractBearerToken(?string $authorizationHeader = null): ?string {
        $header = $authorizationHeader;
        if ($header === null) {
            $header = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? null;

            if ($header === null && function_exists('getallheaders')) {
                $headers = getallheaders();
                if (is_array($headers)) {
                    foreach ($headers as $k => $v) {
                        if (strtolower((string)$k) === 'authorization') {
                            $header = (string)$v;
                            break;
                        }
                    }
                }
            }
        }

        if ($header && preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        if ($authorizationHeader === null && !empty($_COOKIE['alumni_istu_token'])) {
            return trim((string)$_COOKIE['alumni_istu_token']);
        }

        return null;
    }

    private function decodeAndVerifySignature(string $token): ?array {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSig] = $parts;
        $headerJson = self::base64UrlDecode($encodedHeader);
        $payloadJson = self::base64UrlDecode($encodedPayload);
        $signature = self::base64UrlDecode($encodedSig);

        if ($headerJson === false || $payloadJson === false || $signature === false) {
            return null;
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);
        if (!is_array($header) || !is_array($payload) || ($header['alg'] ?? '') !== 'HS256') {
            return null;
        }

        $expectedSig = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $this->getSecret(), true);
        if (!hash_equals($expectedSig, $signature)) {
            return null;
        }

        if (!isset($payload['exp']) || (int)$payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function getReadyPdo(): ?PDO {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }
        if (!self::$schemaChecked) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS revoked_tokens (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        jti VARCHAR(64) NOT NULL UNIQUE,
                        user_id INT NULL,
                        expires_at DATETIME NOT NULL,
                        revoked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_revoked_expires (expires_at),
                        INDEX idx_revoked_user (user_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
                self::$schemaChecked = true;
            } catch (\Throwable $e) {
                return null;
            }
        }
        return $pdo;
    }

    private function readFallbackStore(): array {
        if (!file_exists($this->fallbackRevokedPath)) {
            return [];
        }
        $raw = @file_get_contents($this->fallbackRevokedPath);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeFallbackStore(array $store): void {
        @file_put_contents(
            $this->fallbackRevokedPath,
            json_encode($store, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string|false {
        $remainder = strlen($data) % 4;
        if ($remainder > 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
