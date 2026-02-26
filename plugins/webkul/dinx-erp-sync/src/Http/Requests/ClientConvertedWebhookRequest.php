<?php

namespace Webkul\DinxErpSync\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClientConvertedWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event'            => ['required', 'string', 'in:dinx.lead.client_converted'],
            'eventId'          => ['required', 'string', 'max:255'],
            'occurredAt'       => ['required', 'date'],
            'lead'             => ['required', 'array'],
            'lead.id'          => ['required', 'string', 'max:255'],
            'lead.firstName'   => ['required', 'string', 'max:255'],
            'lead.lastName'    => ['required', 'string', 'max:255'],
            'lead.funnelStage' => ['required', 'string', 'in:Client'],
            'lead.email'       => ['nullable', 'email', 'max:255'],
            'lead.phone'       => ['nullable', 'string', 'max:255'],
            'lead.jobTitle'    => ['nullable', 'string', 'max:255'],
            'lead.status'      => ['nullable', 'string', 'max:255'],
            'lead.source'      => ['nullable', 'string', 'max:255'],
            'account'          => ['nullable', 'array'],
            'account.id'       => ['nullable', 'string', 'max:255'],
            'account.name'     => ['nullable', 'string', 'max:255'],
            'account.industryId' => ['nullable', 'string', 'max:255'],
            'account.websiteUrl' => ['nullable', 'url', 'max:255'],
        ];
    }
}
