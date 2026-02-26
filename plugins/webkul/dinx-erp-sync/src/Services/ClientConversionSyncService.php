<?php

namespace Webkul\DinxErpSync\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Webkul\DinxErpSync\Models\DinxSyncLog;
use Webkul\DinxErpSync\Models\DinxSyncMapping;
use Webkul\Partner\Models\Partner;

class ClientConversionSyncService
{
    public function process(DinxSyncLog $syncLog): DinxSyncLog
    {
        $payload = (array) ($syncLog->payload ?? []);
        $lead = (array) data_get($payload, 'lead', []);

        $externalLeadId = $this->normalizeString(data_get($lead, 'id'));
        if (! $externalLeadId) {
            throw new InvalidArgumentException('Payload is missing lead.id');
        }

        $funnelStage = $this->normalizeString(data_get($lead, 'funnelStage'));
        if ($funnelStage !== 'Client') {
            throw new InvalidArgumentException('Payload lead.funnelStage must be Client');
        }

        $firstName = $this->normalizeString(data_get($lead, 'firstName'));
        $lastName = $this->normalizeString(data_get($lead, 'lastName'));

        $partner = DB::transaction(function () use ($externalLeadId, $syncLog, $payload, $firstName, $lastName, $lead) {
            $mapping = DinxSyncMapping::query()
                ->where('external_lead_id', $externalLeadId)
                ->lockForUpdate()
                ->first();

            $partner = null;
            if ($mapping?->partner_id) {
                $partner = Partner::withTrashed()->find($mapping->partner_id);
            }

            if (! $partner) {
                $partner = new Partner;
            }

            if (method_exists($partner, 'trashed') && $partner->trashed()) {
                $partner->restore();
            }

            $partner->fill($this->buildPartnerAttributes($externalLeadId, $firstName, $lastName, $lead));
            $partner->save();

            if (! $mapping) {
                $mapping = new DinxSyncMapping;
                $mapping->external_lead_id = $externalLeadId;
            }

            $mapping->partner_id = $partner->id;
            $mapping->last_delivery_id = $syncLog->delivery_id;
            $mapping->last_synced_at = now();
            $mapping->metadata = [
                'event'      => data_get($payload, 'event'),
                'eventId'    => data_get($payload, 'eventId'),
                'occurredAt' => data_get($payload, 'occurredAt'),
            ];
            $mapping->save();

            return $partner;
        });

        $syncLog->forceFill([
            'status'           => 'processed',
            'external_lead_id' => $externalLeadId,
            'partner_id'       => $partner->id,
            'error_message'    => null,
            'processed_at'     => now(),
        ])->save();

        return $syncLog->fresh();
    }

    protected function buildPartnerAttributes(string $externalLeadId, ?string $firstName, ?string $lastName, array $lead): array
    {
        $name = trim(implode(' ', array_filter([$firstName, $lastName])));

        if ($name === '') {
            $name = 'DINX Lead '.$externalLeadId;
        }

        $attributes = [
            'account_type' => 'individual',
            'sub_type'     => 'partner',
            'name'         => $name,
        ];

        $email = $this->normalizeString(data_get($lead, 'email'));
        if ($email !== null) {
            $attributes['email'] = $email;
        }

        $phone = $this->normalizeString(data_get($lead, 'phone'));
        if ($phone !== null) {
            $attributes['phone'] = $phone;
        }

        $jobTitle = $this->normalizeString(data_get($lead, 'jobTitle'));
        if ($jobTitle !== null) {
            $attributes['job_title'] = $jobTitle;
        }

        return $attributes;
    }

    protected function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
