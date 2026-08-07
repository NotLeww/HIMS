@props([
    'align' => 'left',
    'numeric' => false,
    'muted' => false,
])

@php
    $alignment = $numeric ? 'text-right tabular-nums' : match ($align) {
        'right' => 'text-right',
        'center' => 'text-center',
        default => 'text-left',
    };
@endphp

<td {{ $attributes->merge([
    'class' => 'px-4 py-3 align-middle '
        .($muted ? 'text-neutral-500' : 'text-neutral-800').' '.$alignment,
]) }}>
    {{ $slot }}
</td>
