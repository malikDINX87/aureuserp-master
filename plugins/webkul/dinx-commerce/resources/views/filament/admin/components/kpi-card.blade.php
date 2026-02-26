@props([
    'label',
    'value',
    'prefix' => '',
    'suffix' => '',
    'tone' => 'default',
])

@php
    $toneClass = match ($tone) {
        'positive' => 'text-emerald-700',
        'negative' => 'text-rose-700',
        'warning' => 'text-amber-700',
        default => 'text-slate-900',
    };
@endphp

<div class="dinx-kpi-card">
    <p class="dinx-kpi-label">{{ $label }}</p>
    <p class="dinx-kpi-value {{ $toneClass }}">
        {{ $prefix }}{{ $value }}{{ $suffix }}
    </p>
</div>
