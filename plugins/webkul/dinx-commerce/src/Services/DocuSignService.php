<?php

namespace Webkul\DinxCommerce\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\DinxCommerce\Models\DinxContract;
use Webkul\DinxCommerce\Models\DinxContractEvent;
use Webkul\DinxCommerce\Models\DinxContractVersion;
use Webkul\DinxCommerce\Settings\DinxCommerceSettings;

class DocuSignService
{
    public function __construct(protected DinxCommerceSettings $settings)
    {
    }

    public function resolveWebhookSecret(): string
    {
        return trim((string) ($this->settings->docusign_webhook_secret ?? env('DOCUSIGN_WEBHOOK_SECRET', '')));
    }

    public function resolveBaseUri(): ?string
    {
        $value = trim((string) ($this->settings->docusign_base_uri ?? env('DOCUSIGN_BASE_URI', '')));

        return $value !== '' ? rtrim($value, '/') : null;
    }

    public function sendEnvelope(DinxContract $contract): array
    {
        $envelopeId = $contract->docusign_envelope_id ?: (string) Str::uuid();

        DB::transaction(function () use ($contract, $envelopeId): void {
            $contract->forceFill([
                'docusign_envelope_id' => $envelopeId,
                'status' => 'sent',
            ])->save();

            DinxContractEvent::query()->create([
                'contract_id' => $contract->id,
                'provider' => 'docusign',
                'event_type' => 'sent',
                'provider_event_id' => $envelopeId,
                'status' => 'sent',
                'payload' => [
                    'event' => 'sent',
                    'envelope_id' => $envelopeId,
                    'base_uri' => $this->resolveBaseUri(),
                ],
                'message' => 'Contract sent via DocuSign workflow.',
                'occurred_at' => now(),
            ]);

            $this->snapshotVersion($contract, 'Sent via DocuSign');
        });

        return [
            'ok' => true,
            'message' => 'Contract sent via DocuSign.',
            'envelope_id' => $envelopeId,
        ];
    }

    public function sendReminder(DinxContract $contract): array
    {
        $envelopeId = $contract->docusign_envelope_id ?: (string) Str::uuid();
        $status = $contract->status === 'draft' ? 'sent' : $contract->status;

        DB::transaction(function () use ($contract, $envelopeId, $status): void {
            $contract->forceFill([
                'docusign_envelope_id' => $envelopeId,
                'status' => $status,
            ])->save();

            DinxContractEvent::query()->create([
                'contract_id' => $contract->id,
                'provider' => 'docusign',
                'event_type' => 'reminder',
                'provider_event_id' => (string) Str::uuid(),
                'status' => $status,
                'payload' => [
                    'event' => 'reminder',
                    'envelope_id' => $envelopeId,
                ],
                'message' => 'Client reminder sent for DocuSign envelope.',
                'occurred_at' => now(),
            ]);

            $this->snapshotVersion($contract, 'Reminder Sent');
        });

        return [
            'ok' => true,
            'message' => 'DocuSign reminder sent.',
            'envelope_id' => $envelopeId,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = $this->resolveWebhookSecret();

        if ($secret === '') {
            return false;
        }

        $signature = trim((string) ($request->header('X-DocuSign-Signature-1') ?: $request->header('X-Docusign-Signature-1')));

        if ($signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($expected, $signature);
    }

    public function handleWebhookEvent(array $payload): array
    {
        $envelopeId = trim((string) (data_get($payload, 'data.envelopeId')
            ?? data_get($payload, 'envelopeId')
            ?? data_get($payload, 'envelopeSummary.envelopeId')));

        $status = strtolower(trim((string) (data_get($payload, 'data.envelopeSummary.status')
            ?? data_get($payload, 'status')
            ?? data_get($payload, 'event'))));

        $eventId = trim((string) (data_get($payload, 'eventId')
            ?? data_get($payload, 'data.eventId')
            ?? Str::uuid()->toString()));

        $contract = null;
        if ($envelopeId !== '') {
            $contract = DinxContract::query()
                ->where('docusign_envelope_id', $envelopeId)
                ->first();
        }

        DB::transaction(function () use ($contract, $payload, $eventId, $status): void {
            DinxContractEvent::query()->create([
                'contract_id' => $contract?->id,
                'provider' => 'docusign',
                'event_type' => $status !== '' ? $status : 'unknown',
                'provider_event_id' => $eventId,
                'status' => $status,
                'payload' => $payload,
                'occurred_at' => now(),
            ]);

            if (! $contract) {
                return;
            }

            $mappedStatus = match (true) {
                str_contains($status, 'complete') || str_contains($status, 'completed') => 'completed',
                str_contains($status, 'decline') => 'declined',
                str_contains($status, 'void') => 'voided',
                str_contains($status, 'deliver') || str_contains($status, 'view') => 'viewed',
                str_contains($status, 'sent') => 'sent',
                default => $contract->status,
            };

            $updates = [
                'status' => $mappedStatus,
            ];

            if ($mappedStatus === 'completed') {
                $updates['signed_at'] = now();

                $documentPath = data_get($payload, 'data.envelopeSummary.documentsCombinedUri');
                if (is_string($documentPath) && $documentPath !== '') {
                    $updates['signed_document_path'] = $documentPath;
                }
            }

            $contract->forceFill($updates)->save();
        });

        return [
            'handled' => true,
            'message' => $contract
                ? 'DocuSign event applied to contract '.$contract->id
                : 'DocuSign event logged without matching contract.',
        ];
    }

    protected function snapshotVersion(DinxContract $contract, ?string $label = null): void
    {
        $version = ((int) DinxContractVersion::query()
            ->where('contract_id', $contract->id)
            ->max('version_number')) + 1;

        DinxContractVersion::query()->create([
            'contract_id' => $contract->id,
            'version_number' => $version,
            'label' => $label,
            'status' => $contract->status,
            'terms_html' => $contract->terms_html,
            'snapshot' => $contract->only([
                'title',
                'status',
                'amount_total',
                'effective_date',
                'expiration_date',
                'docusign_envelope_id',
                'signed_document_path',
                'signed_at',
            ]),
            'created_by' => Auth::id(),
        ]);
    }
}
