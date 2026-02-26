@props([
    'minWidth' => '980px',
])

<div class="dinx-table-shell">
    <div class="dinx-table-scroll">
        <table class="dinx-table" style="min-width: {{ $minWidth }};">
            {{ $slot }}
        </table>
    </div>
</div>
