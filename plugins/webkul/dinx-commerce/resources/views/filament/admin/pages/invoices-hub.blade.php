<x-filament-panels::page>
    @php
        $money = $this->moneyBar;
        $invoices = $this->invoices;
        $activity = $this->invoiceActivity;
        $newInvoiceUrl = $this->newInvoiceUrl;
    @endphp

    <div class="dinx-shell">
        <x-dinx-commerce::filament.admin.components.workspace-hero
            title="Invoices"
            subtitle="PayPal-first billing workspace with collection status, reminders, and payment timeline."
        >
            <a href="{{ $newInvoiceUrl }}" class="dinx-focus-ring rounded-md bg-[#004AAD] px-3 py-2 text-xs font-semibold text-white hover:bg-[#003D8F]">
                + New Invoice
            </a>
        </x-dinx-commerce::filament.admin.components.workspace-hero>

        <div class="dinx-card">
            <div class="dinx-card-body space-y-4">
                <div class="dinx-money-bar">
                    <button type="button" wire:click="setStatusFilter('all')" class="dinx-bar-item dinx-focus-ring text-left">
                        <x-dinx-commerce::filament.admin.components.status-pill label="Unbilled" color="slate" />
                        <p class="value text-slate-900">${{ number_format($money['unbilled'] ?? 0, 2) }}</p>
                    </button>
                    <button type="button" wire:click="setStatusFilter('unpaid')" class="dinx-bar-item dinx-focus-ring text-left">
                        <x-dinx-commerce::filament.admin.components.status-pill label="Unpaid" color="amber" />
                        <p class="value text-amber-800">${{ number_format($money['unpaid'] ?? 0, 2) }}</p>
                    </button>
                    <button type="button" wire:click="setStatusFilter('paid')" class="dinx-bar-item dinx-focus-ring text-left">
                        <x-dinx-commerce::filament.admin.components.status-pill label="Paid (30d)" color="green" />
                        <p class="value text-emerald-800">${{ number_format($money['paid'] ?? 0, 2) }}</p>
                    </button>
                </div>

                <x-dinx-commerce::filament.admin.components.action-toolbar>
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search invoices or clients"
                            class="dinx-focus-ring h-9 w-72 rounded-md border border-slate-300 px-3 text-sm"
                        />
                        <select wire:model.live="statusFilter" class="dinx-focus-ring h-9 rounded-md border border-slate-300 px-3 text-sm">
                            <option value="all">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="overdue">Overdue</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                </x-dinx-commerce::filament.admin.components.action-toolbar>

                <x-dinx-commerce::filament.admin.components.table-shell minWidth="1120px">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th>Issue</th>
                            <th>Due</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr class="{{ $invoice['is_overdue'] ? 'is-overdue' : '' }}">
                                <td class="font-semibold text-slate-900">{{ $invoice['name'] }}</td>
                                <td>{{ $invoice['client'] }}</td>
                                <td>{{ optional($invoice['issue_date'])->format('M d, Y') }}</td>
                                <td>
                                    {{ optional($invoice['due_date'])->format('M d, Y') }}
                                    @if ($invoice['is_overdue'])
                                        <span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs font-semibold text-rose-700">Overdue</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $invoice['currency'] }} {{ number_format($invoice['amount_total'], 2) }}
                                    @if ($invoice['paypal_enabled'])
                                        <span class="ml-1 rounded bg-blue-100 px-1.5 py-0.5 text-xs font-semibold text-blue-700">PayPal</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $paymentColor = $invoice['payment_state'] === 'paid'
                                            ? 'green'
                                            : ($invoice['payment_state'] === 'not_paid' && $invoice['is_overdue'] ? 'rose' : 'amber');
                                    @endphp
                                    <x-dinx-commerce::filament.admin.components.status-pill
                                        :label="strtoupper(str_replace('_', ' ', $invoice['payment_state']))"
                                        :color="$paymentColor"
                                    />
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" wire:click="openInvoice({{ $invoice['id'] }})" class="dinx-focus-ring rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Open</button>
                                        <button type="button" wire:click="generatePayPalLink({{ $invoice['id'] }})" class="dinx-focus-ring rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-[#003D8F]">PayPal Link</button>
                                        <button type="button" wire:click="openLinkedContract({{ $invoice['id'] }})" class="dinx-focus-ring rounded-md bg-indigo-100 px-2.5 py-1.5 text-xs font-semibold text-indigo-800 hover:bg-indigo-200">Open Contract</button>
                                        <button type="button" wire:click="createRecurringProfile({{ $invoice['id'] }})" class="dinx-focus-ring rounded-md bg-emerald-100 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-200">Recurring</button>
                                        <button type="button" wire:click="showActivity({{ $invoice['id'] }})" class="dinx-focus-ring rounded-md bg-amber-100 px-2.5 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-200">Activity</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-dinx-commerce::filament.admin.components.empty-state message="No invoices found." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-dinx-commerce::filament.admin.components.table-shell>
            </div>
        </div>
    </div>

    <x-dinx-commerce::filament.admin.components.right-drawer
        title="Invoice Activity Timeline"
        :open="$this->activityInvoiceId !== null"
        closeAction="hideActivity"
    >
        <ol class="relative ml-3 border-l border-slate-200 pl-4">
            @forelse ($activity as $event)
                <li class="mb-5">
                    <span class="absolute -left-[7px] mt-1 h-3 w-3 rounded-full bg-[#004AAD]"></span>
                    <p class="text-sm font-semibold text-slate-900">{{ $event['label'] }}</p>
                    <p class="text-xs text-slate-500">{{ optional($event['time'])->format('M d, Y H:i') }}</p>
                    <p class="mt-1 text-xs text-slate-600">{{ $event['meta'] }}</p>
                </li>
            @empty
                <x-dinx-commerce::filament.admin.components.empty-state message="No activity events yet." />
            @endforelse
        </ol>
    </x-dinx-commerce::filament.admin.components.right-drawer>
</x-filament-panels::page>
