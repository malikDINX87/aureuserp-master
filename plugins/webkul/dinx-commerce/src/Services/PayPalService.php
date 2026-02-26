<?php

namespace Webkul\DinxCommerce\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Webkul\Account\Enums\PaymentType;
use Webkul\Account\Facades\Account as AccountFacade;
use Webkul\Account\Models\Invoice;
use Webkul\Account\Models\Journal;
use Webkul\Account\Models\PaymentRegister;
use Webkul\DinxCommerce\Models\DinxContractEvent;
use Webkul\DinxCommerce\Models\DinxContractPaymentLink;
use Webkul\DinxCommerce\Models\DinxPayPalOrder;
use Webkul\DinxCommerce\Settings\DinxCommerceSettings;

class PayPalService
{
    public function __construct(protected DinxCommerceSettings $settings)
    {
    }

    public function resolveMode(): string
    {
        $mode = strtolower(trim((string) ($this->settings->paypal_mode ?? env('PAYPAL_MODE', 'sandbox'))));

        return in_array($mode, ['live', 'sandbox'], true) ? $mode : 'sandbox';
    }

    public function resolveBaseUrl(): string
    {
        return $this->resolveMode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function resolveClientId(): string
    {
        return trim((string) ($this->settings->paypal_client_id ?? env('PAYPAL_CLIENT_ID', '')));
    }

    public function resolveClientSecret(): string
    {
        return trim((string) ($this->settings->paypal_client_secret ?? env('PAYPAL_CLIENT_SECRET', '')));
    }

    public function resolveWebhookId(): string
    {
        return trim((string) ($this->settings->paypal_webhook_id ?? env('PAYPAL_WEBHOOK_ID', '')));
    }

    public function resolveBrandName(): string
    {
        return trim((string) ($this->settings->paypal_brand_name ?? env('PAYPAL_BRAND_NAME', 'DINX')));
    }

    public function isConfigured(): bool
    {
        return $this->resolveClientId() !== '' && $this->resolveClientSecret() !== '';
    }

    public function getOrCreateApprovalUrl(Invoice $invoice, ?int $contractId = null): string
    {
        if (! $this->isConfigured()) {
            throw new InvalidArgumentException('PayPal credentials are not configured.');
        }

        $existing = DinxPayPalOrder::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('status', ['created', 'approved'])
            ->whereNotNull('approval_url')
            ->latest('id')
            ->first();

        if ($existing && $existing->approval_url) {
            return (string) $existing->approval_url;
        }

        $amount = (float) ($invoice->amount_residual ?? $invoice->amount_total ?? 0);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Invoice has no outstanding balance to pay.');
        }

        $currencyCode = strtoupper((string) ($invoice->currency?->name ?? 'USD'));
        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            $currencyCode = 'USD';
        }

