<?php

namespace Webkul\DinxCommerce\Filament\Admin\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Account\Enums\MoveState;
use UnitEnum;
use Webkul\Account\Models\Invoice;
use Webkul\DinxCommerce\Models\DinxContractEvent;
use Webkul\DinxCommerce\Models\DinxContractInvoiceLink;
use Webkul\DinxCommerce\Models\DinxPayPalOrder;
use Webkul\DinxCommerce\Models\DinxProjectInvoiceLink;
use Webkul\DinxCommerce\Models\DinxRecurringInvoiceProfile;
use Webkul\DinxCommerce\Services\PayPalService;
use Webkul\DinxCommerce\Services\RecurringInvoiceService;
use Webkul\DinxCommerce\Settings\DinxWorkspaceSettings;

class InvoicesHub extends Page
{
    use HasPageShield;

    protected string $view = 'dinx-commerce::filament.admin.pages.invoices-hub';

    protected static ?string $slug = 'erp/invoices';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'DINX ERP';

    protected static ?int $navigationSort = 30;

    public string $statusFilter = 'all';

    public string $search = '';

    public ?int $activityInvoiceId = null;

    public float $billableRate = 150.0;

    protected static function getPagePermission(): ?string
    {
        return 'page_dinx_workspace_invoices';
    }

    public static function getNavigationLabel(): string
    {
        return 'Invoices';
    }

    public function getNewInvoiceUrlProperty(): string
    {
        if (class_exists(\Webkul\Accounting\Filament\Clusters\Customers\Resources\InvoiceResource::class)) {
            return \Webkul\Accounting\Filament\Clusters\Customers\Resources\InvoiceResource::getUrl('create');
        }

        if (class_exists(\Webkul\Account\Filament\Resources\InvoiceResource::class)) {
            return \Webkul\Account\Filament\Resources\InvoiceResource::getUrl('create');
        }

        return '/admin/erp/invoices';
    }

    public function mount(): void
    {
        $settings = app(DinxWorkspaceSettings::class);

        $this->billableRate = (float) $settings->project_default_billable_hourly_rate;
    }

