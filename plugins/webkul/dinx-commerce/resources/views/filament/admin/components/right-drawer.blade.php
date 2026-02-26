@props([
    'title' => 'Details',
    'open' => false,
    'closeAction' => null,
])

@if ($open)
    <div class="dinx-drawer" role="dialog" aria-modal="true">
        <div class="dinx-drawer-header">
            <h3 class="text-sm font-semibold text-slate-900">{{ $title }}</h3>
            <button
                type="button"
                class="dinx-focus-ring rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                @if ($closeAction) wire:click="{{ $closeAction }}" @endif
            >
                Close
            </button>
        </div>

        <div class="dinx-drawer-body">
            {{ $slot }}
        </div>
    </div>
@endif
