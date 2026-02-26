<x-filament-panels::page>
    @php
        $counts = $this->pipelineCounts;
        $contracts = $this->contracts;
        $versions = $this->versionHistory;
    @endphp

    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">DocuSign Hub</h2>
                <a href="/admin/dinx-contracts/create" class="rounded-md bg-[#004AAD] px-3 py-2 text-sm font-medium text-white hover:bg-[#003D8F]">+ New Contract</a>
            </div>

            <div class="grid gap-2 md:grid-cols-6">
                @foreach (['draft' => 'Draft', 'sent' => 'Sent', 'viewed' => 'Viewed', 'signed' => 'Signed', 'expired' => 'Expired'] as $key => $label)
                    <button
                        type="button"
                        wire:click="setPipeline('{{ $key }}')"
                        class="rounded-lg border px-3 py-2 text-left transition
                            {{ $this->pipeline === $key ? 'border-[#004AAD] bg-[#004AAD] text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-300' }}"
                    >
                        <p class="text-xs uppercase tracking-wide opacity-80">{{ $label }}</p>
                        <p class="text-xl font-semibold">{{ $counts[$key] ?? 0 }}</p>
                    </button>
                @endforeach
                <button
                    type="button"
                    wire:click="setPipeline('all')"
                    class="rounded-lg border px-3 py-2 text-left transition {{ $this->pipeline === 'all' ? 'border-[#004AAD] bg-[#004AAD] text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-300' }}"
                >
                    <p class="text-xs uppercase tracking-wide opacity-80">All</p>
                    <p class="text-xl font-semibold">{{ array_sum($counts) }}</p>
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search contracts or clients"
                    class="h-9 w-72 rounded-md border border-slate-300 px-3 text-sm"
                />
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="px-3 py-2">Contract</th>
                            <th class="px-3 py-2">Client</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Value</th>
                            <th class="px-3 py-2">Expiry</th>
                            <th class="px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contracts as $contract)
                            <tr class="border-b border-slate-100 {{ $contract['is_expired'] ? 'bg-rose-50/50' : '' }}">
                                <td class="px-3 py-3">
                                    <p class="font-medium text-slate-900">{{ $contract['title'] }}</p>
                                    <button type="button" wire:click="openVersionDrawer({{ $contract['id'] }})" class="mt-1 text-xs text-[#004AAD] hover:underline">Version history</button>
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $contract['client'] }}</td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ $contract['status'] === 'draft' ? 'bg-slate-100 text-slate-700' : '' }}
                                        {{ $contract['status'] === 'sent' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $contract['status'] === 'viewed' ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $contract['status'] === 'signed' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                    ">
                                        {{ strtoupper($contract['status']) }}
                                        @if ($contract['status_icon'] === 'eye')
                                            <span class="ml-1">[VIEWED]</span>
                                        @elseif ($contract['status_icon'] === 'pen')
                                            <span class="ml-1">[SIGNED]</span>
                                        @endif
                                    </span>
                                    @if ($contract['last_event_message'])
                                        <p class="mt-1 text-xs text-slate-500">{{ $contract['last_event_message'] }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-slate-700">{{ $contract['currency'] }} {{ number_format($contract['amount'], 2) }}</td>
                                <td class="px-3 py-3">
                                    @if ($contract['expiration_date'])
                                        <span class="{{ $contract['is_expiring_soon'] ? 'font-semibold text-amber-700' : 'text-slate-700' }}">{{ $contract['expiration_date']->format('M d, Y') }}</span>
                                    @else
                                        <span class="text-slate-400">N/A</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        @if ($contract['primary_action'] === 'send')
                                            <button type="button" wire:click="sendViaDocuSign({{ $contract['id'] }})" class="rounded-md bg-[#004AAD] px-2.5 py-1.5 text-xs font-medium text-white hover:bg-[#003D8F]">Send via DocuSign</button>
                                        @elseif ($contract['primary_action'] === 'remind')
                                            <button type="button" wire:click="remindClient({{ $contract['id'] }})" class="rounded-md bg-amber-100 px-2.5 py-1.5 text-xs font-medium text-amber-800 hover:bg-amber-200">Remind Client</button>
                                        @elseif ($contract['primary_action'] === 'view')
                                            <button type="button" wire:click="openSignedPdf({{ $contract['id'] }})" class="rounded-md bg-emerald-100 px-2.5 py-1.5 text-xs font-medium text-emerald-800 hover:bg-emerald-200">View Signed PDF</button>
                                        @else
                                            <button type="button" wire:click="sendRenewal({{ $contract['id'] }})" class="rounded-md bg-rose-100 px-2.5 py-1.5 text-xs font-medium text-rose-800 hover:bg-rose-200">Send Renewal</button>
                                        @endif

                                        <a href="/admin/dinx-contracts/{{ $contract['id'] }}" class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Open</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-10 text-center text-slate-500">No contracts found for this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($this->versionContractId)
        <div class="fixed inset-y-0 right-0 z-30 w-full max-w-md border-l border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h3 class="font-semibold text-slate-900">Version History</h3>
                <button type="button" wire:click="closeVersionDrawer" class="text-sm text-slate-500 hover:text-slate-700">Close</button>
            </div>
            <div class="max-h-[calc(100vh-56px)] overflow-y-auto p-4">
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
                        <p class="text-sm text-slate-500">No saved versions yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
