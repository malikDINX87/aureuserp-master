<?php

namespace Webkul\DinxCommerce\Filament\Admin\Pages;

use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use UnitEnum;
use Webkul\DinxCommerce\Settings\DinxCommerceSettings;
use Webkul\DinxCommerce\Settings\DinxWorkspaceSettings;
use Webkul\Support\Models\Company;

class SettingsHub extends Page
{
    use HasPageShield;

    protected string $view = 'dinx-commerce::filament.admin.pages.settings-hub';

    protected static ?string $slug = 'erp/settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'DINX ERP';

    protected static ?int $navigationSort = 60;

    public string $activeTab = 'company';

    public ?int $companyId = null;

    public string $companyName = '';

    public string $companyEmail = '';

    public string $companyPhone = '';

    public string $companyWebsite = '';

    public float $projectDefaultBillableRate = 150.0;

    public float $projectDefaultCostRate = 75.0;

    public string $crmClientUrlTemplate = '';

    public string $brandLogoPath = '';

    public string $brandPrimaryHex = '#004AAD';

    public string $brandSecondaryHex = '#0F172A';

    public bool $notifyInvoicePaid = true;

    public bool $notifyContractSigned = true;

    public string $paypalMode = 'live';

    public string $paypalClientId = '';

    public string $paypalClientSecret = '';

    public string $paypalWebhookId = '';

    public string $paypalBrandName = 'DINX Solutions Inc';

    public string $docuSignAccountId = '';

    public string $docuSignIntegrationKey = '';

    public string $docuSignUserId = '';

    public string $docuSignBaseUri = '';

    public string $docuSignPrivateKeyPath = '';

    public string $docuSignWebhookSecret = '';

    public array $projectManagerPermissions = [];

    public array $accountantPermissions = [];

    protected static function getPagePermission(): ?string
    {
        return 'page_dinx_workspace_settings';
    }

    public static function getNavigationLabel(): string
    {
        return 'Settings';
    }

