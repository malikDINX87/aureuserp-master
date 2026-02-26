@props([
    'title',
    'subtitle' => null,
])

<div class="dinx-hero">
    <div class="dinx-toolbar">
        <div>
            <h1 class="dinx-hero-title">{{ $title }}</h1>

            @if ($subtitle)
                <p class="dinx-hero-subtitle">{{ $subtitle }}</p>
            @endif
        </div>

        @if (filled(trim((string) $slot)))
            <div class="flex flex-wrap items-center gap-2">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
