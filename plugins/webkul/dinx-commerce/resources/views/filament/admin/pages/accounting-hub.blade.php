<x-filament-panels::page>
    @php
        $sections = $this->accountSections;
        $selectedAccount = $this->selectedAccount;
        $register = $this->registerRows;
        $imports = $this->importHistory;
        $activeImport = $this->activeImport;
        $queue = $this->reconciliationQueue;
        $taxRules = $this->taxMapperRules;
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">General Ledger</h2>
                <p class="text-sm text-slate-500">Split view for chart of accounts and account register.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="openJournalEntryForm" class="rounded-md bg-[#004AAD] px-3 py-2 text-sm font-medium text-white hover:bg-[#003D8F]">+ New Journal Entry</button>
                <button type="button" wire:click="openCoaSettings" class="rounded-md bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">COA Settings</button>
                <button type="button" wire:click="openTaxSettings" class="rounded-md bg-indigo-100 px-3 py-2 text-sm font-medium text-indigo-800 hover:bg-indigo-200">Tax Mapper</button>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Chart of Accounts</h3>
                </div>

                <div class="space-y-4">
                    @forelse ($sections as $section)
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $section['label'] }}</p>
                            <div class="space-y-1">
                                @foreach ($section['accounts'] as $account)
                                    <button
                                        type="button"
                                        wire:click="selectAccount({{ $account['id'] }})"
                                        class="flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-sm {{ $account['is_selected'] ? 'bg-[#004AAD] text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' }}"
                                    >
                                        <span class="truncate">{{ trim(($account['code'] ? $account['code'].' ' : '').$account['name']) }}</span>
                                        <span class="ml-3 whitespace-nowrap text-xs font-semibold {{ $account['is_selected'] ? 'text-white/90' : 'text-slate-500' }}">{{ number_format($account['balance'], 2) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="rounded-md border border-dashed border-slate-300 p-3 text-xs text-slate-500">No chart of accounts data available yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">
                            Account Register
                            @if ($selectedAccount)
                                <span class="text-slate-500">/ {{ trim(($selectedAccount->code ? $selectedAccount->code.' ' : '').$selectedAccount->name) }}</span>
                            @endif
                        </h3>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <input type="search" wire:model.live.debounce.300ms="registerSearch" placeholder="Search register" class="h-9 w-56 rounded-md border border-slate-300 px-3 text-sm" />
                        <input type="date" wire:model.live="dateFrom" class="h-9 rounded-md border border-slate-300 px-2 text-sm" />
                        <input type="date" wire:model.live="dateTo" class="h-9 rounded-md border border-slate-300 px-2 text-sm" />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Date</th>
                                <th class="px-2 py-2">Type</th>
                                <th class="px-2 py-2">Payee</th>
                                <th class="px-2 py-2">Memo / Split</th>
                                <th class="px-2 py-2">Payment (Dr)</th>
                                <th class="px-2 py-2">Deposit (Cr)</th>
                                <th class="px-2 py-2">Balance</th>
                                <th class="px-2 py-2">Stat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($register as $row)
                                <tr class="border-b border-slate-100">
                                    <td class="px-2 py-2 text-slate-700">{{ optional($row['date'])->format('M d, Y') }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $row['type'] }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $row['payee'] }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $row['memo'] }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ number_format($row['balance'], 2) }}</td>
                                    <td class="px-2 py-2">
                                        @if ($row['status'])
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-700">{{ $row['status'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-2 py-10 text-center text-slate-500">No register entries for this account/filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Reconciliation Tool</h3>
                        <p class="text-sm text-slate-500">Import CSV lines and confirm suggested matches.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <input type="file" wire:model="statementUpload" accept=".csv,text/csv" class="text-xs" />
                        <button type="button" wire:click="importBankCsv" class="rounded-md bg-[#004AAD] px-3 py-2 text-sm font-medium text-white hover:bg-[#003D8F]">Import Bank Data</button>
                    </div>
                </div>

                @if ($activeImport)
                    <p class="mb-3 text-xs text-slate-500">
                        Active statement: <span class="font-semibold text-slate-700">{{ $activeImport->file_name }}</span>
                        ({{ $activeImport->matched_lines }}/{{ $activeImport->total_lines }} reconciled)
                    </p>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="px-2 py-2">Line</th>
                                <th class="px-2 py-2">Date</th>
                                <th class="px-2 py-2">Description</th>
                                <th class="px-2 py-2">Amount</th>
                                <th class="px-2 py-2">Suggested Account</th>
                                <th class="px-2 py-2">Suggested Match</th>
                                <th class="px-2 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($queue as $line)
                                <tr class="border-b border-slate-100">
                                    <td class="px-2 py-2 text-slate-700">#{{ $line['line_number'] }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ optional($line['transaction_date'])->format('M d, Y') }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $line['description'] }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ number_format($line['amount'], 2) }}</td>
                                    <td class="px-2 py-2 text-slate-700">{{ $line['suggested_account'] ?: 'N/A' }}</td>
                                    <td class="px-2 py-2 text-slate-700">
                                        @if ($line['match'])
                                            <div class="space-y-0.5">
                                                <p class="text-xs font-semibold text-slate-700">Score {{ number_format($line['match']['score'], 1) }}%</p>
                                                <p class="text-xs text-slate-500">{{ optional($line['match']['date'])->format('M d') }} | {{ number_format($line['match']['amount'], 2) }}</p>
                                                <p class="text-xs text-slate-500">{{ $line['match']['memo'] }}</p>
                                            </div>
                                        @else
                                            <span class="text-slate-400">No match</span>
                                        @endif
                                    </td>
                                    <td class="px-2 py-2">
                                        @if ($line['match'])
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" wire:click="confirmMatch({{ $line['match']['id'] }})" class="rounded-md bg-emerald-100 px-2 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-200">Confirm</button>
                                                <button type="button" wire:click="rejectMatch({{ $line['match']['id'] }})" class="rounded-md bg-rose-100 px-2 py-1 text-xs font-medium text-rose-800 hover:bg-rose-200">Reject</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-2 py-10 text-center text-slate-500">No reconciliation items waiting review.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Statement Imports</h3>
                    <div class="space-y-2">
                        @forelse ($imports as $import)
                            <button
                                type="button"
                                wire:click="setSelectedImport({{ $import['id'] }})"
                                class="w-full rounded-md border px-3 py-2 text-left {{ $import['is_selected'] ? 'border-[#004AAD] bg-blue-50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100' }}"
                            >
                                <p class="truncate text-sm font-medium text-slate-800">{{ $import['file_name'] }}</p>
                                <p class="text-xs text-slate-500">{{ $import['matched_lines'] }}/{{ $import['total_lines'] }} matched | {{ optional($import['created_at'])->format('M d, H:i') }}</p>
                            </button>
                        @empty
                            <p class="rounded-md border border-dashed border-slate-300 p-3 text-xs text-slate-500">No bank import files yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-600">Tax Mapper Rules</h3>
                    <div class="space-y-2">
                        @forelse ($taxRules as $rule)
                            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-sm font-medium text-slate-800">{{ $rule['name'] }}</p>
                                <p class="text-xs text-slate-500">Pattern: {{ $rule['pattern'] }}</p>
                                <p class="text-xs text-slate-500">Tax: {{ $rule['tax'] ?: 'N/A' }} @if($rule['rate_override']) | Override {{ $rule['rate_override'] }}%@endif</p>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-slate-300 p-3 text-xs text-slate-500">No active tax mapper rules configured.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
