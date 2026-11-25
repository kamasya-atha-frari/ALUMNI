<?php
declare(strict_types=1);

namespace AlumniIstü\Services;

use AlumniIstü\Core\Database;
use AlumniIstü\Core\Env;
use PDO;

/**
 * Local-first SVG/Math CAPTCHA service with pluggable external provider config via .env.
 * Works out-of-the-box in Docker without GD/Imagick or external API keys.
 */
class CaptchaService {
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private static bool $schemaChecked = false;
    private string $fallbackStorePath;

    public function __construct(?string $fallbackStorePath = null) {
        $this->fallbackStorePath = $fallbackStorePath
            ?? (sys_get_temp_dir() . '/alumni_istu_captcha_challenges.json');
    }

    public function getDriver(): string {
        return strtolower(trim((string)Env::get('CAPTCHA_DRIVER', 'local_svg')));
    }

    public function getDefaultMode(): string {
        $mode = strtolower(trim((string)Env::get('CAPTCHA_MODE', 'alphanumeric')));
        return in_array($mode, ['alphanumeric', 'math'], true) ? $mode : 'alphanumeric';
    }

    public function getTtlSeconds(): int {
        $ttl = (int)Env::get('CAPTCHA_TTL_SECONDS', 300);
        return max(30, min(1800, $ttl));
    }

    public function getMaxAttempts(): int {
        $max = (int)Env::get('CAPTCHA_MAX_ATTEMPTS', 5);
        return max(1, min(10, $max));
    }

    private function getSecret(): string {
        $secret = (string)Env::get('CAPTCHA_SECRET', '');
        if ($secret !== '') {
            return $secret;
        }
        return (string)Env::get('AUTH_TOKEN_SECRET', 'alumni-istu-local-captcha-secret-2026');
    }

    /**
     * Generate a new CAPTCHA challenge, persist hashed answer, and return client payload.
     * The plaintext answer is never returned in the payload.
     */
    public function generate(?string $mode = null, ?string $ipAddress = null): array {
        $driver = $this->getDriver();
        $selectedMode = strtolower(trim((string)($mode ?? $this->getDefaultMode())));
        if (!in_array($selectedMode, ['alphanumeric', 'math'], true)) {
            $selectedMode = 'alphanumeric';
        }

        if ($driver !== 'local_svg') {
            return [
                'captcha_id' => 'ext_' . bin2hex(random_bytes(12)),
                'driver' => $driver,
                'mode' => 'external',
                'site_key' => (string)Env::get('CAPTCHA_SITE_KEY', ''),
                'prompt' => 'Complete the external CAPTCHA verification widget.',
                'svg' => null,
                'image_data_uri' => null,
                'expires_in' => $this->getTtlSeconds(),
                'expires_at' => gmdate('c', time() + $this->getTtlSeconds()),
            ];
        }

        $captchaId = 'cap_' . bin2hex(random_bytes(16));
        $ttl = $this->getTtlSeconds();
        $expiresTs = time() + $ttl;

        if ($selectedMode === 'math') {
            [$displayText, $answer] = $this->buildMathChallenge();
            $prompt = 'Solve the arithmetic challenge shown in the image';
        } else {
            $answer = $this->buildAlphanumericCode(6);
            $displayText = $answer;
            $prompt = 'Enter the 6 characters shown in the image (case-insensitive)';
        }

        $this->persistChallenge($captchaId, $answer, $selectedMode, $expiresTs, $ipAddress);
        $svg = $this->renderSvg($displayText, $selectedMode);

        return [
            'captcha_id' => $captchaId,
            'driver' => 'local_svg',
            'mode' => $selectedMode,
            'prompt' => $prompt,
            'svg' => $svg,
            'image_data_uri' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            'expires_in' => $ttl,
            'expires_at' => gmdate('c', $expiresTs),
        ];
    }

    /**
     * Store a deterministic challenge (used by automated test suites without exposing answers in HTTP).
     */
    public function storeKnownChallenge(
        string $captchaId,
        string $answer,
        int $ttlSeconds = 300,
        string $mode = 'alphanumeric'
    ): void {
        $this->persistChallenge($captchaId, $answer, $mode, time() + $ttlSeconds, '127.0.0.1');
    }

