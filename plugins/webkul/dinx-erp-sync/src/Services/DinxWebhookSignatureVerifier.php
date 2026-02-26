<?php

namespace Webkul\DinxErpSync\Services;

use Illuminate\Http\Request;
use Webkul\DinxErpSync\Settings\DinxErpSyncSettings;

class DinxWebhookSignatureVerifier
{
    public function __construct(protected DinxErpSyncSettings $settings)
    {
    }

    public function isEnabled(): bool
    {
        if ($this->settings->enabled !== null) {
            return (bool) $this->settings->enabled;
        }

        $configured = config('services.dinx_erp_sync.enabled');

        if (is_bool($configured)) {
            return $configured;
        }

        return $this->parseBoolean((string) $configured, true);
    }

    public function resolveWebhookSecret(): string
    {
        $secret = trim((string) ($this->settings->webhook_secret ?? ''));

        if ($secret !== '') {
            return $secret;
        }

        return trim((string) config('services.dinx_erp_sync.webhook_secret', ''));
    }

    public function resolveMaxTimestampSkewSeconds(): int
    {
        if (is_numeric($this->settings->max_timestamp_skew_seconds) && (int) $this->settings->max_timestamp_skew_seconds > 0) {
            return (int) $this->settings->max_timestamp_skew_seconds;
        }

        $envValue = (int) config('services.dinx_erp_sync.max_skew_seconds', 300);

        return max(30, $envValue);
    }

    public function resolveProcessingQueue(): ?string
    {
        $queue = trim((string) ($this->settings->processing_queue ?? ''));

        if ($queue !== '') {
            return $queue;
        }

        $envQueue = trim((string) config('services.dinx_erp_sync.queue', ''));

        return $envQueue === '' ? null : $envQueue;
    }

    public function verify(Request $request): array
    {
        $secret = $this->resolveWebhookSecret();

        if ($secret === '') {
            return [
                'valid'   => false,
                'message' => 'Webhook secret is not configured',
            ];
        }

        $timestampHeader = trim((string) $request->header('X-DINX-Timestamp', ''));
        $signatureHeader = trim((string) $request->header('X-DINX-Signature', ''));

        if ($timestampHeader === '' || $signatureHeader === '') {
            return [
                'valid'   => false,
                'message' => 'Missing webhook signature headers',
            ];
        }

        if (! preg_match('/^\d+$/', $timestampHeader)) {
            return [
                'valid'   => false,
                'message' => 'Invalid webhook timestamp',
            ];
        }

        $maxSkew = $this->resolveMaxTimestampSkewSeconds();
        $requestTimestamp = (int) $timestampHeader;

        if (abs(now()->timestamp - $requestTimestamp) > $maxSkew) {
            return [
                'valid'   => false,
                'message' => 'Webhook timestamp is outside allowed skew window',
            ];
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $timestampHeader.'.'.$request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signatureHeader)) {
            return [
                'valid'   => false,
                'message' => 'Invalid webhook signature',
            ];
        }

        return [
            'valid'   => true,
            'message' => null,
        ];
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
