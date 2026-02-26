<?php

namespace Webkul\DinxCommerce\Filament\Admin\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use UnitEnum;
use Webkul\Account\Models\Invoice;
use Webkul\DinxCommerce\Models\DinxContract;
use Webkul\DinxCommerce\Models\DinxProjectInvoiceLink;
use Webkul\DinxCommerce\Settings\DinxWorkspaceSettings;
use Webkul\DinxErpSync\Models\DinxSyncMapping;
use Webkul\Project\Models\Project;

class ProjectsHub extends Page
{
    use HasPageShield;

    protected string $view = 'dinx-commerce::filament.admin.pages.projects-hub';

    protected static ?string $slug = 'erp/projects';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'DINX ERP';

    protected static ?int $navigationSort = 10;

    public string $viewMode = 'list';

    public ?int $stageId = null;

    public string $search = '';

    public float $billableRate = 150.0;

    public float $costRate = 75.0;

    public string $crmUrlTemplate = 'https://dinxsolutions.com/dashboard/apps/dinx-crm/hit-list?lead={lead_id}';

    protected static function getPagePermission(): ?string
    {
        return 'page_dinx_workspace_projects';
    }

    public static function getNavigationLabel(): string
    {
        return 'Projects';
    }

    public function mount(): void
    {
        $settings = app(DinxWorkspaceSettings::class);

        $this->billableRate = (float) $settings->project_default_billable_hourly_rate;
        $this->costRate = (float) $settings->project_default_cost_hourly_rate;
        $this->crmUrlTemplate = (string) ($settings->crm_client_url_template ?: $this->crmUrlTemplate);
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['list', 'board'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function createInvoiceFromProject(int $projectId): RedirectResponse
    {
        $project = Project::query()->find($projectId);

        if (! $project) {
            Notification::make()->title('Project not found')->danger()->send();

            return redirect()->to('/admin/erp/projects');
        }

        if (class_exists(\Webkul\Accounting\Filament\Clusters\Customers\Resources\InvoiceResource::class)) {
            $url = \Webkul\Accounting\Filament\Clusters\Customers\Resources\InvoiceResource::getUrl('create', [
                'partner_id' => $project->partner_id,
                'project_id' => $project->id,
            ]);

            return redirect()->to($url);
        }

        if (class_exists(\Webkul\Account\Filament\Resources\InvoiceResource::class)) {
            $url = \Webkul\Account\Filament\Resources\InvoiceResource::getUrl('create', [
                'partner_id' => $project->partner_id,
                'project_id' => $project->id,
            ]);

            return redirect()->to($url);
        }

        return redirect()->to('/admin/erp/invoices');
    }

    public function viewContractFromProject(int $projectId): RedirectResponse
    {
        $project = Project::query()->find($projectId);

        if (! $project || ! $project->partner_id) {
            Notification::make()->title('No client linked to this project')->warning()->send();

            return redirect()->to('/admin/erp/projects');
        }

        $contract = DinxContract::query()
            ->where('partner_id', $project->partner_id)
            ->latest('id')
            ->first();

        if (! $contract) {
            Notification::make()->title('No contract found for this client')->warning()->send();

            return redirect()->to('/admin/erp/contracts');
        }

        return redirect()->to('/admin/dinx-contracts/'.$contract->id);
    }

    public function getScorecardsProperty(): array
    {
        if (
            ! Schema::hasTable('projects_projects')
            || ! Schema::hasTable('analytic_records')
            || ! Schema::hasTable('accounts_account_moves')
        ) {
            return [
                'active_projects' => 0,
                'billable_hours' => 0.0,
                'total_value' => 0.0,
            ];
        }

        $activeProjects = Project::query()->where('is_active', true)->count();

        $billableHours = (float) DB::table('analytic_records')
            ->whereNotNull('project_id')
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('unit_amount');

        $linkedValue = (float) DB::table('dinx_project_invoice_links as l')
            ->join('accounts_account_moves as m', 'm.id', '=', 'l.invoice_id')
            ->where('m.state', 'posted')
            ->sum('m.amount_total');

        return [
            'active_projects' => $activeProjects,
            'billable_hours' => round($billableHours, 2),
            'total_value' => round($linkedValue, 2),
        ];
    }

    public function getStageOptionsProperty(): array
    {
        if (! Schema::hasTable('projects_project_stages')) {
            return [];
        }

        return DB::table('projects_project_stages')
            ->whereNull('deleted_at')
            ->orderBy('sort')
            ->pluck('name', 'id')
            ->all();
    }

    public function getProjectRowsProperty(): array
    {
        if (
            ! Schema::hasTable('projects_projects')
            || ! Schema::hasTable('projects_project_stages')
            || ! Schema::hasTable('partners_partners')
        ) {
            return [];
        }

        $query = DB::table('projects_projects as p')
            ->leftJoin('projects_project_stages as s', 's.id', '=', 'p.stage_id')
            ->leftJoin('partners_partners as partner', 'partner.id', '=', 'p.partner_id')
            ->whereNull('p.deleted_at')
            ->select([
                'p.id',
                'p.name',
                'p.partner_id',
                'p.stage_id',
                'p.start_date',
                'p.end_date',
                'p.allocated_hours',
                'p.is_active',
                's.name as stage_name',
                'partner.name as partner_name',
            ]);

        if ($this->stageId) {
            $query->where('p.stage_id', $this->stageId);
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('p.name', 'like', $term)
                    ->orWhere('partner.name', 'like', $term)
                    ->orWhere('s.name', 'like', $term);
            });
        }

        $rows = $query
            ->orderByRaw('COALESCE(s.sort, 99999) asc')
            ->orderBy('p.id', 'desc')
            ->limit(250)
            ->get();

        $leadByPartner = [];

        if (Schema::hasTable('dinx_sync_mappings')) {
            $leadByPartner = DinxSyncMapping::query()
                ->whereIn('partner_id', $rows->pluck('partner_id')->filter()->unique()->values()->all())
                ->pluck('external_lead_id', 'partner_id')
                ->all();
        }

        return $rows->map(function ($row) use ($leadByPartner) {
            $hoursLogged = 0.0;

            if (Schema::hasTable('analytic_records')) {
                $hoursLogged = (float) DB::table('analytic_records')
                    ->where('project_id', $row->id)
                    ->sum('unit_amount');
            }

            $revenue = $this->resolveProjectRevenue((int) $row->id, (int) ($row->partner_id ?? 0), $row->start_date, $row->end_date);
            $cost = $hoursLogged * $this->costRate;

            $costToRevenue = $revenue > 0 ? min(100, round(($cost / $revenue) * 100, 2)) : 0;
            $margin = $revenue - $cost;
            $marginPct = $revenue > 0 ? round(($margin / $revenue) * 100, 2) : 0;

            $externalLeadId = $leadByPartner[(int) ($row->partner_id ?? 0)] ?? null;

            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'partner_id' => $row->partner_id ? (int) $row->partner_id : null,
                'partner_name' => (string) ($row->partner_name ?: 'Unassigned Client'),
                'partner_url' => $this->resolvePartnerUrl($row->partner_id),
                'crm_url' => $this->resolveCrmUrl($externalLeadId),
                'stage_id' => $row->stage_id ? (int) $row->stage_id : null,
                'stage_name' => (string) ($row->stage_name ?: 'Planning'),
                'stage_color' => $this->resolveStageColor((string) ($row->stage_name ?: 'Planning')),
                'hours_logged' => round($hoursLogged, 2),
                'allocated_hours' => (float) ($row->allocated_hours ?? 0),
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'cost_to_revenue' => $costToRevenue,
                'margin' => round($margin, 2),
                'margin_pct' => $marginPct,
                'is_active' => (bool) $row->is_active,
            ];
        })->toArray();
    }

    public function getBoardColumnsProperty(): array
    {
        $projects = collect($this->projectRows);

        if (! Schema::hasTable('projects_project_stages')) {
            return [];
        }

        $stages = DB::table('projects_project_stages')
            ->whereNull('deleted_at')
            ->orderBy('sort')
            ->get(['id', 'name']);

        $columns = $stages->map(function ($stage) use ($projects) {
            $rows = $projects->where('stage_id', $stage->id)->values()->all();

            return [
                'id' => (int) $stage->id,
                'name' => (string) $stage->name,
                'rows' => $rows,
            ];
        })->all();

        $noStageRows = $projects->whereNull('stage_id')->values()->all();

        if (! empty($noStageRows)) {
            $columns[] = [
                'id' => null,
                'name' => 'Unstaged',
                'rows' => $noStageRows,
            ];
        }

        return $columns;
    }

    protected function resolveProjectRevenue(int $projectId, int $partnerId, mixed $startDate, mixed $endDate): float
    {
        $linkedRevenue = 0.0;

        if (Schema::hasTable('dinx_project_invoice_links') && Schema::hasTable('accounts_account_moves')) {
            $linkedRevenue = (float) DinxProjectInvoiceLink::query()
                ->where('project_id', $projectId)
                ->join('accounts_account_moves as m', 'm.id', '=', 'dinx_project_invoice_links.invoice_id')
                ->where('m.state', 'posted')
                ->sum('m.amount_total');
        }

        if ($linkedRevenue > 0) {
            return $linkedRevenue;
        }

        if (! $partnerId) {
            return 0.0;
        }

        $fallback = Invoice::query()
            ->where('partner_id', $partnerId)
            ->where('state', 'posted');

        if ($startDate && $endDate) {
            $fallback->whereBetween('invoice_date', [$startDate, $endDate]);
        } else {
            $fallback->where('invoice_date', '>=', now()->subYear()->toDateString());
        }

        return (float) $fallback->sum('amount_total');
    }

    protected function resolvePartnerUrl(?int $partnerId): ?string
    {
        if (! $partnerId) {
            return null;
        }

        if (class_exists(\Webkul\Account\Filament\Resources\PartnerResource::class)) {
            return \Webkul\Account\Filament\Resources\PartnerResource::getUrl('view', ['record' => $partnerId]);
        }

        return '/admin/partners/'.$partnerId;
    }

    protected function resolveCrmUrl(?string $leadId): ?string
    {
        if (! is_string($leadId) || trim($leadId) === '') {
            return null;
        }

        return str_replace('{lead_id}', urlencode(trim($leadId)), $this->crmUrlTemplate);
    }

    protected function resolveStageColor(string $stageName): string
    {
        $value = Str::lower($stageName);

        if (str_contains($value, 'progress')) {
            return 'blue';
        }

        if (str_contains($value, 'complete') || str_contains($value, 'done')) {
            return 'green';
        }

        if (str_contains($value, 'blocked') || str_contains($value, 'risk')) {
            return 'red';
        }

        return 'gray';
    }
}
