<x-filament-panels::page>
    @php
        $integrationStatus = $this->integrationStatus;
        $teamOptions = $this->teamPermissionOptions;
    @endphp

    <div class="dinx-shell">
        <x-dinx-commerce::filament.admin.components.workspace-hero
            title="Settings"
            subtitle="Company, billing, permissions, and integrations for DINX ERP workspace."
        />

        <div class="grid gap-4 lg:grid-cols-[260px_minmax(0,1fr)]">
            <div class="dinx-card">
                <div class="dinx-card-body space-y-2">
                    <p class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Settings Menu</p>
                    <button type="button" wire:click="setActiveTab('company')" class="dinx-focus-ring w-full rounded-md px-3 py-2 text-left text-sm font-semibold {{ $this->activeTab === 'company' ? 'bg-[#004AAD] text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' }}">Company</button>
                    <button type="button" wire:click="setActiveTab('billing')" class="dinx-focus-ring w-full rounded-md px-3 py-2 text-left text-sm font-semibold {{ $this->activeTab === 'billing' ? 'bg-[#004AAD] text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' }}">Billing</button>
                    <button type="button" wire:click="setActiveTab('team')" class="dinx-focus-ring w-full rounded-md px-3 py-2 text-left text-sm font-semibold {{ $this->activeTab === 'team' ? 'bg-[#004AAD] text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' }}">Team</button>
                    <button type="button" wire:click="setActiveTab('integrations')" class="dinx-focus-ring w-full rounded-md px-3 py-2 text-left text-sm font-semibold {{ $this->activeTab === 'integrations' ? 'bg-[#004AAD] text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' }}">Integrations</button>
                </div>
            </div>

            <div class="dinx-card">
                <div class="dinx-card-body">
                    @if ($this->activeTab === 'company')
                        <div class="space-y-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Company Profile</h2>
                                <p class="text-sm text-slate-500">Base company details shown across ERP pages and generated documents.</p>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="space-y-1 text-sm"><span class="text-slate-700">Company Name</span><input type="text" wire:model="companyName" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                <label class="space-y-1 text-sm"><span class="text-slate-700">Company Email</span><input type="email" wire:model="companyEmail" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                <label class="space-y-1 text-sm"><span class="text-slate-700">Company Phone</span><input type="text" wire:model="companyPhone" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                <label class="space-y-1 text-sm"><span class="text-slate-700">Website</span><input type="text" wire:model="companyWebsite" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="space-y-1 text-sm"><span class="text-slate-700">Brand Logo Path</span><input type="text" wire:model="brandLogoPath" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" placeholder="images/dinx-logo.png" /></label>
                                <label class="space-y-1 text-sm"><span class="text-slate-700">Primary Hex</span><input type="text" wire:model="brandPrimaryHex" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                <label class="space-y-1 text-sm"><span class="text-slate-700">Secondary Hex</span><input type="text" wire:model="brandSecondaryHex" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" wire:click="saveCompany" class="dinx-focus-ring rounded-md bg-[#004AAD] px-3 py-2 text-xs font-semibold text-white hover:bg-[#003D8F]">Save Company</button>
                                <button type="button" wire:click="saveBranding" class="dinx-focus-ring rounded-md bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200">Save Branding</button>
                            </div>
                        </div>
                    @endif

                    @if ($this->activeTab === 'billing')
                        <div class="space-y-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Billing Defaults</h2>
                                <p class="text-sm text-slate-500">Set workspace pricing assumptions, CRM link template, and billing notifications.</p>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="space-y-1 text-sm"><span class="text-slate-700">Default Billable Hourly Rate</span><input type="number" step="0.01" wire:model="projectDefaultBillableRate" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                <label class="space-y-1 text-sm"><span class="text-slate-700">Default Cost Hourly Rate</span><input type="number" step="0.01" wire:model="projectDefaultCostRate" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                            </div>

                            <label class="space-y-1 text-sm">
                                <span class="text-slate-700">CRM Client URL Template</span>
                                <input type="text" wire:model="crmClientUrlTemplate" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" placeholder="https://.../{lead_id}" />
                            </label>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="notifyInvoicePaid" class="rounded border-slate-300" />
                                    Email me when an invoice is paid
                                </label>
                                <label class="flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                    <input type="checkbox" wire:model="notifyContractSigned" class="rounded border-slate-300" />
                                    Alert me when a contract is signed
                                </label>
                            </div>

                            <button type="button" wire:click="saveBilling" class="dinx-focus-ring rounded-md bg-[#004AAD] px-3 py-2 text-xs font-semibold text-white hover:bg-[#003D8F]">Save Billing Settings</button>
                        </div>
                    @endif

                    @if ($this->activeTab === 'team')
                        <div class="space-y-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Role Presets</h2>
                                <p class="text-sm text-slate-500">Configure reusable permission sets for Project Manager and Accountant.</p>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <h3 class="mb-2 text-sm font-semibold text-slate-800">Project Manager</h3>
                                    <div class="space-y-1.5">
                                        @foreach ($teamOptions as $permission => $label)
                                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox" value="{{ $permission }}" wire:model="projectManagerPermissions" class="rounded border-slate-300" />
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                    <h3 class="mb-2 text-sm font-semibold text-slate-800">Accountant</h3>
                                    <div class="space-y-1.5">
                                        @foreach ($teamOptions as $permission => $label)
                                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox" value="{{ $permission }}" wire:model="accountantPermissions" class="rounded border-slate-300" />
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <button type="button" wire:click="saveTeamPresets" class="dinx-focus-ring rounded-md bg-[#004AAD] px-3 py-2 text-xs font-semibold text-white hover:bg-[#003D8F]">Save Role Presets</button>
                        </div>
                    @endif

                    @if ($this->activeTab === 'integrations')
                        <div class="space-y-5">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">Integrations</h2>
                                <p class="text-sm text-slate-500">Manage PayPal and DocuSign credentials, consent, and connection status.</p>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-lg border p-3 {{ $integrationStatus['paypal']['connected'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                                    <p class="text-sm font-semibold text-slate-900">PayPal</p>
                                    <p class="text-xs {{ $integrationStatus['paypal']['connected'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $integrationStatus['paypal']['label'] }}</p>
                                    <button type="button" wire:click="reauthenticatePayPal" class="dinx-focus-ring mt-2 rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Re-authenticate</button>
                                </div>
                                <div class="rounded-lg border p-3 {{ $integrationStatus['docusign']['connected'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}">
                                    <p class="text-sm font-semibold text-slate-900">DocuSign</p>
                                    <p class="text-xs {{ $integrationStatus['docusign']['connected'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $integrationStatus['docusign']['label'] }}</p>
                                    <button type="button" wire:click="grantDocuSignConsent" class="dinx-focus-ring mt-2 rounded-md bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">Grant Consent</button>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 p-3">
                                <h3 class="mb-3 text-sm font-semibold text-slate-800">PayPal</h3>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="space-y-1 text-sm"><span class="text-slate-700">Mode</span><select wire:model="paypalMode" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3"><option value="live">Live</option><option value="sandbox">Sandbox</option></select></label>
                                    <label class="space-y-1 text-sm"><span class="text-slate-700">Brand Name</span><input type="text" wire:model="paypalBrandName" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                    <label class="space-y-1 text-sm md:col-span-2"><span class="text-slate-700">Client ID</span><input type="text" wire:model="paypalClientId" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                    <label class="space-y-1 text-sm md:col-span-2"><span class="text-slate-700">Client Secret</span><input type="text" wire:model="paypalClientSecret" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                    <label class="space-y-1 text-sm md:col-span-2"><span class="text-slate-700">Webhook ID</span><input type="text" wire:model="paypalWebhookId" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 p-3">
                                <h3 class="mb-3 text-sm font-semibold text-slate-800">DocuSign</h3>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="space-y-1 text-sm"><span class="text-slate-700">Account ID</span><input type="text" wire:model="docuSignAccountId" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                    <label class="space-y-1 text-sm"><span class="text-slate-700">Integration Key</span><input type="text" wire:model="docuSignIntegrationKey" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                    <label class="space-y-1 text-sm"><span class="text-slate-700">User ID</span><input type="text" wire:model="docuSignUserId" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                    <label class="space-y-1 text-sm"><span class="text-slate-700">Base URI</span><input type="text" wire:model="docuSignBaseUri" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                    <label class="space-y-1 text-sm md:col-span-2"><span class="text-slate-700">Private Key Path</span><input type="text" wire:model="docuSignPrivateKeyPath" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                    <label class="space-y-1 text-sm md:col-span-2"><span class="text-slate-700">Webhook Secret</span><input type="text" wire:model="docuSignWebhookSecret" class="dinx-focus-ring h-10 w-full rounded-md border border-slate-300 px-3" /></label>
                                </div>
                            </div>

                            <button type="button" wire:click="saveIntegrations" class="dinx-focus-ring rounded-md bg-[#004AAD] px-3 py-2 text-xs font-semibold text-white hover:bg-[#003D8F]">Save Integration Settings</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
