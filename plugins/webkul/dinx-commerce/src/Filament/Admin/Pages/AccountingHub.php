<?php

namespace Webkul\DinxCommerce\Filament\Admin\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;
use Webkul\Account\Models\Account;
use Webkul\Account\Models\MoveLine;
use Webkul\DinxCommerce\Models\DinxBankReconciliationMatch;
use Webkul\DinxCommerce\Models\DinxBankStatementImport;
use Webkul\DinxCommerce\Models\DinxBankStatementLine;
use Webkul\DinxCommerce\Models\DinxExpenseCategoryRule;
use Webkul\DinxCommerce\Models\DinxTaxMapperRule;

class AccountingHub extends Page
{
    use HasPageShield;
    use WithFileUploads;

    protected string $view = 'dinx-commerce::filament.admin.pages.accounting-hub';

    protected static ?string $slug = 'erp/accounting';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'DINX ERP';

    protected static ?int $navigationSort = 40;

    public ?int $selectedAccountId = null;

    public string $registerSearch = '';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public mixed $statementUpload = null;

    public ?int $selectedImportId = null;

    protected static function getPagePermission(): ?string
    {
        return 'page_dinx_workspace_accounting';
    }

    public static function getNavigationLabel(): string
    {
        return 'Accounting';
    }

    public function mount(): void
    {
        if (! Schema::hasTable('accounts_accounts')) {
            return;
        }

        $this->selectedAccountId = Account::query()->orderByRaw('COALESCE(code, name) asc')->value('id');

        if (Schema::hasTable('dinx_bank_statement_imports')) {
            $this->selectedImportId = DinxBankStatementImport::query()->latest('id')->value('id');
        }
    }

    public function selectAccount(int $accountId): void
    {
        $this->selectedAccountId = $accountId;
    }

    public function openJournalEntryForm(): RedirectResponse
    {
        if (class_exists(\Webkul\Account\Filament\Resources\JournalResource::class)) {
            return redirect()->to(\Webkul\Account\Filament\Resources\JournalResource::getUrl('create'));
        }

        return redirect()->to('/admin/erp/accounting');
    }

    public function openCoaSettings(): RedirectResponse
    {
        if (class_exists(\Webkul\Account\Filament\Resources\AccountResource::class)) {
            return redirect()->to(\Webkul\Account\Filament\Resources\AccountResource::getUrl('index'));
        }

        return redirect()->to('/admin/erp/accounting');
    }

    public function openTaxSettings(): RedirectResponse
    {
        if (class_exists(\Webkul\Account\Filament\Resources\TaxResource::class)) {
            return redirect()->to(\Webkul\Account\Filament\Resources\TaxResource::getUrl('index'));
        }

        if (class_exists(\Webkul\Invoice\Filament\Clusters\Configuration\Resources\TaxResource::class)) {
            return redirect()->to(\Webkul\Invoice\Filament\Clusters\Configuration\Resources\TaxResource::getUrl('index'));
        }

        return redirect()->to('/admin/erp/accounting');
    }

