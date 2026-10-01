@props([
    'label' => '',
    'value' => '—',
    'icon' => null,
    'tone' => 'neutral',
    'hint' => null,
    'summary' => null,
    'href' => null,
    'compact' => false,
])

@php
    $tones = [
        'neutral' => [
            'label' => 'text-neutral-700 dark:text-neutral-300',
            'icon' => 'bg-neutral-100 text-neutral-700 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-700',
            'value' => 'text-neutral-950 dark:text-white',
        ],
        'primary' => [
            'label' => 'text-primary-700 dark:text-primary-300',
            'icon' => 'bg-primary-100 text-primary-700 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/50',
            'value' => 'text-neutral-950 dark:text-white',
        ],
        'success' => [
            'label' => 'text-emerald-700 dark:text-emerald-300',
            'icon' => 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/80 dark:text-emerald-300 dark:ring-emerald-800/50',
            'value' => 'text-neutral-950 dark:text-white',
        ],
        'warning' => [
            'label' => 'text-amber-700 dark:text-amber-300',
            'icon' => 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-950/80 dark:text-amber-300 dark:ring-amber-800/50',
            'value' => 'text-amber-600 dark:text-amber-400',
        ],
        'danger' => [
            'label' => 'text-rose-700 dark:text-rose-300',
            'icon' => 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-950/80 dark:text-rose-300 dark:ring-rose-800/50',
            'value' => 'text-rose-600 dark:text-rose-400',
        ],
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @if ($summary)
        x-data="statTooltip"
        x-id="['stat-tooltip']"
        x-bind:aria-describedby="$id('stat-tooltip')"
        x-on:pointerenter="showTooltip($event)"
        x-on:pointermove="followPointer($event)"
        x-on:pointerleave="hideTooltip()"
        x-on:focusin="if ($el.matches(':focus-visible')) showForFocus()"
        x-on:focusout="hideTooltip()"
    @endif
    {{ $attributes->merge([
        'class' => 'group relative flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white shadow-xs transition-[box-shadow,border-color] motion-safe:duration-150 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/95 dark:hover:border-neutral-700 '
            .($compact ? 'p-4 sm:p-5' : 'p-5')
            .($href ? ' focus-visible:z-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-950' : ''),
    ]) }}
>
    <div>
        <div class="flex items-center justify-between gap-2">
            <p class="min-w-0 text-xs font-bold uppercase tracking-wider sm:text-sm {{ $tones[$tone]['label'] }}">{{ $label }}</p>
            @if ($icon)
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 {{ $tones[$tone]['icon'] }}">
                    <x-ui.icon :name="$icon" class="h-5 w-5" />
                </span>
            @endif
        </div>

        <p class="mt-3 text-3xl font-black tracking-tight tabular-nums sm:text-4xl lg:text-5xl {{ $tones[$tone]['value'] }}">{{ $value }}</p>
    </div>

    @if ($hint)
        <div class="mt-3.5 flex items-center border-t border-neutral-100 pt-2.5 dark:border-neutral-800/80">
            <p class="truncate text-xs font-medium text-neutral-600 dark:text-neutral-300 sm:text-sm">{{ $hint }}</p>
        </div>
    @endif

    @if ($summary)
        <template x-teleport="body">
            <div
                x-ref="tooltip"
                x-bind:id="$id('stat-tooltip')"
                x-show="open"
                x-cloak
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                x-bind:style="position"
                role="tooltip"
                class="pointer-events-none fixed z-[70] w-64 rounded-lg border border-neutral-200/90 bg-white/95 p-3 text-left shadow-lg backdrop-blur-xs dark:border-neutral-700/80 dark:bg-neutral-900/95 dark:shadow-2xl"
            >
                <p class="text-[10px] font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">Summary</p>
                <p class="mt-1 text-xs leading-relaxed text-neutral-700 dark:text-neutral-200">{{ $summary }}</p>
            </div>
        </template>
    @endif
</{{ $tag }}>
