@props([
    'label' => '',
    'value' => '—',
    'icon' => null,
    'tone' => 'neutral',
    'hint' => null,
    'summary' => null,
    'details' => [],
    'summaryTitle' => null,
    'href' => null,
    'compact' => false,
    'context' => null,
    'sparkline' => [],
    'sparklineLabel' => null,
])

@php
    $tones = [
        'neutral' => [
            'label' => 'text-neutral-700 dark:text-neutral-300',
            'icon' => 'bg-neutral-100 text-neutral-700 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-700',
            'value' => 'text-neutral-950 dark:text-white',
            'chart' => 'text-neutral-400 dark:text-neutral-500',
        ],
        'primary' => [
            'label' => 'text-primary-700 dark:text-primary-300',
            'icon' => 'bg-primary-100 text-primary-700 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/50',
            'value' => 'text-neutral-950 dark:text-white',
            'chart' => 'text-primary-500 dark:text-primary-400',
        ],
        'success' => [
            'label' => 'text-emerald-700 dark:text-emerald-300',
            'icon' => 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/80 dark:text-emerald-300 dark:ring-emerald-800/50',
            'value' => 'text-neutral-950 dark:text-white',
            'chart' => 'text-emerald-500 dark:text-emerald-400',
        ],
        'warning' => [
            'label' => 'text-amber-700 dark:text-amber-300',
            'icon' => 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-950/80 dark:text-amber-300 dark:ring-amber-800/50',
            'value' => 'text-amber-600 dark:text-amber-400',
            'chart' => 'text-amber-500 dark:text-amber-400',
        ],
        'danger' => [
            'label' => 'text-rose-700 dark:text-rose-300',
            'icon' => 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-950/80 dark:text-rose-300 dark:ring-rose-800/50',
            'value' => 'text-rose-600 dark:text-rose-400',
            'chart' => 'text-rose-500 dark:text-rose-400',
        ],
    ];
    $tag = $href ? 'a' : 'div';
    $tooltipDetails = collect($details)->filter(fn ($detail) => is_string($detail) && trim($detail) !== '')->values()->all();
    $sparklineValues = collect($sparkline)
        ->filter(fn ($point) => is_numeric($point))
        ->map(fn ($point) => (float) $point)
        ->values();

    if ($sparklineValues->count() === 1) {
        $sparklineValues = collect(array_fill(0, 7, $sparklineValues->first()));
    }

    $sparklinePoints = null;
    if ($sparklineValues->count() > 1) {
        $minimum = (float) $sparklineValues->min();
        $maximum = (float) $sparklineValues->max();
        $range = $maximum - $minimum;
        $lastIndex = $sparklineValues->count() - 1;

        $sparklinePoints = $sparklineValues
            ->map(function (float $point, int $index) use ($minimum, $range, $lastIndex): string {
                $x = ($index / $lastIndex) * 100;
                $y = $range > 0 ? 22 - ((($point - $minimum) / $range) * 16) : 14;

                return round($x, 2).','.round($y, 2);
            })
            ->implode(' ');
    }
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @if ($summary) data-metric-summary="{{ $summary }}" @endif
    @if ($tooltipDetails) data-metric-details="{{ json_encode($tooltipDetails) }}" @endif
    @if ($summaryTitle) data-metric-title="{{ $summaryTitle }}" @endif
    {{ $attributes->merge([
        'class' => 'group relative flex min-w-0 flex-col justify-between rounded-xl border border-neutral-200/90 bg-white shadow-xs transition-[box-shadow,border-color,background-color] motion-safe:duration-150 dark:border-neutral-800 dark:bg-neutral-900/95 '
            .($compact ? 'p-4 sm:p-5' : 'p-5')
            .($href
                ? ' cursor-pointer hover:border-primary-300 hover:shadow-sm active:bg-neutral-50 focus-visible:z-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:hover:border-primary-700 dark:active:bg-neutral-800/80 dark:focus-visible:ring-offset-neutral-950'
                : ' hover:border-neutral-300 dark:hover:border-neutral-700'),
    ]) }}
>
    <div>
        <div class="flex items-center {{ $compact ? 'gap-2.5' : 'justify-between gap-2' }}">
            @if ($icon)
                <span class="flex {{ $compact ? 'h-9 w-9 rounded-lg' : 'h-10 w-10 rounded-xl' }} shrink-0 items-center justify-center ring-1 {{ $tones[$tone]['icon'] }}">
                    <x-ui.icon :name="$icon" class="{{ $compact ? 'h-4 w-4' : 'h-5 w-5' }}" />
                </span>
            @endif
            <p class="min-w-0 text-xs font-bold uppercase tracking-wider {{ $compact ? '' : 'sm:text-sm' }} {{ $tones[$tone]['label'] }}">{{ $label }}</p>
        </div>

        <div class="mt-3 flex min-w-0 items-end justify-between gap-3">
            <p class="min-w-0 break-words font-black tracking-tight tabular-nums {{ $compact ? 'text-2xl sm:text-3xl' : 'text-3xl sm:text-4xl lg:text-5xl' }} {{ $tones[$tone]['value'] }}">{{ $value }}</p>
            @if ($compact && $context)
                <span class="mb-1 shrink-0 text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">{{ $context }}</span>
            @endif
        </div>

        @if ($compact && $sparklinePoints)
            <div class="mt-2 h-7 w-full {{ $tones[$tone]['chart'] }}" role="img" aria-label="{{ $sparklineLabel ?? $label.' distribution profile' }}">
                <svg viewBox="0 0 100 28" preserveAspectRatio="none" class="h-full w-full overflow-visible" aria-hidden="true">
                    <polygon points="0,28 {{ $sparklinePoints }} 100,28" fill="currentColor" opacity="0.10" />
                    <polyline points="{{ $sparklinePoints }}" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </div>
        @endif
    </div>

    @if ($hint)
        <div class="{{ $compact ? 'mt-2.5 pt-2' : 'mt-3.5 pt-2.5' }} flex items-center border-t border-neutral-100 dark:border-neutral-800/80">
            <p class="{{ $compact ? 'line-clamp-2' : 'truncate' }} text-xs font-medium leading-snug text-neutral-600 dark:text-neutral-300 sm:text-sm">{{ $hint }}</p>
        </div>
    @endif

</{{ $tag }}>