    /**
     * Verify a submitted CAPTCHA challenge server-side.
     * Rejects missing, unknown, reused, expired, exhausted, or incorrect answers.
     *
     * @return array{valid:bool, error_code:?string, message:?string, http_status:int}
     */
    public function verify(?string $captchaId, ?string $captchaAnswer): array {
        $driver = $this->getDriver();
        $id = trim((string)($captchaId ?? ''));
        $answer = trim((string)($captchaAnswer ?? ''));

        if ($driver !== 'local_svg') {
            return $this->verifyExternalProvider($answer !== '' ? $answer : $id);
        }

        if ($id === '' || $answer === '') {
            return [
                'valid' => false,
                'error_code' => 'CAPTCHA_MISSING',
                'message' => 'CAPTCHA verification is required. Please provide both captcha_id and captcha_answer.',
                'http_status' => 422,
            ];
        }

        $record = $this->fetchChallenge($id);
        if (!$record) {
            return [
                'valid' => false,
                'error_code' => 'CAPTCHA_INVALID',
                'message' => 'CAPTCHA challenge not found or invalid. Please refresh the CAPTCHA and try again.',
                'http_status' => 422,
            ];
        }

        if (!empty($record['used_at'])) {
            return [
                'valid' => false,
                'error_code' => 'CAPTCHA_REUSED',
                'message' => 'This CAPTCHA challenge has already been used. Please request a new CAPTCHA.',
                'http_status' => 422,
            ];
        }

        $expiresTs = is_numeric($record['expires_at'])
            ? (int)$record['expires_at']
            : strtotime((string)$record['expires_at'] . ' UTC');

        if ($expiresTs === false || $expiresTs < time()) {
            return [
                'valid' => false,
                'error_code' => 'CAPTCHA_EXPIRED',
                'message' => 'CAPTCHA challenge has expired. Please refresh the CAPTCHA and try again.',
                'http_status' => 422,
            ];
        }

        $attempts = (int)($record['attempts'] ?? 0);
        $maxAttempts = (int)($record['max_attempts'] ?? $this->getMaxAttempts());
        if ($attempts >= $maxAttempts) {
            return [
                'valid' => false,
                'error_code' => 'CAPTCHA_MAX_ATTEMPTS',
                'message' => 'Maximum CAPTCHA attempts exceeded. Please request a fresh CAPTCHA challenge.',
                'http_status' => 422,
            ];
        }

        $submittedHash = $this->hashAnswer($answer);
        if (!hash_equals((string)$record['answer_hash'], $submittedHash)) {
            $this->incrementAttempts($id);
            return [
                'valid' => false,
                'error_code' => 'CAPTCHA_INCORRECT',
                'message' => 'Incorrect CAPTCHA answer. Please verify the characters and try again.',
                'http_status' => 422,
            ];
        }

        $this->markUsed($id);

        return [
            'valid' => true,
            'error_code' => null,
            'message' => null,
            'http_status' => 200,
        ];
    }

    private function verifyExternalProvider(string $token): array {
        if ($token === '') {
            return [
                'valid' => false,
                'error_code' => 'CAPTCHA_MISSING',
                'message' => 'External CAPTCHA response token is required.',
                'http_status' => 422,
            ];
        }

        $secret = (string)Env::get('CAPTCHA_SECRET_KEY', '');
        $verifyUrl = (string)Env::get('CAPTCHA_VERIFY_URL', '');
        if ($verifyUrl === '') {
            $verifyUrl = $this->getDriver() === 'turnstile'
                ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
                : 'https://www.google.com/recaptcha/api/siteverify';
        }

        if ($secret === '') {
            return [
                'valid' => false,
                'error_code' => 'CAPTCHA_MISCONFIGURED',
                'message' => 'External CAPTCHA provider is configured without CAPTCHA_SECRET_KEY.',
                'http_status' => 500,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query([
                    'secret' => $secret,
                    'response' => $token,
                ]),
                'timeout' => 5,
            ],
        ]);