    public function importBankCsv(): void
    {
        if (
            ! Schema::hasTable('dinx_bank_statement_imports')
            || ! Schema::hasTable('dinx_bank_statement_lines')
            || ! Schema::hasTable('dinx_bank_reconciliation_matches')
        ) {
            Notification::make()
                ->title('Run DINX Commerce migrations first')
                ->danger()
                ->send();

            return;
        }

        if (! $this->statementUpload) {
            Notification::make()->title('Select a CSV file first')->warning()->send();

            return;
        }

        try {
            $rows = $this->parseCsv((string) $this->statementUpload->getRealPath());

            if ($rows === []) {
                Notification::make()->title('No rows detected in uploaded CSV')->warning()->send();

                return;
            }

            DB::transaction(function () use ($rows): void {
                $import = DinxBankStatementImport::query()->create([
                    'file_name' => (string) $this->statementUpload->getClientOriginalName(),
                    'uploaded_by' => Auth::id(),
                    'status' => 'processing',
                    'total_lines' => 0,
                    'matched_lines' => 0,
                ]);

                $matchedCount = 0;

                foreach ($rows as $row) {
                    $suggestion = $this->suggestAccountAndReason((string) ($row['description'] ?? ''));

                    $line = DinxBankStatementLine::query()->create([
                        'import_id' => $import->id,
                        'line_number' => (int) $row['line_number'],
                        'transaction_date' => $row['transaction_date'],
                        'description' => $row['description'],
                        'reference' => $row['reference'],
                        'amount' => (float) $row['amount'],
                        'balance' => $row['balance'],
                        'currency' => (string) ($row['currency'] ?: 'USD'),
                        'suggested_account_id' => $suggestion['account_id'],
                        'status' => 'unmatched',
                        'metadata' => [
                            'rule_reason' => $suggestion['reason'],
                        ],
                    ]);

                    $match = $this->buildMatchSuggestion($line);

                    if ($match) {
                        $matchedCount++;
                    }
                }

                $import->forceFill([
                    'status' => 'review',
                    'total_lines' => count($rows),
                    'matched_lines' => $matchedCount,
                ])->save();

                $this->selectedImportId = $import->id;
            });

            $this->statementUpload = null;

            Notification::make()
                ->title('Bank CSV imported')
                ->body('Statement lines were imported and reconciliation suggestions were generated.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Import failed')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function setSelectedImport(int $importId): void
    {
        $this->selectedImportId = $importId;
    }

    public function confirmMatch(int $matchId): void
    {
        $match = DinxBankReconciliationMatch::query()->with('statementLine')->find($matchId);

        if (! $match || ! $match->statementLine) {
            Notification::make()->title('Match not found')->danger()->send();

            return;
        }

        DB::transaction(function () use ($match): void {
            DinxBankReconciliationMatch::query()
                ->where('statement_line_id', $match->statement_line_id)
                ->where('id', '!=', $match->id)
                ->update(['status' => 'rejected']);

            $match->forceFill([
                'status' => 'confirmed',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ])->save();

            $line = $match->statementLine;

            $line->forceFill([
                'matched_move_line_id' => $match->move_line_id,
                'status' => 'reconciled',
            ])->save();

            DinxBankStatementImport::query()
                ->where('id', $line->import_id)
                ->update([
                    'matched_lines' => DinxBankStatementLine::query()
                        ->where('import_id', $line->import_id)
                        ->where('status', 'reconciled')
                        ->count(),
                ]);
        });

        Notification::make()->title('Reconciliation match confirmed')->success()->send();
    }

    public function rejectMatch(int $matchId): void
    {
        $match = DinxBankReconciliationMatch::query()->with('statementLine')->find($matchId);

        if (! $match || ! $match->statementLine) {
            Notification::make()->title('Match not found')->danger()->send();

            return;
        }

        DB::transaction(function () use ($match): void {
            $match->forceFill([
                'status' => 'rejected',
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ])->save();

            $line = $match->statementLine;

            $line->forceFill([
                'status' => 'unmatched',
            ])->save();
        });

        Notification::make()->title('Suggestion rejected')->success()->send();
    }

    public function getAccountSectionsProperty(): array
    {
        if (! Schema::hasTable('accounts_accounts') || ! Schema::hasTable('accounts_account_move_lines')) {
            return [];
        }

        $balanceByAccount = MoveLine::query()
            ->whereNotNull('account_id')
            ->groupBy('account_id')
            ->selectRaw('account_id, COALESCE(SUM(debit), 0) as debit_total, COALESCE(SUM(credit), 0) as credit_total')
            ->get()
            ->mapWithKeys(fn (MoveLine $line) => [
                (int) $line->account_id => (float) $line->debit_total - (float) $line->credit_total,
            ]);

        $accounts = Account::query()
            ->orderBy('account_type')
            ->orderBy('code')
            ->orderBy('name')
            ->get(['id', 'account_type', 'code', 'name']);

        $grouped = $accounts->groupBy(function (Account $account): string {
            $accountType = $account->account_type;

            if ($accountType instanceof \BackedEnum) {
                $accountType = $accountType->value;
            } elseif ($accountType instanceof \UnitEnum) {
                $accountType = $accountType->name;
            }

            return $this->resolveAccountSection((string) $accountType);
        });

        $sectionOrder = ['Assets', 'Liabilities', 'Equity', 'Income', 'Expenses', 'Other'];

        $result = [];

        foreach ($sectionOrder as $section) {
            $rows = $grouped->get($section, collect())->map(function (Account $account) use ($balanceByAccount): array {
                return [
                    'id' => (int) $account->id,
                    'code' => (string) ($account->code ?: ''),
                    'name' => (string) $account->name,
                    'balance' => (float) ($balanceByAccount[(int) $account->id] ?? 0),
                    'is_selected' => $this->selectedAccountId === (int) $account->id,
                ];
            })->values()->all();

            if ($rows !== []) {
                $result[] = [
                    'label' => $section,
                    'accounts' => $rows,
                ];
            }
        }

        return $result;
    }

    public function getSelectedAccountProperty(): ?Account
    {
        if (! $this->selectedAccountId) {
            return null;
        }

        return Account::query()->find($this->selectedAccountId);
    }

    public function getRegisterRowsProperty(): array
    {
        if (! Schema::hasTable('accounts_account_move_lines')) {
            return [];
        }

        if (! $this->selectedAccountId) {
            return [];
        }

        $query = MoveLine::query()
            ->with(['move:id,name', 'partner:id,name'])
            ->where('account_id', $this->selectedAccountId);

        if (trim($this->registerSearch) !== '') {
            $term = '%'.trim($this->registerSearch).'%';
            $query->where(function ($builder) use ($term): void {
                $builder
                    ->where('reference', 'like', $term)
                    ->orWhere('name', 'like', $term)
                    ->orWhere('move_name', 'like', $term)
                    ->orWhereHas('partner', fn ($q) => $q->where('name', 'like', $term));
            });
        }

        if ($this->dateFrom) {
            $query->whereDate('date', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('date', '<=', $this->dateTo);
        }

        return $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(function (MoveLine $line): array {
                $status = '';

                if ($line->reconciled) {
                    $status = 'R';
                } elseif ((string) ($line->parent_state?->value ?? $line->parent_state) === 'posted') {
                    $status = 'C';
                }

                return [
                    'id' => (int) $line->id,
                    'date' => $line->date,
                    'type' => Str::headline((string) ($line->display_type?->value ?? $line->display_type ?: 'entry')),
                    'payee' => (string) ($line->partner?->name ?: 'N/A'),
                    'memo' => (string) ($line->reference ?: $line->name ?: 'N/A'),
                    'debit' => (float) ($line->debit ?? 0),
                    'credit' => (float) ($line->credit ?? 0),
                    'balance' => (float) ($line->balance ?? 0),
                    'status' => $status,
                ];
            })
            ->all();
    }

    public function getImportHistoryProperty(): array
    {
        if (! Schema::hasTable('dinx_bank_statement_imports')) {
            return [];
        }

        return DinxBankStatementImport::query()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (DinxBankStatementImport $import): array => [
                'id' => (int) $import->id,
                'file_name' => (string) $import->file_name,
                'status' => (string) $import->status,
                'total_lines' => (int) $import->total_lines,
                'matched_lines' => (int) $import->matched_lines,
                'created_at' => $import->created_at,
                'is_selected' => $this->selectedImportId === (int) $import->id,
            ])
            ->all();
    }

    public function getActiveImportProperty(): ?DinxBankStatementImport
    {
        if (! Schema::hasTable('dinx_bank_statement_imports')) {
            return null;
        }

        if ($this->selectedImportId) {
            return DinxBankStatementImport::query()->find($this->selectedImportId);
        }

        return DinxBankStatementImport::query()->latest('id')->first();
    }

    public function getReconciliationQueueProperty(): array
    {
        if (! Schema::hasTable('dinx_bank_statement_lines') || ! Schema::hasTable('dinx_bank_reconciliation_matches')) {
            return [];
        }

        $import = $this->activeImport;

        if (! $import) {
            return [];
        }

        return DinxBankStatementLine::query()
            ->with(['suggestedAccount:id,code,name', 'matches.moveLine.partner:id,name'])
            ->where('import_id', $import->id)
            ->whereIn('status', ['unmatched', 'suggested'])
            ->orderBy('line_number')
            ->limit(120)
            ->get()
            ->map(function (DinxBankStatementLine $line): array {
                $bestMatch = $line->matches
                    ->sortByDesc('score')
                    ->first();

                return [
                    'id' => (int) $line->id,
                    'line_number' => (int) $line->line_number,
                    'transaction_date' => $line->transaction_date,
                    'description' => (string) ($line->description ?: 'N/A'),
                    'amount' => (float) $line->amount,
                    'status' => (string) $line->status,
                    'suggested_account' => $line->suggestedAccount
                        ? trim(($line->suggestedAccount->code ? $line->suggestedAccount->code.' ' : '').$line->suggestedAccount->name)
                        : null,
                    'match' => $bestMatch ? [
                        'id' => (int) $bestMatch->id,
                        'score' => (float) $bestMatch->score,
                        'move_line_id' => (int) $bestMatch->move_line_id,
                        'partner' => (string) ($bestMatch->moveLine?->partner?->name ?: 'N/A'),
                        'amount' => (float) ($bestMatch->moveLine?->balance ?? 0),
                        'date' => $bestMatch->moveLine?->date,
                        'memo' => (string) ($bestMatch->moveLine?->reference ?: $bestMatch->moveLine?->name ?: 'N/A'),
                    ] : null,
                ];
            })
            ->all();
    }

    public function getTaxMapperRulesProperty(): array
    {
        if (! Schema::hasTable('dinx_tax_mapper_rules')) {
            return [];
        }

        return DinxTaxMapperRule::query()
            ->where('is_active', true)
            ->with('tax:id,name,amount')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (DinxTaxMapperRule $rule): array => [
                'name' => (string) $rule->name,
                'pattern' => (string) $rule->match_pattern,
                'tax' => $rule->tax?->name,
                'rate_override' => $rule->rate_override,
            ])
            ->all();
    }

    protected function parseCsv(string $path): array
    {
        $file = new \SplFileObject($path);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $rows = [];
        $line = 0;
        $headerMap = null;

        foreach ($file as $csvRow) {
            $line++;

            if (! is_array($csvRow)) {
                continue;
            }

            $csvRow = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $csvRow);

            if ($csvRow === [null] || implode('', array_filter($csvRow, fn ($v) => $v !== null)) === '') {
                continue;
            }

            if ($headerMap === null) {
                $possibleHeader = array_map(fn ($value) => Str::lower((string) $value), $csvRow);
                if (collect($possibleHeader)->contains(fn ($value) => in_array($value, ['date', 'transaction_date', 'description', 'amount'], true))) {
                    $headerMap = $possibleHeader;
                    continue;
                }
            }

            $dateRaw = $this->valueByHeader($csvRow, $headerMap, ['date', 'transaction_date', 'posted_date']) ?? ($csvRow[0] ?? null);
            $description = $this->valueByHeader($csvRow, $headerMap, ['description', 'memo', 'name']) ?? ($csvRow[1] ?? null);
            $amountRaw = $this->valueByHeader($csvRow, $headerMap, ['amount', 'debit', 'credit']) ?? ($csvRow[2] ?? null);
            $balanceRaw = $this->valueByHeader($csvRow, $headerMap, ['balance', 'running_balance']) ?? ($csvRow[3] ?? null);
            $reference = $this->valueByHeader($csvRow, $headerMap, ['reference', 'ref', 'id']) ?? ($csvRow[4] ?? null);
            $currency = $this->valueByHeader($csvRow, $headerMap, ['currency']) ?? 'USD';

            $amount = $this->parseAmount((string) $amountRaw);
            $balance = $balanceRaw !== null && $balanceRaw !== '' ? $this->parseAmount((string) $balanceRaw) : null;
            $date = $this->parseDate((string) $dateRaw);

            if ($amount === null && $date === null && $description === null) {
                continue;
            }

            $rows[] = [
                'line_number' => count($rows) + 1,
                'transaction_date' => $date,
                'description' => $description,
                'reference' => $reference,
                'amount' => (float) ($amount ?? 0),
                'balance' => $balance,
                'currency' => strtoupper((string) $currency),
            ];
        }

        return $rows;
    }

