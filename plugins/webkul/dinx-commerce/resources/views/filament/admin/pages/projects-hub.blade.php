<x-filament-panels::page>
    @php
        $scorecards = $this->scorecards;
        $projects = $this->projectRows;
        $columns = $this->boardColumns;
        $stageOptions = $this->stageOptions;
    @endphp

    <div class="dinx-shell">
        <x-dinx-commerce::filament.admin.components.workspace-hero
            title="Projects"
            subtitle="Track delivery, billable execution, and margin performance across active work."
        >
            <a href="/admin/projects/projects/create" class="dinx-focus-ring rounded-md bg-[#004AAD] px-3 py-2 text-xs font-semibold text-white hover:bg-[#003D8F]">
                + New Project
            </a>
        </x-dinx-commerce::filament.admin.components.workspace-hero>

        <div class="dinx-kpi-grid">
            <x-dinx-commerce::filament.admin.components.kpi-card
                label="Active Projects"
                :value="number_format((int) ($scorecards['active_projects'] ?? 0))"
            />
            <x-dinx-commerce::filament.admin.components.kpi-card
                label="Billable Hours This Month"
                :value="number_format((float) ($scorecards['billable_hours'] ?? 0), 2)"
            />
            <x-dinx-commerce::filament.admin.components.kpi-card
                label="Total Project Value"
                :value="number_format((float) ($scorecards['total_value'] ?? 0), 2)"
                prefix="$"
                tone="positive"
            />
        </div>

        <div class="dinx-card">
            <div class="dinx-card-body space-y-4">
                <x-dinx-commerce::filament.admin.components.action-toolbar>
                    <div class="dinx-segmented" role="tablist" aria-label="Project view mode">
                        <button
                            type="button"
                            wire:click="setViewMode('list')"
                            class="dinx-segment-btn {{ $this->viewMode === 'list' ? 'is-active' : '' }}"
                            aria-pressed="{{ $this->viewMode === 'list' ? 'true' : 'false' }}"
                        >
                            List View
                        </button>
                        <button
                            type="button"
                            wire:click="setViewMode('board')"
                            class="dinx-segment-btn {{ $this->viewMode === 'board' ? 'is-active' : '' }}"
                            aria-pressed="{{ $this->viewMode === 'board' ? 'true' : 'false' }}"
                        >
                            Board View
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search projects or clients"
                            class="dinx-focus-ring h-9 w-60 rounded-md border border-slate-300 px-3 text-sm"
                        />

                        <select wire:model.live="stageId" class="dinx-focus-ring h-9 rounded-md border border-slate-300 px-3 text-sm">
                            <option value="">All stages</option>
                            @foreach ($stageOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-dinx-commerce::filament.admin.components.action-toolbar>

                @if ($this->viewMode === 'list')
                    <x-dinx-commerce::filament.admin.components.table-shell minWidth="1080px">
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Stage</th>
                                <th>Hours</th>
                                <th>Revenue</th>
                                <th>Cost</th>
                                <th>Profitability</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($projects as $project)
                                <tr>
                                    <td class="font-semibold text-slate-900">{{ $project['name'] }}</td>
                                    <td>
                                        <div class="flex flex-col gap-1">
                                            @if ($project['partner_url'])
                                                <a href="{{ $project['partner_url'] }}" class="text-[#004AAD] hover:underline">{{ $project['partner_name'] }}</a>
                                            @else
                                                <span>{{ $project['partner_name'] }}</span>
                                            @endif

                                            @if ($project['crm_url'])
                                                <a href="{{ $project['crm_url'] }}" target="_blank" rel="noreferrer" class="text-xs text-slate-500 hover:text-[#004AAD] hover:underline">
                                                    Open CRM profile
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @php
                                            $stageColor = $project['stage_color'] === 'gray'
                                                ? 'slate'
                                                : ($project['stage_color'] === 'red' ? 'rose' : $project['stage_color']);
                                        @endphp
                                        <x-dinx-commerce::filament.admin.components.status-pill
                                            :label="$project['stage_name']"
                                            :color="$stageColor"
                                        />
                                    </td>
                                    <td>{{ number_format($project['hours_logged'], 2) }}</td>
                                    <td>${{ number_format($project['revenue'], 2) }}</td>
                                    <td>${{ number_format($project['cost'], 2) }}</td>
                                    <td>
                                        <div class="space-y-1">
                                            <div class="h-2 w-44 rounded-full bg-slate-100">
                                                <div
                                                    class="h-2 rounded-full {{ $project['margin'] >= 0 ? 'bg-emerald-500' : 'bg-rose-500' }}"
                                                    style="width: {{ max(4, min(100, $project['cost_to_revenue'])) }}%"
                                                ></div>
                                            </div>
                                            <p class="text-xs text-slate-500">Margin: {{ number_format($project['margin_pct'], 2) }}%</p>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                wire:click="createInvoiceFromProject({{ $project['id'] }})"
                                                class="dinx-focus-ring rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-[#003D8F]"
                                            >
                                                Create Invoice
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="viewContractFromProject({{ $project['id'] }})"
                                                class="dinx-focus-ring rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                                            >
                                                View Contract
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-dinx-commerce::filament.admin.components.empty-state message="No projects match your current filters." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-dinx-commerce::filament.admin.components.table-shell>
                @else
                    <div class="grid gap-4 lg:grid-cols-3">
                        @foreach ($columns as $column)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold text-slate-800">{{ $column['name'] }}</h3>
                                    <span class="rounded-full bg-white px-2 py-0.5 text-xs text-slate-500">{{ count($column['rows']) }}</span>
                                </div>

                                <div class="space-y-2">
                                    @forelse ($column['rows'] as $project)
                                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                                            <p class="font-semibold text-slate-900">{{ $project['name'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $project['partner_name'] }}</p>
                                            <div class="mt-2 flex items-center justify-between text-xs text-slate-600">
                                                <span>${{ number_format($project['revenue'], 0) }} rev</span>
                                                <span>${{ number_format($project['cost'], 0) }} cost</span>
                                            </div>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    wire:click="createInvoiceFromProject({{ $project['id'] }})"
                                                    class="dinx-focus-ring rounded-md bg-[#004AAD] px-2 py-1 text-xs font-semibold text-white hover:bg-[#003D8F]"
                                                >
                                                    Invoice
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="viewContractFromProject({{ $project['id'] }})"
                                                    class="dinx-focus-ring rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                                                >
                                                    Contract
                                                </button>
                                            </div>
                                        </div>
                                    @empty
                                        <x-dinx-commerce::filament.admin.components.empty-state message="No projects in this stage." />
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
