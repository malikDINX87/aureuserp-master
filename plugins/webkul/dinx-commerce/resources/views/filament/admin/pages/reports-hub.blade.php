<x-filament-panels::page>
    @php
        $favoriteCards = $this->favoriteCards;
        $overviewCards = $this->overviewCards;
        $salesCards = $this->salesCards;
        $expenseCards = $this->expenseCards;
        $favoriteKeys = $this->favoriteKeys;
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Reports</h2>
                <p class="text-sm text-slate-500">Business intelligence cards with quick exports.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="setPeriod('month')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $this->period === 'month' ? 'bg-[#004AAD] text-white' : 'bg-slate-100 text-slate-700' }}">This Month</button>
                <button type="button" wire:click="setPeriod('quarter')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $this->period === 'quarter' ? 'bg-[#004AAD] text-white' : 'bg-slate-100 text-slate-700' }}">This Quarter</button>
                <button type="button" wire:click="setPeriod('ytd')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $this->period === 'ytd' ? 'bg-[#004AAD] text-white' : 'bg-slate-100 text-slate-700' }}">YTD</button>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex flex-wrap gap-2">
                <button type="button" wire:click="setTab('favorites')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $this->tab === 'favorites' ? 'bg-[#004AAD] text-white' : 'bg-slate-100 text-slate-700' }}">Favorites</button>
                <button type="button" wire:click="setTab('overview')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $this->tab === 'overview' ? 'bg-[#004AAD] text-white' : 'bg-slate-100 text-slate-700' }}">Business Overview</button>
                <button type="button" wire:click="setTab('sales')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $this->tab === 'sales' ? 'bg-[#004AAD] text-white' : 'bg-slate-100 text-slate-700' }}">Sales</button>
                <button type="button" wire:click="setTab('expenses')" class="rounded-md px-3 py-1.5 text-sm font-medium {{ $this->tab === 'expenses' ? 'bg-[#004AAD] text-white' : 'bg-slate-100 text-slate-700' }}">Expenses</button>
            </div>

            @if ($this->tab === 'favorites')
                <div class="grid gap-4 lg:grid-cols-3">
                    @foreach ($favoriteCards as $card)
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-3 flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $card['title'] }}</h3>
                                    <p class="text-xs text-slate-500">{{ $card['subtitle'] }}</p>
                                </div>
                                <button type="button" wire:click="toggleFavorite('{{ $card['key'] }}')" class="rounded px-1.5 py-0.5 text-xs font-semibold {{ in_array($card['key'], $favoriteKeys, true) ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600' }}">[*]</button>
                            </div>

                            <p class="text-2xl font-semibold {{ $card['tone'] === 'positive' ? 'text-emerald-700' : ($card['tone'] === 'negative' ? 'text-rose-700' : 'text-slate-900') }}">
                                {{ number_format($card['value'], 2) }}
                            </p>

                            <div class="mt-3 space-y-1 text-xs text-slate-600">
                                @foreach ($card['preview'] as $line)
                                    <p>{{ $line }}</p>
                                @endforeach
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button type="button" wire:click="runReport('{{ $card['key'] }}')" class="rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-medium text-white hover:bg-[#003D8F]">Run Report</button>
                                <button type="button" wire:click="exportPdf('{{ $card['key'] }}')" class="rounded-md bg-rose-100 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-200">Export PDF</button>
                                <button type="button" wire:click="exportExcel('{{ $card['key'] }}')" class="rounded-md bg-emerald-100 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-200">Export Excel</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($this->tab === 'overview')
                <div class="grid gap-4 lg:grid-cols-3">
                    @foreach ($overviewCards as $card)
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $card['title'] }}</h3>
                                    <p class="text-xs text-slate-500">{{ $card['subtitle'] }}</p>
                                </div>
                                <button type="button" wire:click="toggleFavorite('{{ $card['key'] }}')" class="rounded px-1.5 py-0.5 text-xs font-semibold {{ in_array($card['key'], $favoriteKeys, true) ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600' }}">[*]</button>
                            </div>
                            <p class="text-xl font-semibold text-slate-900">{{ number_format($card['value'], 2) }}</p>
                            <div class="mt-3 h-16 rounded-md bg-slate-50 p-2 text-xs text-slate-600">
                                @foreach ($card['preview'] as $line)
                                    <p>{{ $line }}</p>
                                @endforeach
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" wire:click="runReport('{{ $card['key'] }}')" class="rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-medium text-white">Run</button>
                                <button type="button" wire:click="exportPdf('{{ $card['key'] }}')" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700">PDF</button>
                                <button type="button" wire:click="exportExcel('{{ $card['key'] }}')" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700">Excel</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($this->tab === 'sales')
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($salesCards as $card)
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $card['title'] }}</h3>
                                    <p class="text-xs text-slate-500">{{ $card['subtitle'] }}</p>
                                </div>
                                <button type="button" wire:click="toggleFavorite('{{ $card['key'] }}')" class="rounded px-1.5 py-0.5 text-xs font-semibold {{ in_array($card['key'], $favoriteKeys, true) ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600' }}">[*]</button>
                            </div>
                            <p class="text-xl font-semibold text-slate-900">{{ number_format($card['value'], 2) }}</p>
                            <div class="mt-3 space-y-1 text-xs text-slate-600">
                                @foreach ($card['preview'] as $line)
                                    <p>{{ $line }}</p>
                                @endforeach
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" wire:click="runReport('{{ $card['key'] }}')" class="rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-medium text-white">Run</button>
                                <button type="button" wire:click="exportPdf('{{ $card['key'] }}')" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700">PDF</button>
                                <button type="button" wire:click="exportExcel('{{ $card['key'] }}')" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700">Excel</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($this->tab === 'expenses')
                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach ($expenseCards as $card)
                        <div class="rounded-xl border border-slate-200 bg-white p-4">
                            <div class="mb-2 flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900">{{ $card['title'] }}</h3>
                                    <p class="text-xs text-slate-500">{{ $card['subtitle'] }}</p>
                                </div>
                                <button type="button" wire:click="toggleFavorite('{{ $card['key'] }}')" class="rounded px-1.5 py-0.5 text-xs font-semibold {{ in_array($card['key'], $favoriteKeys, true) ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600' }}">[*]</button>
                            </div>
                            <p class="text-xl font-semibold text-slate-900">{{ number_format($card['value'], 2) }}</p>
                            <div class="mt-3 space-y-1 text-xs text-slate-600">
                                @foreach ($card['preview'] as $line)
                                    <p>{{ $line }}</p>
                                @endforeach
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button type="button" wire:click="runReport('{{ $card['key'] }}')" class="rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-medium text-white">Run</button>
                                <button type="button" wire:click="exportPdf('{{ $card['key'] }}')" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700">PDF</button>
                                <button type="button" wire:click="exportExcel('{{ $card['key'] }}')" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700">Excel</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