    public function setStatusFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'unpaid', 'overdue', 'paid', 'draft'], true)) {
            $this->statusFilter = $filter;
        }
    }

    public function openInvoice(int $invoiceId): RedirectResponse
    {
        if (class_exists(\Webkul\Accounting\Filament\Clusters\Customers\Resources\InvoiceResource::class)) {
            return redirect()->to(\Webkul\Accounting\Filament\Clusters\Customers\Resources\InvoiceResource::getUrl('view', ['record' => $invoiceId]));
        }

        if (class_exists(\Webkul\Account\Filament\Resources\InvoiceResource::class)) {
            return redirect()->to(\Webkul\Account\Filament\Resources\InvoiceResource::getUrl('view', ['record' => $invoiceId]));
        }

        return redirect()->to('/admin/erp/invoices');
    }

    public function generatePayPalLink(int $invoiceId): void
    {
        $invoice = Invoice::query()->find($invoiceId);

        if (! $invoice) {
            Notification::make()->title('Invoice not found')->danger()->send();

            return;
        }

        try {
            $contractId = DinxContractInvoiceLink::query()
                ->where('invoice_id', $invoiceId)
                ->value('contract_id');

            $url = app(PayPalService::class)->getOrCreateApprovalUrl($invoice, $contractId ? (int) $contractId : null);

            Notification::make()
                ->title('PayPal link generated')
                ->body($url)
                ->success()
                ->persistent()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Failed to generate PayPal link')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function createRecurringProfile(int $invoiceId): void
    {
        $invoice = Invoice::query()->find($invoiceId);

        if (! $invoice) {
            Notification::make()->title('Invoice not found')->danger()->send();

            return;
        }

        DinxRecurringInvoiceProfile::query()->updateOrCreate(
            ['source_invoice_id' => $invoiceId],
            [
                'name' => 'Recurring '.$invoice->name,
                'partner_id' => $invoice->partner_id,
                'interval' => 'monthly',
                'day_of_month' => 1,
                'next_run_at' => app(RecurringInvoiceService::class)->nextRunAt(new DinxRecurringInvoiceProfile([
                    'day_of_month' => 1,
                ]), now()),
                'auto_send' => false,
                'allow_paypal' => true,
                'is_active' => true,
                'currency' => $invoice->currency?->name,
                'created_by' => Auth::id(),
                'metadata' => [
                    'origin' => 'invoices_hub',
                ],
            ]
        );

        Notification::make()
            ->title('Recurring profile saved')
            ->body('Invoice '.$invoice->name.' will be generated monthly.')
            ->success()
            ->send();
    }

    public function openLinkedContract(int $invoiceId): RedirectResponse
    {
        $contractId = DinxContractInvoiceLink::query()
            ->where('invoice_id', $invoiceId)
            ->value('contract_id');

        if (! $contractId) {
            Notification::make()
                ->title('No contract linked')
                ->body('This invoice is not currently linked to a contract.')
                ->warning()
                ->send();

            return redirect()->to('/admin/erp/invoices');
        }

        return redirect()->to('/admin/dinx-contracts/'.$contractId);
    }

    public function showActivity(int $invoiceId): void
    {
        $this->activityInvoiceId = $invoiceId;
    }

    public function hideActivity(): void
    {
        $this->activityInvoiceId = null;
    }

    public function getMoneyBarProperty(): array
    {
        if (! Schema::hasTable('accounts_account_moves')) {
            return [
                'unbilled' => 0.0,
                'unpaid' => 0.0,
                'paid' => 0.0,
            ];
        }

        $hours = 0.0;

        if (Schema::hasTable('analytic_records') && Schema::hasTable('dinx_project_invoice_links')) {
            $hours = (float) DB::table('analytic_records as ar')
                ->leftJoin('dinx_project_invoice_links as pil', 'pil.project_id', '=', 'ar.project_id')
                ->whereNotNull('ar.project_id')
                ->whereNull('pil.id')
                ->whereBetween('ar.date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->sum('ar.unit_amount');
        }

        $unbilled = $hours * $this->billableRate;

        $unpaid = (float) DB::table('accounts_account_moves')
            ->where('state', 'posted')
            ->whereIn('payment_state', ['not_paid', 'partial', 'in_payment'])
            ->whereIn('move_type', ['out_invoice', 'out_receipt'])
            ->sum('amount_residual');

        $paid = (float) DB::table('accounts_account_moves')
            ->where('state', 'posted')
            ->where('payment_state', 'paid')
            ->whereIn('move_type', ['out_invoice', 'out_receipt'])
            ->where('updated_at', '>=', now()->subDays(30))
            ->sum('amount_total');

        return [
            'unbilled' => round($unbilled, 2),
            'unpaid' => round($unpaid, 2),
            'paid' => round($paid, 2),
        ];
    }

    public function getInvoicesProperty(): array
    {
        if (! Schema::hasTable('accounts_account_moves')) {
            return [];
        }

        $query = Invoice::query()
            ->with(['partner:id,name', 'currency:id,name'])
            ->whereIn('move_type', ['out_invoice', 'out_receipt'])
            ->latest('id');

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', $term)
                    ->orWhereHas('partner', fn ($q) => $q->where('name', 'like', $term));
            });
        }

        if ($this->statusFilter === 'draft') {
            $query->where('state', 'draft');
        }

        if ($this->statusFilter === 'unpaid') {
            $query->where('state', 'posted')
                ->whereIn('payment_state', ['not_paid', 'partial', 'in_payment']);
        }

        if ($this->statusFilter === 'overdue') {
            $query->where('state', 'posted')
                ->whereDate('invoice_date_due', '<', now()->toDateString())
                ->whereIn('payment_state', ['not_paid', 'partial', 'in_payment']);
        }

        if ($this->statusFilter === 'paid') {
            $query->where('payment_state', 'paid');
        }

        return $query->limit(250)->get()->map(function (Invoice $invoice) {
            $paypalOrder = DinxPayPalOrder::query()
                ->where('invoice_id', $invoice->id)
                ->latest('id')
                ->first();

            $state = (string) ($invoice->state?->value ?? $invoice->state);
            $paymentState = (string) ($invoice->payment_state?->value ?? $invoice->payment_state);

            $isOverdue = $state === MoveState::POSTED->value
                && in_array($paymentState, ['not_paid', 'partial', 'in_payment'], true)
                && $invoice->invoice_date_due
                && $invoice->invoice_date_due->lt(now());

            return [
                'id' => $invoice->id,
                'name' => $invoice->name,
                'client' => $invoice->partner?->name ?? 'Unknown client',
                'issue_date' => $invoice->invoice_date,
                'due_date' => $invoice->invoice_date_due,
                'amount_total' => (float) $invoice->amount_total,
                'amount_residual' => (float) $invoice->amount_residual,
                'currency' => $invoice->currency?->name ?? 'USD',
                'state' => $state,
                'payment_state' => $paymentState,
                'is_move_sent' => (bool) $invoice->is_move_sent,
                'is_overdue' => $isOverdue,
                'paypal_status' => $paypalOrder?->status,
                'paypal_enabled' => (bool) $paypalOrder,
            ];
        })->toArray();
    }

    public function getInvoiceActivityProperty(): array
    {
        if (! $this->activityInvoiceId) {
            return [];
        }

        if (! Schema::hasTable('accounts_account_moves')) {
            return [];
        }

        $invoice = Invoice::query()->find($this->activityInvoiceId);

        if (! $invoice) {
            return [];
        }

        $events = [
            [
                'label' => 'Created',
                'time' => $invoice->created_at,
                'meta' => 'Invoice generated in ERP',
            ],
        ];

        if ($invoice->is_move_sent) {
            $events[] = [
                'label' => 'Sent',
                'time' => $invoice->updated_at,
                'meta' => 'Invoice sent to client',
            ];
        }

        $paypalEvents = collect();

        if (Schema::hasTable('dinx_contract_events') && Schema::hasTable('dinx_paypal_orders')) {
            $paypalEvents = DinxContractEvent::query()
                ->where('provider', 'paypal')
                ->whereJsonContains('payload->resource->supplementary_data->related_ids->order_id', DinxPayPalOrder::query()->where('invoice_id', $invoice->id)->value('paypal_order_id'))
                ->orderBy('occurred_at')
                ->get();
        }

        foreach ($paypalEvents as $event) {
            $events[] = [
                'label' => strtoupper((string) $event->event_type),
                'time' => $event->occurred_at,
                'meta' => $event->message ?: 'PayPal event received',
            ];
        }

        if ((string) $invoice->payment_state->value === 'paid') {
            $events[] = [
                'label' => 'Paid',
                'time' => $invoice->updated_at,
                'meta' => 'Payment received',
            ];
        }

        return collect($events)
            ->sortBy(fn ($event) => $event['time'])
            ->values()
            ->all();
    }
}