    protected function valueByHeader(array $row, ?array $headerMap, array $matches): ?string
    {
        if (! $headerMap) {
            return null;
        }

        foreach ($headerMap as $index => $header) {
            foreach ($matches as $match) {
                if (Str::contains((string) $header, Str::lower($match))) {
                    $value = $row[$index] ?? null;

                    return is_scalar($value) ? (string) $value : null;
                }
            }
        }

        return null;
    }

    protected function parseAmount(string $value): ?float
    {
        $normalized = preg_replace('/[^\d\-\.,]/', '', $value);

        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (Str::contains($normalized, ',') && Str::contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (Str::contains($normalized, ',') && ! Str::contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'm-d-Y', 'd-m-Y'];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $value);

            if ($parsed instanceof \DateTime) {
                return $parsed->format('Y-m-d');
            }
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
    }

    protected function suggestAccountAndReason(string $description): array
    {
        $normalized = Str::lower(trim($description));

        if ($normalized === '') {
            return [
                'account_id' => null,
                'reason' => null,
            ];
        }

        $rule = DinxExpenseCategoryRule::query()
            ->where('is_active', true)
            ->get()
            ->first(function (DinxExpenseCategoryRule $rule) use ($normalized): bool {
                return Str::contains($normalized, Str::lower((string) $rule->match_pattern));
            });

        if ($rule) {
            $rule->forceFill(['last_used_at' => now()])->save();

            return [
                'account_id' => $rule->account_id,
                'reason' => 'Matched expense rule: '.$rule->name,
            ];
        }

        return [
            'account_id' => null,
            'reason' => null,
        ];
    }

