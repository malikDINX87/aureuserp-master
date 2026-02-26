<x-filament-panels::page>
    @php
        $scorecards = $this->scorecards;
        $projects = $this->projectRows;
        $columns = $this->boardColumns;
        $stageOptions = $this->stageOptions;
    @endphp

    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Active Projects</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format((int) ($scorecards['active_projects'] ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Billable Hours This Month</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format((float) ($scorecards['billable_hours'] ?? 0), 2) }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Total Project Value</p>
                <p class="mt-2 text-3xl font-semibold text-slate-900">${{ number_format((float) ($scorecards['total_value'] ?? 0), 2) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="setViewMode('list')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $this->viewMode === 'list' ? 'bg-[#004AAD] text-white' : 'bg-slate-100 text-slate-700' }}">
                        List View
                    </button>
                    <button type="button" wire:click="setViewMode('board')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $this->viewMode === 'board' ? 'bg-[#004AAD] text-white' : 'bg-slate-100 text-slate-700' }}">
                        Board View
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search projects or clients"
                        class="h-9 w-60 rounded-md border border-slate-300 px-3 text-sm"
                    />

                    <select wire:model.live="stageId" class="h-9 rounded-md border border-slate-300 px-3 text-sm">
                        <option value="">All stages</option>
                        @foreach ($stageOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($this->viewMode === 'list')
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-3 py-2">Project</th>
                                <th class="px-3 py-2">Client</th>
                                <th class="px-3 py-2">Stage</th>
                                <th class="px-3 py-2">Hours</th>
                                <th class="px-3 py-2">Revenue</th>
                                <th class="px-3 py-2">Cost</th>
                                <th class="px-3 py-2">Profitability</th>
                                <th class="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($projects as $project)
                                <tr class="border-b border-slate-100">
                                    <td class="px-3 py-3 font-medium text-slate-900">{{ $project['name'] }}</td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-col gap-1">
                                            @if ($project['partner_url'])
                                                <a href="{{ $project['partner_url'] }}" class="text-[#004AAD] hover:underline">{{ $project['partner_name'] }}</a>
                                            @else
                                                <span class="text-slate-700">{{ $project['partner_name'] }}</span>
                                            @endif

                                            @if ($project['crm_url'])
                                                <a href="{{ $project['crm_url'] }}" target="_blank" rel="noreferrer" class="text-xs text-slate-500 hover:text-[#004AAD] hover:underline">Open CRM profile</a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                            {{ $project['stage_color'] === 'green' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                            {{ $project['stage_color'] === 'blue' ? 'bg-blue-100 text-blue-700' : '' }}
                                            {{ $project['stage_color'] === 'red' ? 'bg-rose-100 text-rose-700' : '' }}
                                            {{ $project['stage_color'] === 'gray' ? 'bg-slate-100 text-slate-700' : '' }}
                                        ">
                                            {{ $project['stage_name'] }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3 text-slate-700">{{ number_format($project['hours_logged'], 2) }}</td>
                                    <td class="px-3 py-3 text-slate-700">${{ number_format($project['revenue'], 2) }}</td>
                                    <td class="px-3 py-3 text-slate-700">${{ number_format($project['cost'], 2) }}</td>
                                    <td class="px-3 py-3">
                                        <div class="space-y-1">
                                            <div class="h-2 w-44 rounded-full bg-slate-100">
                                                <div class="h-2 rounded-full {{ $project['margin'] >= 0 ? 'bg-emerald-500' : 'bg-rose-500' }}" style="width: {{ max(4, min(100, $project['cost_to_revenue'])) }}%"></div>
                                            </div>
                                            <p class="text-xs text-slate-500">Margin: {{ number_format($project['margin_pct'], 2) }}%</p>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3">
                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" wire:click="createInvoiceFromProject({{ $project['id'] }})" class="rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-medium text-white hover:bg-[#003D8F]">
                                                Create Invoice
                                            </button>
                                            <button type="button" wire:click="viewContractFromProject({{ $project['id'] }})" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">
                                                View Contract
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-3 py-10 text-center text-slate-500">No projects match your filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="mt-4 grid gap-4 lg:grid-cols-3">
                    @foreach ($columns as $column)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-800">{{ $column['name'] }}</h3>
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs text-slate-500">{{ count($column['rows']) }}</span>
                            </div>

                            <div class="space-y-2">
                                @forelse ($column['rows'] as $project)
                                    <div class="rounded-lg border border-slate-200 bg-white p-3">
                                        <p class="font-medium text-slate-900">{{ $project['name'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $project['partner_name'] }}</p>
                                        <div class="mt-2 flex items-center justify-between text-xs text-slate-600">
                                            <span>${{ number_format($project['revenue'], 0) }} rev</span>
                                            <span>${{ number_format($project['cost'], 0) }} cost</span>
                                        </div>
                                        <div class="mt-2 flex gap-2">
                                            <button type="button" wire:click="createInvoiceFromProject({{ $project['id'] }})" class="rounded-md bg-[#004AAD] px-2 py-1 text-xs font-medium text-white">Invoice</button>
                                            <button type="button" wire:click="viewContractFromProject({{ $project['id'] }})" class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700">Contract</button>
                                        </div>
                                    </div>
                                @empty
                                    <p class="rounded-md border border-dashed border-slate-300 p-3 text-xs text-slate-500">No projects in this stage.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
