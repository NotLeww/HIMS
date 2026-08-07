@props(['sticky' => true])

<thead {{ $attributes->merge([
    'class' => 'bg-neutral-50 '.($sticky ? 'sticky top-0 z-10' : ''),
]) }}>
    <tr class="border-b border-neutral-200">
        {{ $slot }}
    </tr>
</thead>
