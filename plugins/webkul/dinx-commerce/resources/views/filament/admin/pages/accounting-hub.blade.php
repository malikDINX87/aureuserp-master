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

    <div class="dinx-shell">
        <x-dinx-commerce::filament.admin.components.workspace-hero
            title="Accounting"
            subtitle="General ledger split view, reconciliation workflow, and tax mapping controls."
        >
            <button type="button" wire:click="openJournalEntryForm" class="dinx-focus-ring rounded-md bg-[#004AAD] px-3 py-2 text-xs font-semibold text-white hover:bg-[#003D8F]">
                + New Journal Entry
            </button>
            <button type="button" wire:click="openCoaSettings" class="dinx-focus-ring rounded-md bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200">
                COA Settings
            </button>
            <button type="button" wire:click="openTaxSettings" class="dinx-focus-ring rounded-md bg-indigo-100 px-3 py-2 text-xs font-semibold text-indigo-800 hover:bg-indigo-200">
                Tax Mapper
            </button>
        </x-dinx-commerce::filament.admin.components.workspace-hero>

        <div class="grid gap-4 xl:grid-cols-[320px_minmax(0,1fr)]">
            <div class="dinx-card">
                <div class="dinx-card-body space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Chart of Accounts</p>

                    @forelse ($sections as $section)
                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $section['label'] }}</p>
                            <div class="space-y-1">
                                @foreach ($section['accounts'] as $account)
                                    <button
                                        type="button"
                                        wire:click="selectAccount({{ $account['id'] }})"
                                        class="dinx-focus-ring flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-sm {{ $account['is_selected'] ? 'bg-[#004AAD] text-white' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' }}"
                                    >
                                        <span class="truncate">{{ trim(($account['code'] ? $account['code'].' ' : '').$account['name']) }}</span>
                                        <span class="ml-3 whitespace-nowrap text-xs font-semibold {{ $account['is_selected'] ? 'text-white/90' : 'text-slate-500' }}">
                                            {{ number_format($account['balance'], 2) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <x-dinx-commerce::filament.admin.components.empty-state message="No chart of accounts data available yet." />
                    @endforelse
                </div>
            </div>

            <div class="dinx-card">
                <div class="dinx-card-body space-y-4">
                    <x-dinx-commerce::filament.admin.components.action-toolbar>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">
                                Account Register
                                @if ($selectedAccount)
                                    <span class="text-slate-500">/ {{ trim(($selectedAccount->code ? $selectedAccount->code.' ' : '').$selectedAccount->name) }}</span>
                                @endif
                            </h3>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <input type="search" wire:model.live.debounce.300ms="registerSearch" placeholder="Search register" class="dinx-focus-ring h-9 w-56 rounded-md border border-slate-300 px-3 text-sm" />
                            <input type="date" wire:model.live="dateFrom" class="dinx-focus-ring h-9 rounded-md border border-slate-300 px-2 text-sm" />
                            <input type="date" wire:model.live="dateTo" class="dinx-focus-ring h-9 rounded-md border border-slate-300 px-2 text-sm" />
                        </div>
                    </x-dinx-commerce::filament.admin.components.action-toolbar>

                    <x-dinx-commerce::filament.admin.components.table-shell minWidth="1000px">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Payee</th>
                                <th>Memo / Split</th>
                                <th>Payment (Dr)</th>
                                <th>Deposit (Cr)</th>
                                <th>Balance</th>
                                <th>Stat</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($register as $row)
                                <tr>
                                    <td>{{ optional($row['date'])->format('M d, Y') }}</td>
                                    <td>{{ $row['type'] }}</td>
                                    <td>{{ $row['payee'] }}</td>
                                    <td>{{ $row['memo'] }}</td>
                                    <td>{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                                    <td>{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
                                    <td>{{ number_format($row['balance'], 2) }}</td>
                                    <td>
                                        @if ($row['status'])
                                            <x-dinx-commerce::filament.admin.components.status-pill :label="$row['status']" color="slate" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <x-dinx-commerce::filament.admin.components.empty-state message="No register entries for this account/filter." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-dinx-commerce::filament.admin.components.table-shell>
                </div>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="dinx-card">
                <div class="dinx-card-body space-y-4">
                    <x-dinx-commerce::filament.admin.components.action-toolbar>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Reconciliation Tool</h3>
                            <p class="text-sm text-slate-500">Upload statement lines and confirm suggested matches.</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <input type="file" wire:model="statementUpload" accept=".csv,text/csv" class="text-xs" />
                            <button type="button" wire:click="importBankCsv" class="dinx-focus-ring rounded-md bg-[#004AAD] px-3 py-2 text-xs font-semibold text-white hover:bg-[#003D8F]">
                                Import Bank Data
                            </button>
                        </div>
                    </x-dinx-commerce::filament.admin.components.action-toolbar>

                    @if ($activeImport)
                        <p class="text-xs text-slate-500">
                            Active statement: <span class="font-semibold text-slate-700">{{ $activeImport->file_name }}</span>
                            ({{ $activeImport->matched_lines }}/{{ $activeImport->total_lines }} reconciled)
                        </p>
                    @endif

                    <x-dinx-commerce::filament.admin.components.table-shell minWidth="980px">
                        <thead>
                            <tr>
                                <th>Line</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Suggested Account</th>
                                <th>Suggested Match</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($queue as $line)
                                <tr>
                                    <td>#{{ $line['line_number'] }}</td>
                                    <td>{{ optional($line['transaction_date'])->format('M d, Y') }}</td>
                                    <td>{{ $line['description'] }}</td>
                                    <td>{{ number_format($line['amount'], 2) }}</td>
                                    <td>{{ $line['suggested_account'] ?: 'N/A' }}</td>
                                    <td>
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
                                    <td>
                                        @if ($line['match'])
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" wire:click="confirmMatch({{ $line['match']['id'] }})" class="dinx-focus-ring rounded-md bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800 hover:bg-emerald-200">Confirm</button>
                                                <button type="button" wire:click="rejectMatch({{ $line['match']['id'] }})" class="dinx-focus-ring rounded-md bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-800 hover:bg-rose-200">Reject</button>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">
                                        <x-dinx-commerce::filament.admin.components.empty-state message="No reconciliation items waiting review." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-dinx-commerce::filament.admin.components.table-shell>
                </div>
            </div>

            <div class="space-y-4">
                <div class="dinx-card">
                    <div class="dinx-card-body space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Statement Imports</p>
                        @forelse ($imports as $import)
                            <button
                                type="button"
                                wire:click="setSelectedImport({{ $import['id'] }})"
                                class="dinx-focus-ring w-full rounded-md border px-3 py-2 text-left {{ $import['is_selected'] ? 'border-[#004AAD] bg-blue-50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100' }}"
                            >
                                <p class="truncate text-sm font-semibold text-slate-800">{{ $import['file_name'] }}</p>
                                <p class="text-xs text-slate-500">{{ $import['matched_lines'] }}/{{ $import['total_lines'] }} matched | {{ optional($import['created_at'])->format('M d, H:i') }}</p>
                            </button>
                        @empty
                            <x-dinx-commerce::filament.admin.components.empty-state message="No bank import files yet." />
                        @endforelse
                    </div>
                </div>

                <div class="dinx-card">
                    <div class="dinx-card-body space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tax Mapper Rules</p>
                        @forelse ($taxRules as $rule)
                            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                                <p class="text-sm font-semibold text-slate-800">{{ $rule['name'] }}</p>
                                <p class="text-xs text-slate-500">Pattern: {{ $rule['pattern'] }}</p>
                                <p class="text-xs text-slate-500">Tax: {{ $rule['tax'] ?: 'N/A' }} @if($rule['rate_override']) | Override {{ $rule['rate_override'] }}%@endif</p>
                            </div>
                        @empty
                            <x-dinx-commerce::filament.admin.components.empty-state message="No active tax mapper rules configured." />
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