        $raw = @file_get_contents($verifyUrl, false, $context);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($decoded) || empty($decoded['success'])) {
            return [
                'valid' => false,
                'error_code' => 'CAPTCHA_INCORRECT',
                'message' => 'External CAPTCHA verification failed. Please retry.',
                'http_status' => 422,
            ];
        }

        return [
            'valid' => true,
            'error_code' => null,
            'message' => null,
            'http_status' => 200,
        ];
    }

    private function hashAnswer(string $answer): string {
        $normalized = strtolower(preg_replace('/\s+/', '', trim($answer)) ?? '');
        return hash_hmac('sha256', $normalized, $this->getSecret());
    }

    private function buildAlphanumericCode(int $length = 6): string {
        $alphabet = self::ALPHABET;
        $maxIndex = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $maxIndex)];
        }
        return $code;
    }

    /**
     * @return array{0:string, 1:string} [displayText, expectedAnswer]
     */
    private function buildMathChallenge(): array {
        $a = random_int(4, 19);
        $b = random_int(2, 12);
        $ops = ['+', '-'];
        $op = $ops[random_int(0, 1)];
        if ($op === '-' && $b > $a) {
            [$a, $b] = [$b, $a];
        }
        $result = $op === '+' ? ($a + $b) : ($a - $b);
        return ["{$a} {$op} {$b} = ?", (string)$result];
    }

    /**
     * Render a crisp, AlumniIstü Navy/Cream/Coral distorted SVG challenge.
     * Requires zero external PHP image extensions (works out-of-the-box in PHP 8.2 Docker).
     */
    private function renderSvg(string $text, string $mode): string {
        $width = 240;
        $height = 72;

        $lines = '';
        for ($i = 0; $i < 5; $i++) {
            $x1 = random_int(0, 45);
            $y1 = random_int(6, $height - 6);
            $cx1 = random_int(60, 120);
            $cy1 = random_int(4, $height - 4);
            $cx2 = random_int(120, 180);
            $cy2 = random_int(4, $height - 4);
            $x2 = random_int($width - 45, $width);
            $y2 = random_int(6, $height - 6);
            $color = ($i % 2 === 0) ? 'rgba(255,107,88,0.34)' : 'rgba(244,237,224,0.18)';
            $strokeWidth = random_int(1, 2);
            $lines .= sprintf(
                '<path d="M %d %d C %d %d, %d %d, %d %d" fill="none" stroke="%s" stroke-width="%d" stroke-linecap="round"/>',
                $x1, $y1, $cx1, $cy1, $cx2, $cy2, $x2, $y2, $color, $strokeWidth
            );
        }

        $dots = '';
        for ($i = 0; $i < 28; $i++) {
            $cx = random_int(6, $width - 6);
            $cy = random_int(6, $height - 6);
            $r = random_int(1, 2);
            $fill = ($i % 3 === 0) ? 'rgba(255,107,88,0.40)' : 'rgba(244,237,224,0.22)';
            $dots .= sprintf('<circle cx="%d" cy="%d" r="%d" fill="%s"/>', $cx, $cy, $r, $fill);
        }

        $charsSvg = '';
        if ($mode === 'math') {
            $angle = random_int(-5, 5);
            $y = random_int(43, 49);
            $safeText = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $charsSvg = sprintf(
                '<text x="120" y="%d" text-anchor="middle" font-family="ui-monospace, SFMono-Regular, Menlo, monospace" font-size="28" font-weight="700" letter-spacing="2" fill="#F4EDE0" transform="rotate(%d 120 %d)">%s</text>',
                $y, $angle, $y, $safeText
            );
        } else {
            $chars = str_split($text);
            $count = count($chars);
            $step = (int)(($width - 36) / max(1, $count));
            foreach ($chars as $idx => $ch) {
                $x = 26 + ($idx * $step) + random_int(-3, 4);
                $y = random_int(42, 52);
                $angle = random_int(-18, 18);
                $fontSize = random_int(27, 32);
                $fill = ($idx % 3 === 1) ? '#FF8B7B' : '#F4EDE0';
                $safeCh = htmlspecialchars($ch, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $charsSvg .= sprintf(
                    '<text x="%d" y="%d" font-family="ui-monospace, SFMono-Regular, Menlo, monospace" font-size="%d" font-weight="700" fill="%s" transform="rotate(%d %d %d)">%s</text>',
                    $x, $y, $fontSize, $fill, $angle, $x, $y, $safeCh
                );
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '" role="img" aria-label="CAPTCHA verification challenge">'
            . '<defs>'
            . '<linearGradient id="capBg" x1="0%" y1="0%" x2="100%" y2="100%">'
            . '<stop offset="0%" stop-color="#0f1a30"/>'
            . '<stop offset="100%" stop-color="#192847"/>'
            . '</linearGradient>'
            . '</defs>'
            . '<rect width="' . $width . '" height="' . $height . '" rx="10" fill="url(#capBg)"/>'
            . '<path d="M0 24 H240 M0 48 H240 M60 0 V72 M120 0 V72 M180 0 V72" stroke="rgba(244,237,224,0.06)" stroke-width="1"/>'
            . $dots
            . $lines
            . $charsSvg
            . '</svg>';
    }

    private function persistChallenge(
        string $captchaId,
        string $answer,
        string $mode,
        int $expiresTs,
        ?string $ipAddress
    ): void {
        $answerHash = $this->hashAnswer($answer);
        $expiresAtSql = gmdate('Y-m-d H:i:s', $expiresTs);
        $maxAttempts = $this->getMaxAttempts();

        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                // Opportunistic cleanup of stale expired challenges
                $pdo->prepare('DELETE FROM captcha_challenges WHERE expires_at < :cutoff')
                    ->execute(['cutoff' => gmdate('Y-m-d H:i:s', time() - 3600)]);

                $stmt = $pdo->prepare('
                    INSERT INTO captcha_challenges (captcha_id, answer_hash, challenge_type, attempts, max_attempts, ip_address, expires_at)
                    VALUES (:captcha_id, :answer_hash, :challenge_type, 0, :max_attempts, :ip_address, :expires_at)
                ');
                $stmt->execute([
                    'captcha_id' => $captchaId,
                    'answer_hash' => $answerHash,
                    'challenge_type' => $mode,
                    'max_attempts' => $maxAttempts,
                    'ip_address' => $ipAddress,
                    'expires_at' => $expiresAtSql,
                ]);
                return;
            } catch (\Throwable $e) {
                // Fall back to local file store
            }
        }

        $store = $this->readFallbackStore();
        $now = time();
        foreach ($store as $key => $item) {
            if (($item['expires_at'] ?? 0) < ($now - 3600)) {
                unset($store[$key]);
            }
        }
        $store[$captchaId] = [
            'captcha_id' => $captchaId,
            'answer_hash' => $answerHash,
            'challenge_type' => $mode,
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'ip_address' => $ipAddress,
            'expires_at' => $expiresTs,
            'used_at' => null,
        ];
        $this->writeFallbackStore($store);
    }

    private function fetchChallenge(string $captchaId): ?array {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT * FROM captcha_challenges WHERE captcha_id = :captcha_id LIMIT 1');
                $stmt->execute(['captcha_id' => $captchaId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return $row;
                }
            } catch (\Throwable $e) {
                // Fall through to fallback store
            }
        }

        $store = $this->readFallbackStore();
        return $store[$captchaId] ?? null;
    }

    private function incrementAttempts(string $captchaId): void {
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('UPDATE captcha_challenges SET attempts = attempts + 1 WHERE captcha_id = :captcha_id');
                $stmt->execute(['captcha_id' => $captchaId]);
                return;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        $store = $this->readFallbackStore();
        if (isset($store[$captchaId])) {
            $store[$captchaId]['attempts'] = (int)($store[$captchaId]['attempts'] ?? 0) + 1;
            $this->writeFallbackStore($store);
        }
    }

    private function markUsed(string $captchaId): void {
        $nowSql = gmdate('Y-m-d H:i:s');
        $pdo = $this->getReadyPdo();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare('UPDATE captcha_challenges SET used_at = :used_at WHERE captcha_id = :captcha_id');
                $stmt->execute([
                    'used_at' => $nowSql,
                    'captcha_id' => $captchaId,
                ]);
                return;
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        $store = $this->readFallbackStore();
        if (isset($store[$captchaId])) {
            $store[$captchaId]['used_at'] = $nowSql;
            $this->writeFallbackStore($store);
        }
    }

    private function getReadyPdo(): ?PDO {
        $pdo = Database::getConnection();
        if (!$pdo) {
            return null;
        }
        if (!self::$schemaChecked) {
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS captcha_challenges (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        captcha_id VARCHAR(64) NOT NULL UNIQUE,
                        answer_hash VARCHAR(64) NOT NULL,
                        challenge_type VARCHAR(30) NOT NULL DEFAULT 'alphanumeric',
                        attempts INT NOT NULL DEFAULT 0,
                        max_attempts INT NOT NULL DEFAULT 5,
                        ip_address VARCHAR(45) NULL,
                        expires_at DATETIME NOT NULL,
                        used_at DATETIME NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_captcha_expires (expires_at),
                        INDEX idx_captcha_used (used_at)
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
        if (!file_exists($this->fallbackStorePath)) {
            return [];
        }
        $raw = @file_get_contents($this->fallbackStorePath);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeFallbackStore(array $store): void {
        @file_put_contents(
            $this->fallbackStorePath,
            json_encode($store, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }
}
