<?php

namespace Webkul\DinxCommerce\Filament\Admin\Pages;

use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;
use Webkul\Account\Enums\AccountType;
use Webkul\DinxCommerce\Exports\ArrayReportExport;
use Webkul\DinxCommerce\Models\DinxProjectInvoiceLink;
use Webkul\DinxCommerce\Models\DinxReportFavorite;
use Webkul\DinxCommerce\Settings\DinxWorkspaceSettings;

class ReportsHub extends Page
{
    use HasPageShield;

    protected string $view = 'dinx-commerce::filament.admin.pages.reports-hub';

    protected static ?string $slug = 'erp/reports';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup = 'DINX ERP';

    protected static ?int $navigationSort = 50;

    public string $tab = 'favorites';

    public string $period = 'month';

    protected static function getPagePermission(): ?string
    {
        return 'page_dinx_workspace_reports';
    }

    public static function getNavigationLabel(): string
    {
        return 'Reports';
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['favorites', 'overview', 'sales', 'expenses'], true)) {
            $this->tab = $tab;
        }
    }

    public function setPeriod(string $period): void
    {
        if (in_array($period, ['month', 'quarter', 'ytd'], true)) {
            $this->period = $period;
        }
    }

    public function toggleFavorite(string $reportKey): void
    {
        $userId = Auth::id();

        if (! $userId) {
            Notification::make()->title('Please sign in again')->warning()->send();

            return;
        }

        $existing = DinxReportFavorite::query()
            ->where('user_id', $userId)
            ->where('report_key', $reportKey)
            ->first();

        if ($existing) {
            $existing->delete();

            Notification::make()->title('Removed from favorites')->success()->send();

            return;
        }

        $nextSort = ((int) DinxReportFavorite::query()->where('user_id', $userId)->max('sort')) + 1;

        DinxReportFavorite::query()->create([
            'user_id' => $userId,
            'report_key' => $reportKey,
            'label' => $this->reportCardIndex[$reportKey]['title'] ?? null,
            'sort' => $nextSort,
        ]);

        Notification::make()->title('Added to favorites')->success()->send();
    }

    public function runReport(string $reportKey): RedirectResponse
    {
        $url = match ($reportKey) {
            'profit_loss' => class_exists(\Webkul\Accounting\Filament\Clusters\Reporting\Pages\ProfitLoss::class)
                ? \Webkul\Accounting\Filament\Clusters\Reporting\Pages\ProfitLoss::getUrl()
                : '/admin/erp/reports',
            'ar_aging' => class_exists(\Webkul\Accounting\Filament\Clusters\Reporting\Pages\AgedReceivable::class)
                ? \Webkul\Accounting\Filament\Clusters\Reporting\Pages\AgedReceivable::getUrl()
                : '/admin/erp/reports',
            'project_profitability' => '/admin/erp/projects',
            default => '/admin/erp/reports',
        };

        return redirect()->to($url);
    }

    public function exportPdf(string $reportKey)
    {
        $payload = $this->buildExportPayload($reportKey);

        $htmlRows = collect($payload['rows'])
            ->map(function (array $row): string {
                $cells = collect($row)->map(fn ($value) => '<td style="border:1px solid #cbd5e1;padding:8px;">'.e((string) $value).'</td>')->implode('');

                return '<tr>'.$cells.'</tr>';
            })
            ->implode('');

        $headings = collect($payload['headings'])
            ->map(fn ($heading) => '<th style="border:1px solid #cbd5e1;padding:8px;background:#eef2ff;text-align:left;">'.e((string) $heading).'</th>')
            ->implode('');

        $html = '<html><body style="font-family:Arial,sans-serif;">'
            .'<h2 style="margin-bottom:4px;">'.e($payload['title']).'</h2>'
            .'<p style="margin-top:0;color:#475569;">Period: '.e($payload['period_label']).'</p>'
            .'<table style="border-collapse:collapse;width:100%;"><thead><tr>'.$headings.'</tr></thead><tbody>'.$htmlRows.'</tbody></table>'
            .'</body></html>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, $reportKey.'-report-'.now()->format('Ymd_His').'.pdf');
    }

    public function exportExcel(string $reportKey): BinaryFileResponse
    {
        $payload = $this->buildExportPayload($reportKey);

        return Excel::download(
            new ArrayReportExport($payload['headings'], $payload['rows']),
            $reportKey.'-report-'.now()->format('Ymd_His').'.xlsx'
        );
    }

    public function getFavoriteKeysProperty(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        return DinxReportFavorite::query()
            ->where('user_id', $userId)
            ->orderBy('sort')
            ->pluck('report_key')
            ->all();
    }

    public function getFavoriteCardsProperty(): array
    {
        $keys = $this->favoriteKeys;

        if ($keys === []) {
            $keys = ['profit_loss', 'ar_aging', 'project_profitability'];
        }

        $cards = $this->reportCards;

        return collect($keys)
            ->map(fn (string $key) => collect($cards)->firstWhere('key', $key))
            ->filter()
            ->values()
            ->all();
    }

    public function getReportCardsProperty(): array
    {
        $profitLoss = $this->profitLossSummary;
        $aging = $this->arAgingSummary;
        $project = $this->projectProfitabilitySummary;

        return [
            [
                'key' => 'profit_loss',
                'title' => 'Profit & Loss',
                'subtitle' => $this->periodLabel,
                'value' => $profitLoss['net'],
                'tone' => $profitLoss['net'] >= 0 ? 'positive' : 'negative',
                'preview' => [
                    'Revenue '.number_format($profitLoss['revenue'], 2),
                    'Expenses '.number_format($profitLoss['expenses'], 2),
                    'Net '.number_format($profitLoss['net'], 2),
                ],
                'kpi' => $profitLoss,
                'section' => 'overview',
            ],
            [
                'key' => 'ar_aging',
                'title' => 'A/R Aging Summary',
                'subtitle' => 'Outstanding receivables by due bucket',
                'value' => $aging['total'],
                'tone' => $aging['total'] > 0 ? 'warning' : 'neutral',
                'preview' => [
                    '1-30 days: '.number_format($aging['bucket_1_30'], 2),
                    '31-60 days: '.number_format($aging['bucket_31_60'], 2),
                    '60+ days: '.number_format($aging['bucket_60_plus'], 2),
                ],
                'kpi' => $aging,
                'section' => 'sales',
            ],
            [
                'key' => 'project_profitability',
                'title' => 'Project Profitability',
                'subtitle' => 'Revenue minus labor cost by project',
                'value' => $project['margin_total'],
                'tone' => $project['margin_total'] >= 0 ? 'positive' : 'negative',
                'preview' => collect($project['top_projects'])
                    ->take(3)
                    ->map(fn (array $row) => $row['name'].': '.number_format($row['margin'], 2))
                    ->values()
                    ->all(),
                'kpi' => $project,
                'section' => 'overview',
            ],
        ];
    }

    public function getOverviewCardsProperty(): array
    {
        return collect($this->reportCards)
            ->filter(fn (array $card) => in_array($card['section'], ['overview', 'sales'], true))
            ->values()
            ->all();
    }

    public function getSalesCardsProperty(): array
    {
        return collect($this->reportCards)
            ->filter(fn (array $card) => $card['section'] === 'sales')
            ->values()
            ->all();
    }

    public function getExpenseCardsProperty(): array
    {
        return collect($this->reportCards)
            ->filter(fn (array $card) => in_array($card['key'], ['profit_loss', 'project_profitability'], true))
            ->values()
            ->all();
    }

    public function getPeriodLabelProperty(): string
    {
        return match ($this->period) {
            'month' => 'This Month',
            'quarter' => 'This Quarter',
            'ytd' => 'Year to Date',
            default => 'This Month',
        };
    }

    public function getReportCardIndexProperty(): array
    {
        return collect($this->reportCards)
            ->mapWithKeys(fn (array $card): array => [$card['key'] => $card])
            ->all();
    }

    public function getProfitLossSummaryProperty(): array
    {
        if (! Schema::hasTable('accounts_account_move_lines') || ! Schema::hasTable('accounts_account_moves') || ! Schema::hasTable('accounts_accounts')) {
            return [
                'revenue' => 0.0,
                'expenses' => 0.0,
                'net' => 0.0,
                'start' => now()->toDateString(),
                'end' => now()->toDateString(),
            ];
        }

        [$start, $end] = $this->resolveDateRange();

        $row = DB::table('accounts_account_move_lines as line')
            ->join('accounts_account_moves as move', 'move.id', '=', 'line.move_id')
            ->join('accounts_accounts as account', 'account.id', '=', 'line.account_id')
            ->where('move.state', 'posted')
            ->whereBetween('move.date', [$start, $end])
            ->selectRaw('COALESCE(SUM(CASE WHEN account.account_type IN (?, ?) THEN line.balance ELSE 0 END), 0) as income_balance', [AccountType::INCOME->value, AccountType::INCOME_OTHER->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN account.account_type IN (?, ?, ?) THEN line.balance ELSE 0 END), 0) as expense_balance', [AccountType::EXPENSE->value, AccountType::EXPENSE_DIRECT_COST->value, AccountType::EXPENSE_DEPRECIATION->value])
            ->first();

        $revenue = abs((float) ($row->income_balance ?? 0));
        $expenses = abs((float) ($row->expense_balance ?? 0));

        return [
            'revenue' => round($revenue, 2),
            'expenses' => round($expenses, 2),
            'net' => round($revenue - $expenses, 2),
            'start' => $start,
            'end' => $end,
        ];
    }

    public function getArAgingSummaryProperty(): array
    {
        if (! Schema::hasTable('accounts_account_moves')) {
            return [
                'bucket_1_30' => 0.0,
                'bucket_31_60' => 0.0,
                'bucket_60_plus' => 0.0,
                'total' => 0.0,
            ];
        }

        $today = now()->startOfDay();

        $invoices = DB::table('accounts_account_moves')
            ->where('state', 'posted')
            ->whereIn('move_type', ['out_invoice', 'out_receipt'])
            ->whereIn('payment_state', ['not_paid', 'partial', 'in_payment'])
            ->where('amount_residual', '>', 0)
            ->get(['invoice_date_due', 'amount_residual']);

        $bucket1 = 0.0;
        $bucket2 = 0.0;
        $bucket3 = 0.0;

        foreach ($invoices as $invoice) {
            $amount = (float) $invoice->amount_residual;
            $days = $invoice->invoice_date_due
                ? $today->diffInDays(Carbon::parse((string) $invoice->invoice_date_due), false)
                : 0;

            if ($days <= 30) {
                $bucket1 += $amount;
            } elseif ($days <= 60) {
                $bucket2 += $amount;
            } else {
                $bucket3 += $amount;
            }
        }

        return [
            'bucket_1_30' => round($bucket1, 2),
            'bucket_31_60' => round($bucket2, 2),
            'bucket_60_plus' => round($bucket3, 2),
            'total' => round($bucket1 + $bucket2 + $bucket3, 2),
        ];
    }

    public function getProjectProfitabilitySummaryProperty(): array
    {
        if (
            ! Schema::hasTable('projects_projects')
            || ! Schema::hasTable('analytic_records')
            || ! Schema::hasTable('dinx_project_invoice_links')
            || ! Schema::hasTable('accounts_account_moves')
        ) {
            return [
                'margin_total' => 0.0,
                'top_projects' => [],
            ];
        }

        $costRate = (float) app(DinxWorkspaceSettings::class)->project_default_cost_hourly_rate;

        $rows = DB::table('projects_projects as p')
            ->leftJoin('partners_partners as partner', 'partner.id', '=', 'p.partner_id')
            ->whereNull('p.deleted_at')
            ->select('p.id', 'p.name', 'p.partner_id', 'partner.name as partner_name')
            ->limit(120)
            ->get();

        $top = [];
        $marginTotal = 0.0;

        foreach ($rows as $row) {
            $revenue = (float) DinxProjectInvoiceLink::query()
                ->where('project_id', $row->id)
                ->join('accounts_account_moves as move', 'move.id', '=', 'dinx_project_invoice_links.invoice_id')
                ->where('move.state', 'posted')
                ->sum('move.amount_total');

            $hours = (float) DB::table('analytic_records')
                ->where('project_id', $row->id)
                ->sum('unit_amount');

            $cost = $hours * $costRate;
            $margin = $revenue - $cost;

            $marginTotal += $margin;

            $top[] = [
                'project_id' => (int) $row->id,
                'name' => (string) $row->name,
                'client' => (string) ($row->partner_name ?: 'Unassigned'),
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'margin' => round($margin, 2),
                'margin_pct' => $revenue > 0 ? round(($margin / $revenue) * 100, 2) : 0,
            ];
        }

        $top = collect($top)
            ->sortByDesc('margin')
            ->values()
            ->take(8)
            ->all();

        return [
            'margin_total' => round($marginTotal, 2),
            'top_projects' => $top,
        ];
    }

    protected function resolveDateRange(): array
    {
        return match ($this->period) {
            'quarter' => [now()->startOfQuarter()->toDateString(), now()->endOfQuarter()->toDateString()],
            'ytd' => [now()->startOfYear()->toDateString(), now()->toDateString()],
            default => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
        };
    }

    protected function buildExportPayload(string $reportKey): array
    {
        return match ($reportKey) {
            'profit_loss' => [
                'title' => 'Profit & Loss',
                'period_label' => $this->periodLabel,
                'headings' => ['Metric', 'Value'],
                'rows' => [
                    ['Revenue', number_format($this->profitLossSummary['revenue'], 2)],
                    ['Expenses', number_format($this->profitLossSummary['expenses'], 2)],
                    ['Net', number_format($this->profitLossSummary['net'], 2)],
                ],
            ],
            'ar_aging' => [
                'title' => 'A/R Aging Summary',
                'period_label' => 'As of '.now()->format('M d, Y'),
                'headings' => ['Bucket', 'Amount'],
                'rows' => [
                    ['1-30 days', number_format($this->arAgingSummary['bucket_1_30'], 2)],
                    ['31-60 days', number_format($this->arAgingSummary['bucket_31_60'], 2)],
                    ['60+ days', number_format($this->arAgingSummary['bucket_60_plus'], 2)],
                    ['Total', number_format($this->arAgingSummary['total'], 2)],
                ],
            ],
            'project_profitability' => [
                'title' => 'Project Profitability',
                'period_label' => $this->periodLabel,
                'headings' => ['Project', 'Client', 'Revenue', 'Cost', 'Margin', 'Margin %'],
                'rows' => collect($this->projectProfitabilitySummary['top_projects'])
                    ->map(fn (array $row) => [
                        $row['name'],
                        $row['client'],
                        number_format($row['revenue'], 2),
                        number_format($row['cost'], 2),
                        number_format($row['margin'], 2),
                        number_format($row['margin_pct'], 2).'%',
                    ])
                    ->values()
                    ->all(),
            ],
            default => [
                'title' => 'Report',
                'period_label' => $this->periodLabel,
                'headings' => ['Field', 'Value'],
                'rows' => [['No data', '']],
            ],
        };
    }
}
