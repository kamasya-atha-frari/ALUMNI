<?php
declare(strict_types=1);

namespace AlumniIstü\Services;

use AlumniIstü\Core\Env;

/**
 * Google OAuth 2.0 / OpenID Connect Service for "Continue with Google" registration & sign-in.
 * Supports both Browser Redirect Flow (Authorization Code) and Direct ID Token Verification (API/SPA).
 * Uses zero external Composer dependencies (native PHP curl / stream fallback).
 */
class GoogleOAuthService {
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const TOKENINFO_ENDPOINT = 'https://oauth2.googleapis.com/tokeninfo';
    private const USERINFO_ENDPOINT = 'https://openidconnect.googleapis.com/v1/userinfo';
    private const STATE_TTL_SECONDS = 600;

    /** @var callable|null Optional HTTP requester override for unit tests */
    private $httpRequester;

    public function __construct(?callable $httpRequester = null) {
        $this->httpRequester = $httpRequester;
    }

    public function getClientId(): string {
        return trim((string)Env::get('GOOGLE_CLIENT_ID', ''));
    }

    public function getClientSecret(): string {
        return trim((string)Env::get('GOOGLE_CLIENT_SECRET', ''));
    }

    public function getRedirectUri(): string {
        $uri = trim((string)Env::get('GOOGLE_REDIRECT_URI', ''));
        if ($uri !== '') {
            return $uri;
        }
        $port = trim((string)Env::get('APP_PORT', '8080'));
        return "http://localhost:{$port}/api/v1/auth/google/callback";
    }

    public function isConfigured(): bool {
        return $this->getClientId() !== '' && $this->getClientSecret() !== '';
    }

    public function isTokenVerificationConfigured(): bool {
        return $this->getClientId() !== '';
    }

    /**
     * Public-safe provider discovery metadata (never exposes client secret).
     */
    public function getPublicConfig(): array {
        return [
            'enabled' => $this->isConfigured(),
            'id_token_enabled' => $this->isTokenVerificationConfigured(),
            'redirect_uri' => $this->getRedirectUri(),
            'authorize_endpoint' => '/api/v1/auth/google',
            'token_endpoint' => '/api/v1/auth/google/token',
        ];
    }

    private function getStateSecret(): string {
        $secret = trim((string)Env::get('AUTH_TOKEN_SECRET', ''));
        return $secret !== '' ? $secret : 'alumni-istu-hs256-dev-secret-change-in-production-2026';
    }

    /**
     * Create a stateless, HMAC-SHA256 signed CSRF state parameter with short TTL.
     */
    public function createState(string $role = 'alumni', string $returnPath = '/register'): string {
        $payload = [
            'nonce' => bin2hex(random_bytes(12)),
            'role' => $role,
            'return_path' => str_starts_with($returnPath, '/') ? $returnPath : '/register',
            'iat' => time(),
            'exp' => time() + self::STATE_TTL_SECONDS,
        ];
        $encoded = self::base64UrlEncode((string)json_encode($payload));
        $sig = self::base64UrlEncode(hash_hmac('sha256', $encoded, $this->getStateSecret(), true));
        return "{$encoded}.{$sig}";
    }

    /**
     * Verify HMAC-SHA256 signature and expiration of the OAuth state parameter.
     */
    public function verifyState(?string $state): ?array {
        $raw = trim((string)($state ?? ''));
        if ($raw === '' || !str_contains($raw, '.')) {
            return null;
        }
        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$encoded, $providedSig] = $parts;
        $expectedSig = self::base64UrlEncode(hash_hmac('sha256', $encoded, $this->getStateSecret(), true));
        if (!hash_equals($expectedSig, $providedSig)) {
            return null;
        }

