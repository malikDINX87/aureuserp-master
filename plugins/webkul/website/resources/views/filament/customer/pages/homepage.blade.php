<x-filament-panels::page class="fi-home-page">
    <div class="mb-4 flex items-center gap-3">
        <img src="{{ asset('images/dinx-logo.png') }}" alt="DINX" class="h-9 w-9 rounded-lg" />
        <span class="text-lg font-semibold">DINX ERP</span>
    </div>
    {!! str($this->getContent())->sanitizeHtml() !!}
</x-filament-panels::page>
