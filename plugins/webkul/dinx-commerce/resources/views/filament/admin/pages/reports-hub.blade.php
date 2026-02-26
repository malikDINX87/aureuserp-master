<x-filament-panels::page>
    @php
        $favoriteCards = $this->favoriteCards;
        $overviewCards = $this->overviewCards;
        $salesCards = $this->salesCards;
        $expenseCards = $this->expenseCards;
        $favoriteKeys = $this->favoriteKeys;
    @endphp

    <div class="dinx-shell">
        <x-dinx-commerce::filament.admin.components.workspace-hero
            title="Reports"
            subtitle="QuickBooks-style report cards with KPI previews and export shortcuts."
        >
            <div class="dinx-segmented" role="tablist" aria-label="Report period">
                <button type="button" wire:click="setPeriod('month')" class="dinx-segment-btn {{ $this->period === 'month' ? 'is-active' : '' }}" aria-pressed="{{ $this->period === 'month' ? 'true' : 'false' }}">This Month</button>
                <button type="button" wire:click="setPeriod('quarter')" class="dinx-segment-btn {{ $this->period === 'quarter' ? 'is-active' : '' }}" aria-pressed="{{ $this->period === 'quarter' ? 'true' : 'false' }}">This Quarter</button>
                <button type="button" wire:click="setPeriod('ytd')" class="dinx-segment-btn {{ $this->period === 'ytd' ? 'is-active' : '' }}" aria-pressed="{{ $this->period === 'ytd' ? 'true' : 'false' }}">YTD</button>
            </div>
        </x-dinx-commerce::filament.admin.components.workspace-hero>

        <div class="dinx-card">
            <div class="dinx-card-body space-y-4">
                <div class="dinx-segmented" role="tablist" aria-label="Report category">
                    <button type="button" wire:click="setTab('favorites')" class="dinx-segment-btn {{ $this->tab === 'favorites' ? 'is-active' : '' }}" aria-pressed="{{ $this->tab === 'favorites' ? 'true' : 'false' }}">Favorites</button>
                    <button type="button" wire:click="setTab('overview')" class="dinx-segment-btn {{ $this->tab === 'overview' ? 'is-active' : '' }}" aria-pressed="{{ $this->tab === 'overview' ? 'true' : 'false' }}">Business Overview</button>
                    <button type="button" wire:click="setTab('sales')" class="dinx-segment-btn {{ $this->tab === 'sales' ? 'is-active' : '' }}" aria-pressed="{{ $this->tab === 'sales' ? 'true' : 'false' }}">Sales</button>
                    <button type="button" wire:click="setTab('expenses')" class="dinx-segment-btn {{ $this->tab === 'expenses' ? 'is-active' : '' }}" aria-pressed="{{ $this->tab === 'expenses' ? 'true' : 'false' }}">Expenses</button>
                </div>

                @php
                    $cards = $this->tab === 'favorites'
                        ? $favoriteCards
                        : ($this->tab === 'overview'
                            ? $overviewCards
                            : ($this->tab === 'sales' ? $salesCards : $expenseCards));

                    $gridClass = $this->tab === 'favorites'
                        ? 'grid gap-4 lg:grid-cols-3'
                        : 'grid gap-4 lg:grid-cols-2';
                @endphp

                <div class="{{ $gridClass }}">
                    @forelse ($cards as $card)
                        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="mb-3 flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $card['title'] }}</h3>
                                    <p class="text-xs text-slate-500">{{ $card['subtitle'] }}</p>
                                </div>
                                <button
                                    type="button"
                                    wire:click="toggleFavorite('{{ $card['key'] }}')"
                                    class="dinx-focus-ring rounded px-1.5 py-0.5 text-xs font-semibold {{ in_array($card['key'], $favoriteKeys, true) ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600' }}"
                                >
                                    [*]
                                </button>
                            </div>

                            <p class="text-2xl font-semibold {{ $card['tone'] === 'positive' ? 'text-emerald-700' : ($card['tone'] === 'negative' ? 'text-rose-700' : ($card['tone'] === 'warning' ? 'text-amber-700' : 'text-slate-900')) }}">
                                {{ number_format($card['value'], 2) }}
                            </p>

                            <div class="mt-3 min-h-16 rounded-md border border-slate-200 bg-slate-50 p-2 text-xs text-slate-600">
                                @foreach ($card['preview'] as $line)
                                    <p>{{ $line }}</p>
                                @endforeach
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" wire:click="runReport('{{ $card['key'] }}')" class="dinx-focus-ring rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-[#003D8F]">Run Report</button>
                                <button type="button" wire:click="exportPdf('{{ $card['key'] }}')" class="dinx-focus-ring rounded-md bg-rose-100 px-2.5 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-200">Export PDF</button>
                                <button type="button" wire:click="exportExcel('{{ $card['key'] }}')" class="dinx-focus-ring rounded-md bg-emerald-100 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-200">Export Excel</button>
                            </div>
                        </div>
                    @empty
                        <x-dinx-commerce::filament.admin.components.empty-state message="No report cards available yet." />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
