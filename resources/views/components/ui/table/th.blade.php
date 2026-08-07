@props([
    'align' => 'left',
    'numeric' => false,
])

@php
    $alignment = $numeric ? 'text-right' : match ($align) {
        'right' => 'text-right',
        'center' => 'text-center',
        default => 'text-left',
    };
@endphp

<th scope="col" {{ $attributes->merge([
    'class' => 'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-600 '
        .'whitespace-nowrap bg-neutral-50 '.$alignment,
]) }}>
    {{ $slot }}
</th>
