<?php

namespace Webkul\DinxCommerce\Filament\Admin\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use UnitEnum;
use Webkul\DinxCommerce\Models\DinxContract;
use Webkul\DinxCommerce\Models\DinxContractEvent;
use Webkul\DinxCommerce\Models\DinxContractVersion;
use Webkul\DinxCommerce\Services\DocuSignService;

class ContractsHub extends Page
{
    use HasPageShield;

    protected string $view = 'dinx-commerce::filament.admin.pages.contracts-hub';

    protected static ?string $slug = 'erp/contracts';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'DINX ERP';

    protected static ?int $navigationSort = 20;

    public string $pipeline = 'all';

    public string $search = '';

    public ?int $versionContractId = null;

    protected static function getPagePermission(): ?string
    {
        return 'page_dinx_workspace_contracts';
    }

    public static function getNavigationLabel(): string
    {
        return 'Contracts';
    }

    public function setPipeline(string $pipeline): void
    {
        $allowed = ['all', 'draft', 'sent', 'viewed', 'signed', 'expired'];

        if (in_array($pipeline, $allowed, true)) {
            $this->pipeline = $pipeline;
        }
    }

    public function sendViaDocuSign(int $contractId): void
    {
        $contract = DinxContract::query()->find($contractId);

        if (! $contract) {
            Notification::make()->title('Contract not found')->danger()->send();

            return;
        }

        $result = app(DocuSignService::class)->sendEnvelope($contract);

        Notification::make()
            ->title((string) ($result['message'] ?? 'Contract sent'))
            ->success()
            ->send();
    }

    public function remindClient(int $contractId): void
    {
        $contract = DinxContract::query()->find($contractId);

        if (! $contract) {
            Notification::make()->title('Contract not found')->danger()->send();

            return;
        }

        $result = app(DocuSignService::class)->sendReminder($contract);

        Notification::make()
            ->title((string) ($result['message'] ?? 'Reminder sent'))
            ->success()
            ->send();
    }

    public function sendRenewal(int $contractId): void
    {
        $contract = DinxContract::query()->find($contractId);

        if (! $contract) {
            Notification::make()->title('Contract not found')->danger()->send();

            return;
        }

        $renewal = $contract->replicate([
            'status',
            'signed_at',
            'docusign_envelope_id',
            'signed_document_path',
            'created_at',
            'updated_at',
        ]);

        $renewal->title = trim($contract->title).' Renewal '.now()->format('Y');
        $renewal->status = 'draft';
        $renewal->effective_date = now()->toDateString();
        $renewal->expiration_date = now()->addYear()->toDateString();
        $renewal->created_by = Auth::id();
        $renewal->save();

        $this->snapshotVersion($renewal, 'Renewal Created');

        Notification::make()
            ->title('Renewal draft created')
            ->body('Contract #'.$renewal->id.' is ready to send.')
            ->success()
            ->send();
    }

    public function openSignedPdf(int $contractId): RedirectResponse
    {
        $contract = DinxContract::query()->find($contractId);

        if (! $contract?->signed_document_path) {
            Notification::make()->title('Signed document not available')->warning()->send();

            return redirect()->to('/admin/erp/contracts');
        }

        if (Str::startsWith($contract->signed_document_path, ['http://', 'https://'])) {
            return redirect()->away($contract->signed_document_path);
        }

        return redirect()->to('/storage/'.ltrim($contract->signed_document_path, '/'));
    }

    public function openVersionDrawer(int $contractId): void
    {
        $this->versionContractId = $contractId;
    }

    public function closeVersionDrawer(): void
    {
        $this->versionContractId = null;
    }

