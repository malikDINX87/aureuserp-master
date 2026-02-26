<?php

namespace Webkul\DinxCommerce\Filament\Admin\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Account\Enums\JournalType;
use Webkul\Account\Enums\MoveState;
use Webkul\Account\Enums\MoveType;
use Webkul\Account\Enums\PaymentState;
use Webkul\Account\Models\Journal;
use UnitEnum;
use Webkul\Account\Models\Invoice;
use Webkul\Account\Models\Partner;
use Webkul\DinxCommerce\Models\DinxContract;
use Webkul\DinxCommerce\Models\DinxContractEvent;
use Webkul\DinxCommerce\Models\DinxContractInvoiceLink;
use Webkul\DinxCommerce\Models\DinxPayPalOrder;
use Webkul\DinxCommerce\Models\DinxProjectInvoiceLink;
use Webkul\DinxCommerce\Models\DinxRecurringInvoiceProfile;
use Webkul\DinxCommerce\Services\PayPalService;
use Webkul\DinxCommerce\Services\RecurringInvoiceService;
use Webkul\DinxCommerce\Settings\DinxWorkspaceSettings;
use Webkul\Support\Models\Currency;

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

    public bool $showCreatePayPalModal = false;

    /**
     * @var array<string, mixed>
     */
    public array $paypalCreateForm = [];

    public ?string $latestPayPalApprovalUrl = null;

    public ?int $latestPayPalInvoiceId = null;

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

        $this->resetPayPalCreateForm();
    }

    public function openCreatePayPalModal(): void
    {
        $this->resetPayPalCreateForm();
        $this->showCreatePayPalModal = true;
    }

    public function closeCreatePayPalModal(): void
    {
        $this->showCreatePayPalModal = false;
    }

    public function createPayPalInvoice(): void
    {
        $data = $this->validate([
            'paypalCreateForm.partner_id' => ['required', 'integer', 'exists:partners_partners,id'],
            'paypalCreateForm.amount' => ['required', 'numeric', 'min:0.01'],
            'paypalCreateForm.invoice_date' => ['required', 'date'],
            'paypalCreateForm.invoice_date_due' => ['required', 'date', 'after_or_equal:paypalCreateForm.invoice_date'],
            'paypalCreateForm.currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
            'paypalCreateForm.reference' => ['nullable', 'string', 'max:255'],
            'paypalCreateForm.contract_id' => ['nullable', 'integer', 'exists:dinx_contracts,id'],
        ])['paypalCreateForm'];

        $user = Auth::user();

        if (! $user?->default_company_id) {
            Notification::make()
                ->title('Missing default company')
                ->body('Set a default company on your user before creating invoices.')
                ->danger()
                ->send();

            return;
        }

        $journalId = Journal::query()
            ->where('company_id', $user->default_company_id)
            ->where('type', JournalType::SALE->value)
            ->value('id');

        if (! $journalId) {
            Notification::make()
                ->title('Sales journal not found')
                ->body('Create a Sales journal first, then retry invoice creation.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $contract = null;
        if (! empty($data['contract_id'])) {
            $contract = DinxContract::query()->find((int) $data['contract_id']);
        }

        if ($contract && (int) $contract->partner_id !== (int) $data['partner_id']) {
            Notification::make()
                ->title('Contract mismatch')
                ->body('The selected contract belongs to a different client.')
                ->danger()
                ->send();

            return;
        }

        if (! app(PayPalService::class)->isConfigured()) {
            Notification::make()
                ->title('PayPal is not configured')
                ->body('Add PayPal credentials in DINX ERP settings before creating PayPal invoices.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        try {
            $invoice = DB::transaction(function () use ($data, $journalId, $user, $contract) {
                $amount = round((float) $data['amount'], 2);
                $invoiceDate = Carbon::parse((string) $data['invoice_date'])->toDateString();
                $dueDate = Carbon::parse((string) $data['invoice_date_due'])->toDateString();
                $currencyId = $data['currency_id']
                    ?: ($user->defaultCompany?->currency_id ?: Currency::query()->where('active', true)->value('id'));

                $invoice = Invoice::query()->create([
                    'journal_id' => $journalId,
                    'company_id' => (int) $user->default_company_id,
                    'partner_id' => (int) $data['partner_id'],
                    'currency_id' => $currencyId ? (int) $currencyId : null,
                    'move_type' => MoveType::OUT_INVOICE->value,
                    'state' => MoveState::DRAFT->value,
                    'payment_state' => PaymentState::NOT_PAID->value,
                    'date' => $invoiceDate,
                    'invoice_date' => $invoiceDate,
                    'invoice_date_due' => $dueDate,
                    'reference' => trim((string) ($data['reference'] ?? '')) !== '' ? trim((string) $data['reference']) : null,
                    'invoice_origin' => 'DINX ERP Invoices Hub',
                    'amount_total' => $amount,
                    'amount_residual' => $amount,
                    'amount_untaxed' => $amount,
                    'amount_tax' => 0,
                    'amount_total_signed' => $amount,
                    'amount_total_in_currency_signed' => $amount,
                    'amount_residual_signed' => $amount,
                    'amount_untaxed_signed' => $amount,
                    'amount_untaxed_in_currency_signed' => $amount,
                    'amount_tax_signed' => 0,
                    'quick_edit_total_amount' => $amount,
                ]);

                if ($contract) {
                    DinxContractInvoiceLink::query()->updateOrCreate(
                        [
                            'contract_id' => (int) $contract->id,
                            'invoice_id' => (int) $invoice->id,
                        ],
                        []
                    );
                }

                return $invoice;
            });

            $approvalUrl = app(PayPalService::class)->getOrCreateApprovalUrl(
                $invoice,
                $contract?->id ? (int) $contract->id : null
            );

            $this->latestPayPalApprovalUrl = $approvalUrl;
            $this->latestPayPalInvoiceId = (int) $invoice->id;
            $this->showCreatePayPalModal = false;

            Notification::make()
                ->title('PayPal invoice created')
                ->body('Invoice '.$invoice->name.' is ready. Open the PayPal checkout link below.')
                ->success()
                ->persistent()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Failed to create PayPal invoice')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function getClientOptionsProperty(): array
    {
        return Partner::query()
            ->select(['id', 'name'])
            ->whereNotNull('name')
            ->orderBy('name')
            ->limit(500)
            ->pluck('name', 'id')
            ->all();
    }

    public function getContractOptionsProperty(): array
    {
        return DinxContract::query()
            ->select(['id', 'title'])
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('title', 'id')
            ->all();
    }

    public function getCurrencyOptionsProperty(): array
    {
        return Currency::query()
            ->select(['id', 'name'])
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function resetPayPalCreateForm(): void
    {
        $defaultCurrencyId = Auth::user()?->defaultCompany?->currency_id
            ?: Currency::query()->where('active', true)->value('id');

        $this->paypalCreateForm = [
            'partner_id' => null,
            'contract_id' => null,
            'amount' => null,
            'invoice_date' => now()->toDateString(),
            'invoice_date_due' => now()->addDays(14)->toDateString(),
            'currency_id' => $defaultCurrencyId ? (int) $defaultCurrencyId : null,
            'reference' => null,
        ];
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
