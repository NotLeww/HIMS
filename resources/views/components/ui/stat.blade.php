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
    'prefix' => null,
    'suffix' => null,
    'breakdown' => [],
    'featured' => false,
    'chart' => [],
    'chartLabel' => null,
    'chartType' => 'segments',
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
    $tooltipDetails = collect($details)->filter(fn ($detail) => is_string($detail) && trim($detail) !== '')->values()->all();
    $breakdownItems = collect($breakdown)
        ->filter(fn ($item) => is_array($item) && isset($item['label'], $item['value']))
        ->take($featured ? 3 : 2)
        ->values();
    $chartItems = collect($chart)
        ->filter(fn ($item) => is_array($item) && isset($item['label'], $item['value']) && is_numeric($item['value']))
        ->map(fn ($item) => [
            'label' => (string) $item['label'],
            'value' => max(0, (float) $item['value']),
            'tone' => $item['tone'] ?? 'primary',
        ])
        ->values();
    $chartTotal = (float) $chartItems->sum('value');
    $chartMaximum = max(1, (float) $chartItems->max('value'));
    $chartTones = [
        'neutral' => 'bg-neutral-400 dark:bg-neutral-500',
        'primary' => 'bg-primary-500 dark:bg-primary-400',
        'success' => 'bg-emerald-500 dark:bg-emerald-400',
        'warning' => 'bg-amber-500 dark:bg-amber-400',
        'danger' => 'bg-rose-500 dark:bg-rose-400',
    ];
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @if ($summary) data-metric-summary="{{ $summary }}" @endif
    @if ($tooltipDetails) data-metric-details="{{ json_encode($tooltipDetails) }}" @endif
    @if ($summaryTitle) data-metric-title="{{ $summaryTitle }}" @endif
    {{ $attributes->merge([
        'class' => 'group relative flex min-w-0 flex-col justify-between rounded-xl border bg-white shadow-xs transition-[box-shadow,border-color,background-color] motion-safe:duration-150 dark:bg-neutral-900/95 '
            .($featured ? 'border-neutral-300 dark:border-neutral-700 ' : 'border-neutral-200/90 dark:border-neutral-800 ')
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
            <p class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 break-words font-black tracking-tight tabular-nums {{ $featured ? 'text-3xl sm:text-4xl' : ($compact ? 'text-2xl sm:text-3xl' : 'text-3xl sm:text-4xl lg:text-5xl') }} {{ $tones[$tone]['value'] }}">
                @if ($prefix)
                    <span class="text-base font-bold tracking-normal {{ $tones[$tone]['label'] }} sm:text-lg">{{ $prefix }}</span>
                @endif
                <span>{{ $value }}</span>
                @if ($suffix)
                    <span class="text-xs font-bold tracking-normal text-neutral-500 dark:text-neutral-400 sm:text-sm">{{ $suffix }}</span>
                @endif
            </p>
            @if ($compact && $context)
                <span class="mb-1 shrink-0 text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">{{ $context }}</span>
            @endif
        </div>

        @if ($chartItems->isNotEmpty())
            <div class="mt-3" role="img" aria-label="{{ $chartLabel ?? $label.' comparison' }}">
                @if ($chartLabel)
                    <p class="mb-1.5 text-[10px] font-bold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $chartLabel }}</p>
                @endif

                @if ($chartType === 'bars')
                    <div class="grid h-12 items-end gap-1.5" style="grid-template-columns: repeat({{ $chartItems->count() }}, minmax(0, 1fr));">
                        @foreach ($chartItems as $item)
                            <div class="flex min-w-0 flex-col justify-end gap-1" title="{{ $item['label'] }}: {{ number_format($item['value'], 2) }}">
                                <div class="flex h-8 items-end overflow-hidden rounded-sm bg-neutral-100 dark:bg-neutral-800">
                                    <span
                                        class="block w-full rounded-sm {{ $chartTones[$item['tone']] ?? $chartTones['primary'] }}"
                                        style="height: {{ $item['value'] > 0 ? max(8, round(($item['value'] / $chartMaximum) * 100)) : 4 }}%;"
                                    ></span>
                                </div>
                                <span class="truncate text-center text-[9px] font-semibold leading-none text-neutral-500 dark:text-neutral-400">{{ $item['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex h-2.5 w-full overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                        @if ($chartTotal > 0)
                            @foreach ($chartItems as $item)
                                <span
                                    class="h-full {{ $chartTones[$item['tone']] ?? $chartTones['primary'] }}"
                                    style="width: {{ round(($item['value'] / $chartTotal) * 100, 2) }}%;"
                                    title="{{ $item['label'] }}: {{ number_format($item['value'], 2) }}"
                                ></span>
                            @endforeach
                        @endif
                    </div>
                @endif
            </div>
        @endif

        @if ($breakdownItems->isNotEmpty())
            <dl class="mt-3 grid {{ $featured ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-2' }} gap-x-3 border-t border-neutral-100 pt-3 dark:border-neutral-800/80">
                @foreach ($breakdownItems as $item)
                    <div class="min-w-0">
                        <dt class="truncate text-[10px] font-bold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $item['label'] }}</dt>
                        <dd class="mt-0.5 truncate text-sm font-bold tabular-nums text-neutral-900 dark:text-neutral-100" title="{{ $item['value'] }}">{{ $item['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    @if ($hint)
        <div class="{{ $compact ? 'mt-2.5 pt-2' : 'mt-3.5 pt-2.5' }} flex items-center border-t border-neutral-100 dark:border-neutral-800/80">
            <p class="{{ $compact ? 'line-clamp-2' : 'truncate' }} text-xs font-medium leading-snug text-neutral-600 dark:text-neutral-300 sm:text-sm">{{ $hint }}</p>
        </div>
    @endif

</{{ $tag }}>
