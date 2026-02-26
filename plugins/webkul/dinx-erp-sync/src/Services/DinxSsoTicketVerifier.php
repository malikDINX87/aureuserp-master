<?php

namespace Webkul\DinxErpSync\Services;

use Illuminate\Support\Facades\Cache;
use Webkul\DinxErpSync\Settings\DinxErpSyncSettings;

class DinxSsoTicketVerifier
{
    public function __construct(protected DinxErpSyncSettings $settings)
    {
    }

    public function isEnabled(): bool
    {
        if ($this->settings->sso_enabled !== null) {
            return (bool) $this->settings->sso_enabled;
        }

        $configured = config('services.dinx_erp_sso.enabled');

        if (is_bool($configured)) {
            return $configured;
        }

        return $this->parseBoolean((string) $configured, true);
    }

    public function resolveSharedSecret(): string
    {
        $secret = trim((string) ($this->settings->sso_shared_secret ?? ''));

        if ($secret !== '') {
            return $secret;
        }

        return trim((string) config('services.dinx_erp_sso.shared_secret', ''));
    }

    public function resolveIssuer(): string
    {
        $issuer = trim((string) ($this->settings->sso_issuer ?? ''));

        if ($issuer !== '') {
            return $issuer;
        }

        return trim((string) config('services.dinx_erp_sso.issuer', 'dinxsolutions.com'));
    }

    public function resolveAudience(): string
    {
        $audience = trim((string) ($this->settings->sso_audience ?? ''));

        if ($audience !== '') {
            return $audience;
        }

        return trim((string) config('services.dinx_erp_sso.audience', 'dinx-erp'));
    }

    public function resolveMaxClockSkewSeconds(): int
    {
        if (is_numeric($this->settings->sso_max_clock_skew_seconds) && (int) $this->settings->sso_max_clock_skew_seconds > 0) {
            return max(5, (int) $this->settings->sso_max_clock_skew_seconds);
        }

        return max(5, (int) config('services.dinx_erp_sso.max_clock_skew_seconds', 60));
    }

    public function resolveJtiTtlSeconds(): int
    {
        if (is_numeric($this->settings->sso_jti_ttl_seconds) && (int) $this->settings->sso_jti_ttl_seconds > 0) {
            return max(60, (int) $this->settings->sso_jti_ttl_seconds);
        }

        return max(60, (int) config('services.dinx_erp_sso.jti_ttl_seconds', 600));
    }

