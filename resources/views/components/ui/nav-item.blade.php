@props([
    'href' => '#',
    'icon' => null,
    'active' => false,
    'badge' => null,
    'disabled' => false,
    'sub' => false,
])

@php
    $classes = $sub
        ? 'group flex min-h-8 items-center gap-2 px-2.5 py-1.5 rounded-lg text-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 '
        : 'group flex min-h-11 items-center gap-3 px-3 py-2 rounded-xl text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 ';

    $state = match (true) {
        $disabled => 'text-emerald-100/35 cursor-not-allowed',
        (bool) $active => $sub
            ? 'bg-emerald-400/15 text-white font-semibold shadow-sm ring-1 ring-inset ring-emerald-300/15'
            : 'bg-emerald-400/20 text-white font-semibold shadow-lg ring-1 ring-inset ring-emerald-300/20',
        default => 'text-emerald-50/80 font-medium hover:bg-white/10 hover:text-white',
    };
@endphp

<a
    href="{{ $disabled ? '#' : $href }}"
    @if ($disabled) aria-disabled="true" tabindex="-1" @endif
    @if ($active) aria-current="page" @endif
    {{ $attributes->merge(['class' => $classes.' '.$state]) }}
>
    @if ($icon)
        <x-ui.icon
            :name="$icon"
            class="{{ $sub ? 'w-4 h-4' : 'w-[19px] h-[19px]' }} shrink-0 {{ $active ? 'text-emerald-300' : 'text-emerald-100/65 group-hover:text-emerald-200' }}"
        />
    @elseif ($sub)
        <span class="w-1.5 h-1.5 rounded-full shrink-0 transition-colors {{ $active ? 'bg-emerald-300 ring-2 ring-emerald-300/25' : 'bg-emerald-100/35 group-hover:bg-emerald-200/70' }}"></span>
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if ($disabled)
        <span class="text-[10px] font-medium uppercase tracking-wide text-emerald-100/40">Soon</span>
    @elseif ($badge)
        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold tabular-nums
                     {{ $active ? 'bg-emerald-300/20 text-emerald-100' : 'bg-white/10 text-emerald-100/75' }}">
            {{ $badge }}
        </span>
    @endif
</a>
