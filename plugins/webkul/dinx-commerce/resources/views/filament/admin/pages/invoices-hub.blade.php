<x-filament-panels::page>
    @php
        $money = $this->moneyBar;
        $invoices = $this->invoices;
        $activity = $this->invoiceActivity;
        $newInvoiceUrl = $this->newInvoiceUrl;
    @endphp

    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-slate-900">PayPal Hub</h2>
                <a href="{{ $newInvoiceUrl }}" class="rounded-md bg-[#004AAD] px-3 py-2 text-sm font-medium text-white hover:bg-[#003D8F]">+ New Invoice</a>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <button type="button" wire:click="setStatusFilter('all')" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-left hover:border-slate-300">
                    <p class="text-xs uppercase tracking-wide text-slate-500">Unbilled</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">${{ number_format($money['unbilled'] ?? 0, 2) }}</p>
                </button>
                <button type="button" wire:click="setStatusFilter('unpaid')" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-left hover:border-amber-300">
                    <p class="text-xs uppercase tracking-wide text-amber-700">Unpaid</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-800">${{ number_format($money['unpaid'] ?? 0, 2) }}</p>
                </button>
                <button type="button" wire:click="setStatusFilter('paid')" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-left hover:border-emerald-300">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">Paid (30d)</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-800">${{ number_format($money['paid'] ?? 0, 2) }}</p>
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search invoices or clients"
                    class="h-9 w-72 rounded-md border border-slate-300 px-3 text-sm"
                />
                <select wire:model.live="statusFilter" class="h-9 rounded-md border border-slate-300 px-3 text-sm">
                    <option value="all">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="overdue">Overdue</option>
                    <option value="paid">Paid</option>
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2">Invoice</th>
                            <th class="px-3 py-2">Client</th>
                            <th class="px-3 py-2">Issue</th>
                            <th class="px-3 py-2">Due</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr class="border-b border-slate-100 {{ $invoice['is_overdue'] ? 'bg-rose-50/50' : '' }}">
                                <td class="px-3 py-3 font-medium text-slate-900">{{ $invoice['name'] }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ $invoice['client'] }}</td>
                                <td class="px-3 py-3 text-slate-700">{{ optional($invoice['issue_date'])->format('M d, Y') }}</td>
                                <td class="px-3 py-3 text-slate-700">
                                    {{ optional($invoice['due_date'])->format('M d, Y') }}
                                    @if ($invoice['is_overdue'])
                                        <span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs font-semibold text-rose-700">Overdue</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-700">
                                    {{ $invoice['currency'] }} {{ number_format($invoice['amount_total'], 2) }}
                                    @if ($invoice['paypal_enabled'])
                                        <span class="ml-1 rounded bg-blue-100 px-1.5 py-0.5 text-xs font-semibold text-blue-700">PayPal</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $invoice['payment_state'] === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ strtoupper(str_replace('_', ' ', $invoice['payment_state'])) }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" wire:click="openInvoice({{ $invoice['id'] }})" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Open</button>
                                        <button type="button" wire:click="generatePayPalLink({{ $invoice['id'] }})" class="rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-medium text-white hover:bg-[#003D8F]">PayPal Link</button>
                                        <button type="button" wire:click="openLinkedContract({{ $invoice['id'] }})" class="rounded-md bg-indigo-100 px-2.5 py-1.5 text-xs font-medium text-indigo-800 hover:bg-indigo-200">Open Contract</button>
                                        <button type="button" wire:click="createRecurringProfile({{ $invoice['id'] }})" class="rounded-md bg-emerald-100 px-2.5 py-1.5 text-xs font-medium text-emerald-800 hover:bg-emerald-200">Recurring</button>
                                        <button type="button" wire:click="showActivity({{ $invoice['id'] }})" class="rounded-md bg-amber-100 px-2.5 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-200">Activity</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-10 text-center text-slate-500">No invoices found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($this->activityInvoiceId)
        <div class="fixed inset-y-0 right-0 z-30 w-full max-w-md border-l border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold text-slate-900">Invoice Activity</h3>
                <button type="button" wire:click="hideActivity" class="text-sm text-slate-500 hover:text-slate-700">Close</button>
            </div>
            <div class="max-h-[calc(100vh-56px)] overflow-y-auto p-4">
                <ol class="relative ml-3 border-l border-slate-200 pl-4">
                    @forelse ($activity as $event)
                        <li class="mb-5">
                            <span class="absolute -left-[7px] mt-1 h-3 w-3 rounded-full bg-[#004AAD]"></span>
                            <p class="text-sm font-semibold text-slate-900">{{ $event['label'] }}</p>
                            <p class="text-xs text-slate-500">{{ optional($event['time'])->format('M d, Y H:i') }}</p>
                            <p class="mt-1 text-xs text-slate-600">{{ $event['meta'] }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">No activity events yet.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    @endif
</x-filament-panels::page>