    public function mount(): void
    {
        $company = Company::query()->find(Auth::user()?->default_company_id)
            ?: Company::query()->latest('id')->first();

        if ($company) {
            $this->companyId = $company->id;
            $this->companyName = (string) ($company->name ?? '');
            $this->companyEmail = (string) ($company->email ?? '');
            $this->companyPhone = (string) ($company->phone ?? '');
            $this->companyWebsite = (string) ($company->website ?? '');
        }

        $workspace = app(DinxWorkspaceSettings::class);

        $this->projectDefaultBillableRate = (float) $workspace->project_default_billable_hourly_rate;
        $this->projectDefaultCostRate = (float) $workspace->project_default_cost_hourly_rate;
        $this->crmClientUrlTemplate = (string) ($workspace->crm_client_url_template ?? '');
        $this->brandLogoPath = (string) ($workspace->brand_logo_path ?? '');
        $this->brandPrimaryHex = (string) ($workspace->brand_primary_hex ?: '#004AAD');
        $this->brandSecondaryHex = (string) ($workspace->brand_secondary_hex ?: '#0F172A');
        $this->notifyInvoicePaid = (bool) $workspace->notify_invoice_paid;
        $this->notifyContractSigned = (bool) $workspace->notify_contract_signed;

        $commerce = app(DinxCommerceSettings::class);

        $this->paypalMode = (string) ($commerce->paypal_mode ?: 'live');
        $this->paypalClientId = (string) ($commerce->paypal_client_id ?? '');
        $this->paypalClientSecret = (string) ($commerce->paypal_client_secret ?? '');
        $this->paypalWebhookId = (string) ($commerce->paypal_webhook_id ?? '');
        $this->paypalBrandName = (string) ($commerce->paypal_brand_name ?: 'DINX Solutions Inc');
        $this->docuSignAccountId = (string) ($commerce->docusign_account_id ?? '');
        $this->docuSignIntegrationKey = (string) ($commerce->docusign_integration_key ?? '');
        $this->docuSignUserId = (string) ($commerce->docusign_user_id ?? '');
        $this->docuSignBaseUri = (string) ($commerce->docusign_base_uri ?? '');
        $this->docuSignPrivateKeyPath = (string) ($commerce->docusign_private_key_path ?? '');
        $this->docuSignWebhookSecret = (string) ($commerce->docusign_webhook_secret ?? '');

        $this->hydrateRolePermissions();
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['company', 'billing', 'team', 'integrations'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function saveCompany(): void
    {
        if (! $this->companyId) {
            Notification::make()->title('No company selected')->warning()->send();

            return;
        }

        $company = Company::query()->find($this->companyId);

        if (! $company) {
            Notification::make()->title('Company record not found')->danger()->send();

            return;
        }

        $company->forceFill([
            'name' => trim($this->companyName),
            'email' => trim($this->companyEmail),
            'phone' => trim($this->companyPhone),
            'website' => trim($this->companyWebsite),
        ])->save();

        Notification::make()->title('Company profile updated')->success()->send();
    }

    public function saveBilling(): void
    {
        $workspace = app(DinxWorkspaceSettings::class);

        $workspace->project_default_billable_hourly_rate = max(0, (float) $this->projectDefaultBillableRate);
        $workspace->project_default_cost_hourly_rate = max(0, (float) $this->projectDefaultCostRate);
        $workspace->crm_client_url_template = trim($this->crmClientUrlTemplate);
        $workspace->notify_invoice_paid = (bool) $this->notifyInvoicePaid;
        $workspace->notify_contract_signed = (bool) $this->notifyContractSigned;
        $workspace->save();

        Notification::make()->title('Billing settings saved')->success()->send();
    }

    public function saveBranding(): void
    {
        $workspace = app(DinxWorkspaceSettings::class);

        $workspace->brand_logo_path = trim($this->brandLogoPath);
        $workspace->brand_primary_hex = strtoupper(trim($this->brandPrimaryHex));
        $workspace->brand_secondary_hex = strtoupper(trim($this->brandSecondaryHex));
        $workspace->save();

        Notification::make()->title('Brand settings updated')->success()->send();
    }

    public function saveIntegrations(): void
    {
        $settings = app(DinxCommerceSettings::class);

        $settings->paypal_mode = in_array($this->paypalMode, ['sandbox', 'live'], true) ? $this->paypalMode : 'live';
        $settings->paypal_client_id = trim($this->paypalClientId);
        $settings->paypal_client_secret = trim($this->paypalClientSecret);
        $settings->paypal_webhook_id = trim($this->paypalWebhookId);
        $settings->paypal_brand_name = trim($this->paypalBrandName);
        $settings->docusign_account_id = trim($this->docuSignAccountId);
        $settings->docusign_integration_key = trim($this->docuSignIntegrationKey);
        $settings->docusign_user_id = trim($this->docuSignUserId);
        $settings->docusign_base_uri = trim($this->docuSignBaseUri);
        $settings->docusign_private_key_path = trim($this->docuSignPrivateKeyPath);
        $settings->docusign_webhook_secret = trim($this->docuSignWebhookSecret);
        $settings->save();

        Notification::make()->title('Integration settings saved')->success()->send();
    }

    public function reauthenticatePayPal(): void
    {
        Notification::make()
            ->title('PayPal credentials check')
            ->body('Save integration settings after updating your PayPal keys or webhook ID.')
            ->success()
            ->send();
    }

    public function grantDocuSignConsent(): void
    {
        if ($this->docuSignIntegrationKey === '' || $this->docuSignBaseUri === '') {
            Notification::make()
                ->title('DocuSign setup incomplete')
                ->body('Set Integration Key and Base URI first, then save settings.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('DocuSign consent required')
            ->body('Use your DocuSign admin account to grant consent for the configured integration key.')
            ->warning()
            ->persistent()
            ->send();
    }

    public function saveTeamPresets(): void
    {
        $projectManagerRole = Role::query()->firstOrCreate([
            'name' => 'Project Manager',
            'guard_name' => 'web',
        ]);

        $accountantRole = Role::query()->firstOrCreate([
            'name' => 'Accountant',
            'guard_name' => 'web',
        ]);

        $projectPermissions = $this->sanitizePermissionNames($this->projectManagerPermissions);
        $accountantPermissions = $this->sanitizePermissionNames($this->accountantPermissions);

        $projectManagerBase = $projectManagerRole->permissions
            ->pluck('name')
            ->reject(fn (string $name) => str_starts_with($name, 'page_dinx_workspace_'))
            ->values()
            ->all();

        $accountantBase = $accountantRole->permissions
            ->pluck('name')
            ->reject(fn (string $name) => str_starts_with($name, 'page_dinx_workspace_'))
            ->values()
            ->all();

        $projectManagerRole->syncPermissions(array_values(array_unique(array_merge($projectManagerBase, $projectPermissions))));
        $accountantRole->syncPermissions(array_values(array_unique(array_merge($accountantBase, $accountantPermissions))));

        Notification::make()->title('Role presets updated')->success()->send();
    }

    public function getIntegrationStatusProperty(): array
    {
        $paypalConnected = $this->paypalClientId !== ''
            && $this->paypalClientSecret !== ''
            && $this->paypalWebhookId !== '';

        $docuSignConnected = $this->docuSignIntegrationKey !== ''
            && $this->docuSignUserId !== ''
            && $this->docuSignAccountId !== ''
            && $this->docuSignBaseUri !== '';

        return [
            'paypal' => [
                'connected' => $paypalConnected,
                'label' => $paypalConnected ? 'Connected' : 'Action required',
            ],
            'docusign' => [
                'connected' => $docuSignConnected,
                'label' => $docuSignConnected ? 'Connected' : 'Action required',
            ],
        ];
    }

    public function getTeamPermissionOptionsProperty(): array
    {
        return [
            'page_dinx_workspace_projects' => 'Projects',
            'page_dinx_workspace_contracts' => 'Contracts',
            'page_dinx_workspace_invoices' => 'Invoices',
            'page_dinx_workspace_accounting' => 'Accounting',
            'page_dinx_workspace_reports' => 'Reports',
            'page_dinx_workspace_settings' => 'Settings',
        ];
    }

    protected function hydrateRolePermissions(): void
    {
        $projectManager = Role::query()->where('name', 'Project Manager')->where('guard_name', 'web')->first();
        $accountant = Role::query()->where('name', 'Accountant')->where('guard_name', 'web')->first();

        $defaultProjectManager = [
            'page_dinx_workspace_projects',
            'page_dinx_workspace_contracts',
            'page_dinx_workspace_invoices',
            'page_dinx_workspace_reports',
        ];

        $defaultAccountant = [
            'page_dinx_workspace_invoices',
            'page_dinx_workspace_accounting',
            'page_dinx_workspace_reports',
        ];

        $this->projectManagerPermissions = $projectManager
            ? $projectManager->permissions()->pluck('name')->filter(fn ($name) => str_starts_with($name, 'page_dinx_workspace_'))->values()->all()
            : $defaultProjectManager;

        $this->accountantPermissions = $accountant
            ? $accountant->permissions()->pluck('name')->filter(fn ($name) => str_starts_with($name, 'page_dinx_workspace_'))->values()->all()
            : $defaultAccountant;
    }

    protected function sanitizePermissionNames(array $names): array
    {
        $allowed = array_keys($this->teamPermissionOptions);

        $clean = collect($names)
            ->filter(fn ($name) => in_array($name, $allowed, true))
            ->values()
            ->all();

        foreach ($clean as $name) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        return $clean;
    }
}