    public function verify(?string $ticket): array
    {
        $secret = $this->resolveSharedSecret();

        if ($secret === '') {
            return [
                'valid' => false,
                'message' => 'SSO shared secret is not configured.',
                'claims' => null,
            ];
        }

        if (! is_string($ticket) || trim($ticket) === '') {
            return [
                'valid' => false,
                'message' => 'Missing SSO ticket.',
                'claims' => null,
            ];
        }

        $segments = explode('.', trim($ticket));

        if (count($segments) !== 3) {
            return [
                'valid' => false,
                'message' => 'Malformed SSO ticket.',
                'claims' => null,
            ];
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $segments;

        $header = $this->decodeSegment($encodedHeader);
        $claims = $this->decodeSegment($encodedPayload);

        if (! is_array($header) || ! is_array($claims)) {
            return [
                'valid' => false,
                'message' => 'Invalid SSO ticket encoding.',
                'claims' => null,
            ];
        }

        if (($header['alg'] ?? null) !== 'HS256') {
            return [
                'valid' => false,
                'message' => 'Unsupported SSO signing algorithm.',
                'claims' => null,
            ];
        }

        $providedSignature = $this->base64UrlDecode($encodedSignature);
        if ($providedSignature === false) {
            return [
                'valid' => false,
                'message' => 'Invalid SSO signature.',
                'claims' => null,
            ];
        }

        $expectedSignature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $secret, true);

        if (! hash_equals($expectedSignature, $providedSignature)) {
            return [
                'valid' => false,
                'message' => 'SSO signature verification failed.',
                'claims' => null,
            ];
        }

        $requiredStringClaims = ['iss', 'aud', 'sub', 'email', 'name', 'jti'];

        foreach ($requiredStringClaims as $claim) {
            if (! is_string($claims[$claim] ?? null) || trim((string) $claims[$claim]) === '') {
                return [
                    'valid' => false,
                    'message' => sprintf('SSO ticket is missing required claim: %s.', $claim),
                    'claims' => null,
                ];
            }
        }

        foreach (['iat', 'nbf', 'exp'] as $claim) {
            if (! is_numeric($claims[$claim] ?? null)) {
                return [
                    'valid' => false,
                    'message' => sprintf('SSO ticket has invalid claim: %s.', $claim),
                    'claims' => null,
                ];
            }
        }

        if (! isset($claims['isGlobalAdmin']) || ! is_bool($claims['isGlobalAdmin'])) {
            return [
                'valid' => false,
                'message' => 'SSO ticket has invalid isGlobalAdmin claim.',
                'claims' => null,
            ];
        }

        $issuer = $this->resolveIssuer();
        if ($issuer !== '' && ! hash_equals($issuer, (string) $claims['iss'])) {
            return [
                'valid' => false,
                'message' => 'SSO ticket issuer mismatch.',
                'claims' => null,
            ];
        }

        $audience = $this->resolveAudience();
        if ($audience !== '' && ! hash_equals($audience, (string) $claims['aud'])) {
            return [
                'valid' => false,
                'message' => 'SSO ticket audience mismatch.',
                'claims' => null,
            ];
        }

        $now = now()->timestamp;
        $skew = $this->resolveMaxClockSkewSeconds();
        $iat = (int) $claims['iat'];
        $nbf = (int) $claims['nbf'];
        $exp = (int) $claims['exp'];

        if ($nbf > ($now + $skew)) {
            return [
                'valid' => false,
                'message' => 'SSO ticket is not active yet.',
                'claims' => null,
            ];
        }

        if ($iat > ($now + $skew)) {
            return [
                'valid' => false,
                'message' => 'SSO ticket issued-at claim is invalid.',
                'claims' => null,
            ];
        }

        if ($exp < ($now - $skew)) {
            return [
                'valid' => false,
                'message' => 'SSO ticket has expired.',
                'claims' => null,
            ];
        }

        $jti = trim((string) $claims['jti']);
        $cacheTtl = max(1, min($this->resolveJtiTtlSeconds(), max(1, ($exp - $now + $skew))));
        $cacheKey = 'dinx_erp_sync:sso:jti:'.$jti;

        if (! Cache::add($cacheKey, 1, now()->addSeconds($cacheTtl))) {
            return [
                'valid' => false,
                'message' => 'SSO ticket replay detected.',
                'claims' => null,
            ];
        }

        return [
            'valid' => true,
            'message' => null,
            'claims' => [
                'iss' => (string) $claims['iss'],
                'aud' => (string) $claims['aud'],
                'sub' => (string) $claims['sub'],
                'email' => strtolower(trim((string) $claims['email'])),
                'name' => trim((string) $claims['name']),
                'crmRole' => is_string($claims['crmRole'] ?? null) ? trim((string) $claims['crmRole']) : null,
                'isGlobalAdmin' => (bool) $claims['isGlobalAdmin'],
                'jti' => $jti,
                'iat' => $iat,
                'nbf' => $nbf,
                'exp' => $exp,
                'target' => is_string($claims['target'] ?? null) ? trim((string) $claims['target']) : null,
            ],
        ];
    }

    protected function decodeSegment(string $segment): ?array
    {
        $decoded = $this->base64UrlDecode($segment);

        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);

        return is_array($data) ? $data : null;
    }

    protected function base64UrlDecode(string $value): string|false
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }

    protected function parseBoolean(string $value, bool $fallback): bool
    {
        $normalized = strtolower(trim($value));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $fallback;
    }
}
