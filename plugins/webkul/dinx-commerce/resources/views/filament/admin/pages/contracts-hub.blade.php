<x-filament-panels::page>
    @php
        $counts = $this->pipelineCounts;
        $contracts = $this->contracts;
        $versions = $this->versionHistory;
        $pipelineLabels = ['draft' => 'Draft', 'sent' => 'Sent', 'viewed' => 'Viewed', 'signed' => 'Signed', 'expired' => 'Expired', 'all' => 'All'];
    @endphp

    <div class="dinx-shell">
        <x-dinx-commerce::filament.admin.components.workspace-hero
            title="Contracts"
            subtitle="DocuSign workflow hub for draft, delivery, signature tracking, and renewal actions."
        >
            <a href="/admin/dinx-contracts/create" class="dinx-focus-ring rounded-md bg-[#004AAD] px-3 py-2 text-xs font-semibold text-white hover:bg-[#003D8F]">
                + New Contract
            </a>
        </x-dinx-commerce::filament.admin.components.workspace-hero>

        <div class="dinx-card">
            <div class="dinx-card-body space-y-4">
                <div class="dinx-pipeline-bar">
                    @foreach ($pipelineLabels as $key => $label)
                        @php
                            $count = $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0);
                            $tone = match ($key) {
                                'draft' => 'slate',
                                'sent' => 'blue',
                                'viewed' => 'amber',
                                'signed' => 'green',
                                'expired' => 'rose',
                                default => 'slate',
                            };
                        @endphp
                        <button
                            type="button"
                            wire:click="setPipeline('{{ $key }}')"
                            class="dinx-bar-item dinx-focus-ring text-left {{ $this->pipeline === $key ? 'ring-2 ring-[#004AAD]' : '' }}"
                            aria-pressed="{{ $this->pipeline === $key ? 'true' : 'false' }}"
                        >
                            <x-dinx-commerce::filament.admin.components.status-pill :label="$label" :color="$tone" />
                            <p class="value text-slate-900">{{ $count }}</p>
                        </button>
                    @endforeach
                </div>

                <x-dinx-commerce::filament.admin.components.action-toolbar>
                    <div class="text-sm text-slate-600">
                        Live status updates include viewed/signed events from DocuSign webhooks.
                    </div>

                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search contracts or clients"
                        class="dinx-focus-ring h-9 w-72 rounded-md border border-slate-300 px-3 text-sm"
                    />
                </x-dinx-commerce::filament.admin.components.action-toolbar>

                <x-dinx-commerce::filament.admin.components.table-shell minWidth="1040px">
                    <thead>
                        <tr>
                            <th>Contract</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Value</th>
                            <th>Expiry</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contracts as $contract)
                            <tr class="{{ $contract['is_expired'] ? 'is-overdue' : '' }}">
                                <td>
                                    <p class="font-semibold text-slate-900">{{ $contract['title'] }}</p>
                                    <button type="button" wire:click="openVersionDrawer({{ $contract['id'] }})" class="dinx-focus-ring mt-1 rounded px-1 text-xs text-[#004AAD] hover:underline">
                                        Version history
                                    </button>
                                </td>
                                <td>{{ $contract['client'] }}</td>
                                <td>
                                    @php
                                        $statusColor = $contract['status'] === 'draft'
                                            ? 'slate'
                                            : ($contract['status'] === 'sent'
                                                ? 'blue'
                                                : ($contract['status'] === 'viewed'
                                                    ? 'amber'
                                                    : ($contract['status'] === 'signed' ? 'green' : 'rose')));
                                        $indicator = $contract['status_icon'] === 'eye' ? 'o' : ($contract['status_icon'] === 'pen' ? '*' : null);
                                    @endphp
                                    <x-dinx-commerce::filament.admin.components.status-pill
                                        :label="strtoupper($contract['status'])"
                                        :color="$statusColor"
                                        :indicator="$indicator"
                                    />
                                    @if ($contract['last_event_message'])
                                        <p class="mt-1 text-xs text-slate-500">{{ $contract['last_event_message'] }}</p>
                                    @endif
                                </td>
                                <td>{{ $contract['currency'] }} {{ number_format($contract['amount'], 2) }}</td>
                                <td>
                                    @if ($contract['expiration_date'])
                                        <span class="{{ $contract['is_expiring_soon'] ? 'font-semibold text-amber-700' : '' }}">
                                            {{ $contract['expiration_date']->format('M d, Y') }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">N/A</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        @if ($contract['primary_action'] === 'send')
                                            <button type="button" wire:click="sendViaDocuSign({{ $contract['id'] }})" class="dinx-focus-ring rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-[#003D8F]">Send via DocuSign</button>
                                        @elseif ($contract['primary_action'] === 'remind')
                                            <button type="button" wire:click="remindClient({{ $contract['id'] }})" class="dinx-focus-ring rounded-md bg-amber-100 px-2.5 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-200">Remind Client</button>
                                        @elseif ($contract['primary_action'] === 'view')
                                            <button type="button" wire:click="openSignedPdf({{ $contract['id'] }})" class="dinx-focus-ring rounded-md bg-emerald-100 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-200">View Signed PDF</button>
                                        @else
                                            <button type="button" wire:click="sendRenewal({{ $contract['id'] }})" class="dinx-focus-ring rounded-md bg-rose-100 px-2.5 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-200">Send Renewal</button>
                                        @endif

                                        <a href="/admin/dinx-contracts/{{ $contract['id'] }}" class="dinx-focus-ring rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                                            Open
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-dinx-commerce::filament.admin.components.empty-state message="No contracts found for the selected filter." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-dinx-commerce::filament.admin.components.table-shell>
            </div>
        </div>
    </div>

    <x-dinx-commerce::filament.admin.components.right-drawer
        title="Version History"
        :open="$this->versionContractId !== null"
        closeAction="closeVersionDrawer"
    >
        <div class="space-y-3">
            @forelse ($versions as $version)
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-900">v{{ $version['version_number'] }}</p>
                        <span class="text-xs text-slate-500">{{ optional($version['created_at'])->format('M d, Y H:i') }}</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-600">{{ $version['label'] ?: 'Snapshot' }}</p>
                    <p class="mt-1 text-xs text-slate-500">Status: {{ strtoupper($version['status'] ?: 'unknown') }}</p>
                    @if ($version['creator'])
                        <p class="mt-1 text-xs text-slate-500">By: {{ $version['creator'] }}</p>
                    @endif
                </div>
            @empty
                <x-dinx-commerce::filament.admin.components.empty-state message="No saved versions yet." />
            @endforelse
        </div>
    </x-dinx-commerce::filament.admin.components.right-drawer>
</x-filament-panels::page>