    public function getPipelineCountsProperty(): array
    {
        if (! Schema::hasTable('dinx_contracts')) {
            return [
                'draft' => 0,
                'sent' => 0,
                'viewed' => 0,
                'signed' => 0,
                'expired' => 0,
            ];
        }

        $contracts = DinxContract::query()->get(['status', 'expiration_date']);
        $today = now()->startOfDay();

        return [
            'draft' => $contracts->where('status', 'draft')->count(),
            'sent' => $contracts->where('status', 'sent')->count(),
            'viewed' => $contracts->where('status', 'viewed')->count(),
            'signed' => $contracts->where('status', 'completed')->count(),
            'expired' => $contracts->filter(function ($contract) use ($today) {
                if (! $contract->expiration_date) {
                    return false;
                }

                return $contract->expiration_date->lt($today) && $contract->status !== 'completed';
            })->count(),
        ];
    }

    public function getContractsProperty(): array
    {
        if (! Schema::hasTable('dinx_contracts')) {
            return [];
        }

        $query = DinxContract::query()
            ->with(['partner:id,name', 'currency:id,name'])
            ->latest('id');

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($builder) use ($term) {
                $builder->where('title', 'like', $term)
                    ->orWhereHas('partner', fn ($q) => $q->where('name', 'like', $term));
            });
        }

        if ($this->pipeline === 'signed') {
            $query->where('status', 'completed');
        } elseif ($this->pipeline === 'expired') {
            $query->whereDate('expiration_date', '<', now()->toDateString())
                ->where('status', '!=', 'completed');
        } elseif ($this->pipeline !== 'all') {
            $query->where('status', $this->pipeline);
        }

        return $query->limit(200)->get()->map(function (DinxContract $contract) {
            $displayStatus = $contract->status === 'completed' ? 'signed' : $contract->status;

            $lastEvent = DinxContractEvent::query()
                ->where('contract_id', $contract->id)
                ->where('provider', 'docusign')
                ->latest('occurred_at')
                ->first();

            $isExpiringSoon = $contract->expiration_date
                && $contract->expiration_date->between(now(), now()->addDays(30));

            $isExpired = $contract->expiration_date
                && $contract->expiration_date->lt(now())
                && $contract->status !== 'completed';

            $primaryAction = match ($displayStatus) {
                'draft' => 'send',
                'sent', 'viewed' => 'remind',
                'signed' => 'view',
                default => 'renew',
            };

            return [
                'id' => $contract->id,
                'title' => $contract->title,
                'client' => $contract->partner?->name ?? 'Unknown client',
                'status' => $displayStatus,
                'amount' => (float) $contract->amount_total,
                'currency' => $contract->currency?->name ?? 'USD',
                'expiration_date' => $contract->expiration_date,
                'is_expiring_soon' => $isExpiringSoon,
                'is_expired' => $isExpired,
                'primary_action' => $primaryAction,
                'status_icon' => match ($displayStatus) {
                    'viewed' => 'eye',
                    'signed' => 'pen',
                    default => null,
                },
                'last_event_message' => $lastEvent?->message,
            ];
        })->toArray();
    }

    public function getVersionHistoryProperty(): array
    {
        if (! $this->versionContractId) {
            return [];
        }

        if (! Schema::hasTable('dinx_contract_versions')) {
            return [];
        }

        return DinxContractVersion::query()
            ->where('contract_id', $this->versionContractId)
            ->orderByDesc('version_number')
            ->limit(20)
            ->get()
            ->map(fn (DinxContractVersion $version) => [
                'version_number' => $version->version_number,
                'label' => $version->label,
                'status' => $version->status,
                'created_at' => $version->created_at,
                'creator' => $version->creator?->name,
            ])
            ->toArray();
    }

    protected function snapshotVersion(DinxContract $contract, string $label): void
    {
        $latestVersion = (int) DinxContractVersion::query()
            ->where('contract_id', $contract->id)
            ->max('version_number');

        DinxContractVersion::query()->create([
            'contract_id' => $contract->id,
            'version_number' => $latestVersion + 1,
            'label' => $label,
            'status' => $contract->status,
            'terms_html' => $contract->terms_html,
            'snapshot' => $contract->only([
                'title',
                'status',
                'amount_total',
                'effective_date',
                'expiration_date',
            ]),
            'created_by' => Auth::id(),
        ]);
    }
}
