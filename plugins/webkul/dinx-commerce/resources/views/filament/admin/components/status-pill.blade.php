@props([
    'label',
    'color' => 'slate',
    'indicator' => null,
])

<span class="dinx-status-pill {{ $color }}">
    @if ($indicator)
        <span class="mr-1">{{ $indicator }}</span>
    @endif

    {{ $label }}
</span>