    protected function buildMatchSuggestion(DinxBankStatementLine $line): ?DinxBankReconciliationMatch
    {
        $query = MoveLine::query()
            ->whereNotNull('account_id')
            ->where(function ($builder): void {
                $builder->where('reconciled', false)->orWhereNull('reconciled');
            })
            ->whereNotNull('balance');

        if ($line->transaction_date) {
            $query->whereDate('date', '>=', Carbon::parse($line->transaction_date)->subDays(45)->toDateString())
                ->whereDate('date', '<=', Carbon::parse($line->transaction_date)->addDays(45)->toDateString());
        }

        $candidates = $query
            ->orderByDesc('date')
            ->limit(300)
            ->get(['id', 'date', 'balance', 'reference', 'name', 'partner_id']);

        $targetAmount = abs((float) $line->amount);
        $targetDate = $line->transaction_date;
        $description = Str::lower((string) $line->description);

        $bestCandidate = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $candidateAmount = abs((float) ($candidate->balance ?? 0));
            $amountDelta = abs($candidateAmount - $targetAmount);
            $amountScore = max(0, 65 - ($amountDelta * 120));

            $dayDelta = 31;
            if ($targetDate && $candidate->date) {
                $dayDelta = abs($candidate->date->diffInDays(Carbon::parse($targetDate), false));
                $dayDelta = abs($dayDelta);
            }

            $dateScore = max(0, 30 - ($dayDelta * 1.2));

            $memoText = Str::lower((string) ($candidate->reference ?: $candidate->name ?: ''));
            $textScore = ($description !== '' && $memoText !== '' && Str::contains($memoText, $description)) ? 15 : 0;

            $score = min(100, $amountScore + $dateScore + $textScore);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCandidate = $candidate;
            }
        }

        if (! $bestCandidate || $bestScore < 55) {
            return null;
        }

        $line->forceFill([
            'status' => 'suggested',
        ])->save();

        return DinxBankReconciliationMatch::query()->updateOrCreate(
            [
                'statement_line_id' => $line->id,
                'move_line_id' => $bestCandidate->id,
            ],
            [
                'score' => round($bestScore, 2),
                'status' => 'suggested',
                'reason' => 'Auto match based on amount/date proximity.',
            ]
        );
    }

    protected function resolveAccountSection(string $accountType): string
    {
        return match (true) {
            Str::startsWith($accountType, 'asset_') => 'Assets',
            Str::startsWith($accountType, 'liability_') => 'Liabilities',
            Str::startsWith($accountType, 'equity') => 'Equity',
            Str::startsWith($accountType, 'income') => 'Income',
            Str::startsWith($accountType, 'expense') => 'Expenses',
            default => 'Other',
        };
    }
}