        $json = self::base64UrlDecode($encoded);
        if ($json === false) {
            return null;
        }
        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['exp']) || (int)$payload['exp'] < time()) {
            return null;
        }
        return $payload;
    }

    /**
     * Build the Google OAuth 2.0 Authorization URL.
     *
     * @return array{ok:bool, status:int, authorize_url?:string, state?:string, error_code?:string, message?:string}
     */
    public function buildAuthorizationUrl(string $role = 'alumni', string $returnPath = '/register'): array {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'status' => 503,
                'error_code' => 'GOOGLE_OAUTH_NOT_CONFIGURED',
                'message' => 'Google OAuth is not configured yet. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in .env.',
            ];
        }

        $state = $this->createState($role, $returnPath);
        $params = [
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->getRedirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        return [
            'ok' => true,
            'status' => 200,
            'authorize_url' => self::AUTH_ENDPOINT . '?' . http_build_query($params),
            'state' => $state,
        ];
    }

    /**
     * Exchange an OAuth 2.0 authorization code for verified Google user profile claims.
     *
     * @return array{ok:bool, status:int, profile?:array, error_code?:string, message?:string}
     */
    public function exchangeCodeForProfile(string $code): array {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'status' => 503,
                'error_code' => 'GOOGLE_OAUTH_NOT_CONFIGURED',
                'message' => 'Google OAuth credentials are missing in .env.',
            ];
        }

        $trimmedCode = trim($code);
        if ($trimmedCode === '') {
            return [
                'ok' => false,
                'status' => 400,
                'error_code' => 'OAUTH_MISSING_CODE',
                'message' => 'Authorization code is missing from Google OAuth callback.',
            ];
        }

        $tokenResponse = $this->httpRequest('POST', self::TOKEN_ENDPOINT, [
            'code' => $trimmedCode,
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'redirect_uri' => $this->getRedirectUri(),
            'grant_type' => 'authorization_code',
        ]);

        if (!$tokenResponse['ok'] || !is_array($tokenResponse['body'])) {
            return [
                'ok' => false,
                'status' => 401,
                'error_code' => 'OAUTH_TOKEN_EXCHANGE_FAILED',
                'message' => 'Failed to exchange Google authorization code for tokens. The code may be invalid or expired.',
            ];
        }

        $tokens = $tokenResponse['body'];
        if (!empty($tokens['id_token'])) {
            return $this->verifyIdToken((string)$tokens['id_token']);
        }

        if (!empty($tokens['access_token'])) {
            $userInfo = $this->httpRequest(
                'GET',
                self::USERINFO_ENDPOINT,
                [],
                ['Authorization: Bearer ' . (string)$tokens['access_token']]
            );
            if (!$userInfo['ok'] || !is_array($userInfo['body'])) {
                return [
                    'ok' => false,
                    'status' => 401,
                    'error_code' => 'OAUTH_USERINFO_FAILED',
                    'message' => 'Failed to retrieve verified profile from Google UserInfo endpoint.',
                ];
            }
            return $this->normalizeAndValidateClaims($userInfo['body'], false);
        }

        return [
            'ok' => false,
            'status' => 401,
            'error_code' => 'OAUTH_TOKEN_EXCHANGE_FAILED',
            'message' => 'Google token response did not include an id_token or access_token.',
        ];
    }

    /**
     * Verify a Google ID token directly (for Swagger, Postman, SPA, or mobile clients).
     *
     * @return array{ok:bool, status:int, profile?:array, error_code?:string, message?:string}
     */
    public function verifyIdToken(string $idToken): array {
        $token = trim($idToken);
        if ($token === '') {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'GOOGLE_TOKEN_MISSING',
                'message' => "Validation error: 'id_token' is required.",
            ];
        }

        $url = self::TOKENINFO_ENDPOINT . '?' . http_build_query(['id_token' => $token]);
        $res = $this->httpRequest('GET', $url);

        if (!$res['ok'] || !is_array($res['body'])) {
            return [
                'ok' => false,
                'status' => 401,
                'error_code' => 'GOOGLE_TOKEN_INVALID',
                'message' => 'Google ID token is invalid, expired, or could not be verified.',
            ];
        }

        return $this->normalizeAndValidateClaims($res['body'], true);
    }

    /**
     * Validate audience, issuer, expiration, and verified email on Google OIDC claims.
     */
    private function normalizeAndValidateClaims(array $claims, bool $checkAudienceAndIssuer): array {
        $sub = trim((string)($claims['sub'] ?? ''));
        $email = strtolower(trim((string)($claims['email'] ?? '')));

        if ($sub === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'ok' => false,
                'status' => 401,
                'error_code' => 'GOOGLE_TOKEN_INVALID',
                'message' => 'Google identity payload is missing a valid subject ID or email address.',
            ];
        }

        if ($checkAudienceAndIssuer) {
            $configuredClientId = $this->getClientId();
            $aud = trim((string)($claims['aud'] ?? ''));
            if ($configuredClientId !== '' && $aud !== $configuredClientId) {
                return [
                    'ok' => false,
                    'status' => 401,
                    'error_code' => 'GOOGLE_AUDIENCE_MISMATCH',
                    'message' => 'Google ID token audience does not match this application client ID.',
                ];
            }

            if (isset($claims['iss'])) {
                $validIssuers = ['https://accounts.google.com', 'accounts.google.com'];
                if (!in_array((string)$claims['iss'], $validIssuers, true)) {
                    return [
                        'ok' => false,
                        'status' => 401,
                        'error_code' => 'GOOGLE_ISSUER_INVALID',
                        'message' => 'Google ID token issuer is invalid.',
                    ];
                }
            }

            if (isset($claims['exp']) && (int)$claims['exp'] < time()) {
                return [
                    'ok' => false,
                    'status' => 401,
                    'error_code' => 'GOOGLE_TOKEN_EXPIRED',
                    'message' => 'Google ID token has expired. Please sign in with Google again.',
                ];
            }
        }

        $emailVerifiedRaw = $claims['email_verified'] ?? false;
        $emailVerified = ($emailVerifiedRaw === true || $emailVerifiedRaw === 'true' || $emailVerifiedRaw === 1 || $emailVerifiedRaw === '1');
        if (!$emailVerified) {
            return [
                'ok' => false,
                'status' => 422,
                'error_code' => 'GOOGLE_EMAIL_UNVERIFIED',
                'message' => 'Your Google account email address must be verified before registering on AlumniIstü.',
            ];
        }

        $fullName = trim((string)($claims['name'] ?? ''));
        if ($fullName === '') {
            $given = trim((string)($claims['given_name'] ?? ''));
            $family = trim((string)($claims['family_name'] ?? ''));
            $fullName = trim("{$given} {$family}");
        }
        if ($fullName === '') {
            $fullName = explode('@', $email)[0];
        }

        $avatarUrl = trim((string)($claims['picture'] ?? '')) ?: null;

        return [
            'ok' => true,
            'status' => 200,
            'profile' => [
                'google_id' => $sub,
                'email' => $email,
                'email_verified' => true,
                'full_name' => $fullName,
                'avatar_url' => $avatarUrl,
            ],
        ];
    }

    /**
     * Perform an HTTP GET/POST JSON request using curl or stream fallback.
     *
     * @return array{ok:bool, status:int, body:?array}
     */
    private function httpRequest(string $method, string $url, array $formParams = [], array $headers = []): array {
        if ($this->httpRequester !== null) {
            return ($this->httpRequester)($method, $url, $formParams, $headers);
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

            if (strtoupper($method) === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formParams));
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
            if (!empty($headers)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            }

            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            return [
                'ok' => $status >= 200 && $status < 300 && is_array($decoded),
                'status' => $status,
                'body' => is_array($decoded) ? $decoded : null,
            ];
        }

        $httpOptions = [
            'method' => strtoupper($method),
            'timeout' => 8,
            'ignore_errors' => true,
        ];
        if (strtoupper($method) === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $httpOptions['content'] = http_build_query($formParams);
        }
        if (!empty($headers)) {
            $httpOptions['header'] = implode("\r\n", $headers) . "\r\n";
        }

        $context = stream_context_create(['http' => $httpOptions]);
        $raw = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $status = (int)$m[1];
            }
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return [
            'ok' => $status >= 200 && $status < 300 && is_array($decoded),
            'status' => $status,
            'body' => is_array($decoded) ? $decoded : null,
        ];
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