        $accessToken = $this->issueAccessToken();

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($this->resolveBaseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'invoice-'.$invoice->id,
                    'invoice_id' => (string) $invoice->name,
                    'description' => 'DINX Invoice '.$invoice->name,
                    'amount' => [
                        'currency_code' => $currencyCode,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                ]],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'brand_name' => $this->resolveBrandName(),
                            'shipping_preference' => 'NO_SHIPPING',
                            'landing_page' => 'LOGIN',
                            'user_action' => 'PAY_NOW',
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new InvalidArgumentException('PayPal order creation failed: '.$response->body());
        }

        $body = $response->json();
        $paypalOrderId = (string) data_get($body, 'id');
        $approvalUrl = collect((array) data_get($body, 'links', []))
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if ($paypalOrderId === '' || ! is_string($approvalUrl) || $approvalUrl === '') {
            throw new InvalidArgumentException('PayPal order response did not include an approval URL.');
        }

        $record = DinxPayPalOrder::query()->updateOrCreate(
            ['paypal_order_id' => $paypalOrderId],
            [
                'invoice_id' => $invoice->id,
                'contract_id' => $contractId,
                'status' => (string) data_get($body, 'status', 'created'),
                'amount' => $amount,
                'currency' => $currencyCode,
                'approval_url' => $approvalUrl,
                'raw_payload' => $body,
            ]
        );

        if (! $record->contract_id && $contractId) {
            $record->forceFill(['contract_id' => $contractId])->save();
        }

        return $approvalUrl;
    }

    public function verifyWebhook(Request $request): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $webhookId = $this->resolveWebhookId();

        if ($webhookId === '') {
            return false;
        }

        $accessToken = $this->issueAccessToken();

        $payload = [
            'auth_algo' => $request->header('paypal-auth-algo'),
            'cert_url' => $request->header('paypal-cert-url'),
            'transmission_id' => $request->header('paypal-transmission-id'),
            'transmission_sig' => $request->header('paypal-transmission-sig'),
            'transmission_time' => $request->header('paypal-transmission-time'),
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($request->getContent(), true),
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($this->resolveBaseUrl().'/v1/notifications/verify-webhook-signature', $payload);

        if (! $response->successful()) {
            return false;
        }

        return strtoupper((string) data_get($response->json(), 'verification_status', '')) === 'SUCCESS';
    }

    public function handleWebhookEvent(array $event): array
    {
        $eventType = strtoupper((string) data_get($event, 'event_type', ''));
        $eventId = (string) data_get($event, 'id', '');

        $paypalOrderId = (string) (
            data_get($event, 'resource.supplementary_data.related_ids.order_id')
            ?? data_get($event, 'resource.id')
        );

        if ($paypalOrderId === '') {
            return [
                'handled' => false,
                'message' => 'Missing PayPal order ID in webhook payload.',
            ];
        }

        $order = DinxPayPalOrder::query()
            ->where('paypal_order_id', $paypalOrderId)
            ->first();

        if (! $order) {
            DinxContractEvent::query()->create([
                'provider' => 'paypal',
                'event_type' => $eventType === '' ? 'UNKNOWN' : $eventType,
                'provider_event_id' => $eventId !== '' ? $eventId : null,
                'status' => 'ignored',
                'payload' => $event,
                'message' => 'No dinx_paypal_orders record for incoming PayPal webhook.',
                'occurred_at' => now(),
            ]);

            return [
                'handled' => false,
                'message' => 'No local order matched webhook payload.',
            ];
        }

        $status = strtolower((string) data_get($event, 'resource.status', ''));

        DB::transaction(function () use ($order, $event, $eventType, $eventId, $status): void {
            $order->forceFill([
                'status' => $status !== '' ? $status : strtolower($eventType),
                'raw_payload' => $event,
            ])->save();

            DinxContractEvent::query()->create([
                'contract_id' => $order->contract_id,
                'provider' => 'paypal',
                'event_type' => $eventType,
                'provider_event_id' => $eventId !== '' ? $eventId : null,
                'status' => $status,
                'payload' => $event,
                'occurred_at' => now(),
            ]);
        });

        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $captureId = (string) data_get($event, 'resource.id', '');
            $captureAmount = (float) data_get($event, 'resource.amount.value', $order->amount);
            $captureCurrency = (string) data_get($event, 'resource.amount.currency_code', $order->currency);

            $this->reconcileInvoicePayment($order, $captureId, $captureAmount, $captureCurrency);
        }

        return [
            'handled' => true,
            'message' => 'Webhook processed.',
        ];
    }

    public function reconcileInvoicePayment(DinxPayPalOrder $order, string $captureId, float $captureAmount, string $captureCurrency): void
    {
        $invoice = Invoice::query()
            ->with(['paymentTermLines', 'lines', 'company', 'currency'])
            ->find($order->invoice_id);

        if (! $invoice) {
            throw new InvalidArgumentException('Invoice not found for PayPal order.');
        }

        if ($captureId !== '' && $order->paypal_capture_id === $captureId && $order->processed_at) {
            return;
        }

        $lineIds = $invoice->paymentTermLines
            ->filter(fn ($line) => ! $line->reconciled)
            ->pluck('id')
            ->toArray();

        if (empty($lineIds)) {
            $order->forceFill([
                'status' => 'captured',
                'paypal_capture_id' => $captureId !== '' ? $captureId : $order->paypal_capture_id,
                'processed_at' => now(),
            ])->save();

            return;
        }

        $paymentRegister = new PaymentRegister;
        $paymentRegister->lines = $invoice->lines;
        $paymentRegister->company = $invoice->company;
        $paymentRegister->currency = $invoice->currency;
        $paymentRegister->currency_id = $invoice->currency_id;
        $paymentRegister->payment_type = $invoice->isInbound(true)
            ? PaymentType::RECEIVE
            : PaymentType::SEND;
        $paymentRegister->computeBatches();
        $paymentRegister->computeAvailableJournalIds();
        $paymentRegister->journal_id = $paymentRegister->available_journal_ids[0] ?? null;

        if (! $paymentRegister->journal_id) {
            throw new InvalidArgumentException('No valid journal was found to reconcile PayPal capture.');
        }

        $paymentRegister->journal = Journal::find($paymentRegister->journal_id);
        $paymentRegister->computePaymentMethodLineId();

        if (! $paymentRegister->payment_method_line_id) {
            throw new InvalidArgumentException('No payment method line available for selected journal.');
        }

        $amountsToPay = $paymentRegister->getTotalAmountsToPay($paymentRegister->batches);
        $defaultAmount = (float) data_get($amountsToPay, 'amount_by_default', $captureAmount);
        $fullAmount = (float) data_get($amountsToPay, 'full_amount', $captureAmount);
        $amountToApply = min(max($captureAmount, 0.01), max($defaultAmount, $fullAmount));

        $register = PaymentRegister::query()->create([
            'journal_id' => $paymentRegister->journal_id,
            'payment_method_line_id' => $paymentRegister->payment_method_line_id,
            'currency_id' => $invoice->currency_id,
            'payment_date' => now()->toDateString(),
            'communication' => 'PayPal capture '.($captureId !== '' ? $captureId : $order->paypal_order_id),
            'amount' => $amountToApply,
        ]);

        $register->lines()->sync($lineIds);
        $register->refresh();
        $register->computeFromLines();
        $register->save();

        AccountFacade::createPayments($register);

        $order->forceFill([
            'status' => 'captured',
            'currency' => strtoupper(trim($captureCurrency)) !== '' ? strtoupper(trim($captureCurrency)) : $order->currency,
            'paypal_capture_id' => $captureId !== '' ? $captureId : $order->paypal_capture_id,
            'processed_at' => now(),
        ])->save();

        if ($order->contract_id) {
            $payments = $invoice->matchedPayments()->get();

            foreach ($payments as $payment) {
                DinxContractPaymentLink::query()->firstOrCreate([
                    'contract_id' => $order->contract_id,
                    'payment_id' => $payment->id,
                ]);
            }
        }
    }

    protected function issueAccessToken(): string
    {
        $response = Http::withBasicAuth($this->resolveClientId(), $this->resolveClientSecret())
            ->asForm()
            ->acceptJson()
            ->post($this->resolveBaseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            throw new InvalidArgumentException('Failed to obtain PayPal access token: '.$response->body());
        }

        $token = trim((string) data_get($response->json(), 'access_token', ''));

        if ($token === '') {
            throw new InvalidArgumentException('PayPal access token was not returned by API.');
        }

        return $token;
    }
}
