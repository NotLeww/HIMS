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
    'analytics' => false,
    'hintIcon' => 'information-circle',
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
    $analyticsSurfaces = [
        'neutral' => 'border-neutral-200/90 bg-gradient-to-br from-white via-white to-neutral-50/80 dark:border-neutral-700 dark:from-neutral-900 dark:via-neutral-900 dark:to-neutral-800/80',
        'primary' => 'border-primary-200/80 bg-gradient-to-br from-white via-white to-primary-50/80 dark:border-primary-900/70 dark:from-neutral-900 dark:via-neutral-900 dark:to-primary-950/50',
        'success' => 'border-emerald-200/80 bg-gradient-to-br from-white via-emerald-50/45 to-emerald-50/80 dark:border-emerald-900/70 dark:from-neutral-900 dark:via-emerald-950/20 dark:to-emerald-950/45',
        'warning' => 'border-amber-200/80 bg-gradient-to-br from-white via-amber-50/35 to-orange-50/70 dark:border-amber-900/70 dark:from-neutral-900 dark:via-amber-950/15 dark:to-orange-950/35',
        'danger' => 'border-rose-200/80 bg-gradient-to-br from-white via-rose-50/35 to-rose-50/70 dark:border-rose-900/70 dark:from-neutral-900 dark:via-rose-950/15 dark:to-rose-950/35',
    ];
    $breakdownSurfaces = [
        'neutral' => 'border-neutral-200/80 bg-white/70 dark:border-neutral-700 dark:bg-neutral-900/60',
        'primary' => 'border-primary-100 bg-white/70 dark:border-primary-900/60 dark:bg-neutral-900/60',
        'success' => 'border-emerald-100 bg-white/70 dark:border-emerald-900/60 dark:bg-neutral-900/60',
        'warning' => 'border-amber-100 bg-white/70 dark:border-amber-900/60 dark:bg-neutral-900/60',
        'danger' => 'border-rose-100 bg-white/70 dark:border-rose-900/60 dark:bg-neutral-900/60',
    ];
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    @if ($summary) data-metric-summary="{{ $summary }}" @endif
    @if ($tooltipDetails) data-metric-details="{{ json_encode($tooltipDetails) }}" @endif
    @if ($summaryTitle) data-metric-title="{{ $summaryTitle }}" @endif
    {{ $attributes->merge([
        'class' => 'group relative flex min-w-0 flex-col justify-between overflow-hidden border shadow-xs transition-[box-shadow,border-color,background-color] motion-safe:duration-150 '
            .($analytics
                ? 'rounded-2xl '.($analyticsSurfaces[$tone] ?? $analyticsSurfaces['neutral']).' p-5 sm:p-6 '
                : 'rounded-xl bg-white dark:bg-neutral-900/95 '.($featured ? 'border-neutral-300 dark:border-neutral-700 ' : 'border-neutral-200/90 dark:border-neutral-800 ').($compact ? 'p-4 sm:p-5 ' : 'p-5 '))
            .($href
                ? ' cursor-pointer hover:border-primary-300 hover:shadow-sm active:bg-neutral-50 focus-visible:z-30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:hover:border-primary-700 dark:active:bg-neutral-800/80 dark:focus-visible:ring-offset-neutral-950'
                : ' hover:border-neutral-300 dark:hover:border-neutral-700'),
    ]) }}
>
    <div>
        <div class="flex {{ $analytics ? 'flex-wrap items-start gap-2' : 'items-center gap-3' }} justify-between">
            <div class="flex min-w-0 items-center {{ $analytics ? 'min-w-[9rem] flex-1 gap-2.5' : 'gap-3' }}">
                @if ($icon)
                    <span class="flex {{ $analytics ? 'h-10 w-10 rounded-xl' : ($compact ? 'h-9 w-9 rounded-lg' : 'h-10 w-10 rounded-xl') }} shrink-0 items-center justify-center ring-1 {{ $tones[$tone]['icon'] }}">
                        <x-ui.icon :name="$icon" class="{{ $analytics ? 'h-5 w-5' : ($compact ? 'h-4 w-4' : 'h-5 w-5') }}" />
                    </span>
                @endif
                <p class="min-w-0 font-bold uppercase tracking-wider {{ $analytics ? 'text-[11px] leading-snug sm:text-xs' : 'text-xs '.(!$compact ? 'sm:text-sm' : '') }} {{ $tones[$tone]['label'] }}">{{ $label }}</p>
            </div>
            @if ($analytics && $context)
                <span class="shrink-0 rounded-lg bg-neutral-100/80 px-2.5 py-1.5 text-[11px] font-medium text-neutral-600 ring-1 ring-neutral-200/70 dark:bg-neutral-800/80 dark:text-neutral-300 dark:ring-neutral-700">{{ $context }}</span>
            @endif
        </div>

        <div class="{{ $analytics ? 'mt-4' : 'mt-3' }} flex min-w-0 items-end justify-between gap-3">
            <p class="flex min-w-0 flex-wrap items-baseline gap-x-1.5 break-words font-black tracking-[-0.025em] tabular-nums {{ $analytics ? ($featured ? 'text-[clamp(2.25rem,3vw,3.5rem)]' : 'text-[clamp(2rem,2.7vw,3.25rem)]') : ($featured ? 'text-3xl sm:text-4xl' : ($compact ? 'text-2xl sm:text-3xl' : 'text-3xl sm:text-4xl lg:text-5xl')) }} {{ $tones[$tone]['value'] }}">
                @if ($prefix)
                    <span class="text-[0.72em] font-black tracking-normal {{ $analytics ? $tones[$tone]['value'] : $tones[$tone]['label'] }}">{{ $prefix }}</span>
                @endif
                <span>{{ $value }}</span>
                @if ($suffix)
                    <span class="text-xs font-bold tracking-normal text-neutral-500 dark:text-neutral-400 sm:text-sm">{{ $suffix }}</span>
                @endif
            </p>
            @if (!$analytics && $compact && $context)
                <span class="mb-1 shrink-0 text-[11px] font-semibold text-neutral-500 dark:text-neutral-400">{{ $context }}</span>
            @endif
        </div>

        @if ($chartItems->isNotEmpty())
            <div class="mt-3" role="img" aria-label="{{ $chartLabel ?? $label.' comparison' }}">
                @if ($chartLabel)
                    <p class="mb-1.5 text-[10px] font-bold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $chartLabel }}</p>
                @endif

                @if ($chartType === 'bars')
                    <div class="grid items-start gap-2" style="grid-template-columns: repeat({{ $chartItems->count() }}, minmax(0, 1fr));">
                        @foreach ($chartItems as $item)
                            <div class="flex min-w-0 flex-col gap-1.5" title="{{ $item['label'] }}: {{ number_format($item['value'], 2) }}">
                                <div class="flex h-9 items-end overflow-hidden rounded-md bg-neutral-100 dark:bg-neutral-800">
                                    <span
                                        class="block w-full rounded-md {{ $chartTones[$item['tone']] ?? $chartTones['primary'] }}"
                                        style="height: {{ $item['value'] > 0 ? max(8, round(($item['value'] / $chartMaximum) * 100)) : 4 }}%;"
                                    ></span>
                                </div>
                                <span class="text-center text-[9px] font-medium leading-tight text-neutral-500 dark:text-neutral-400">{{ $item['label'] }}</span>
                                <span class="text-center text-sm font-black leading-none tabular-nums text-neutral-900 dark:text-white">{{ number_format($item['value']) }}</span>
                            </div>
                        @endforeach
                    </div>
                @elseif ($chartType === 'comparison')
                    <div class="grid gap-2" style="grid-template-columns: repeat({{ $chartItems->count() }}, minmax(0, 1fr));">
                        @foreach ($chartItems as $item)
                            <div class="min-w-0" title="{{ $item['label'] }}: {{ number_format($item['value'], 2) }}">
                                <div class="h-3 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                                    <span
                                        class="block h-full rounded-full {{ $chartTones[$item['tone']] ?? $chartTones['primary'] }}"
                                        style="width: {{ $item['value'] > 0 ? max(3, round(($item['value'] / $chartMaximum) * 100)) : 0 }}%;"
                                    ></span>
                                </div>
                                <div class="mt-1.5 flex min-w-0 items-center justify-center gap-1.5">
                                    <span class="h-2 w-2 shrink-0 rounded-full {{ $chartTones[$item['tone']] ?? $chartTones['primary'] }}"></span>
                                    <span class="truncate text-[10px] font-semibold text-neutral-500 dark:text-neutral-400">{{ $item['label'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex h-3 w-full overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
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
            <dl class="mt-3 grid {{ $featured ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-2' }} {{ $analytics ? 'gap-2' : 'gap-x-3 border-t border-neutral-100 pt-3 dark:border-neutral-800/80' }}">
                @foreach ($breakdownItems as $breakdownIndex => $item)
                    @php
                        $breakdownTone = $item['tone'] ?? data_get($chartItems->get($breakdownIndex), 'tone', 'neutral');
                    @endphp
                    <div class="min-w-0 {{ $analytics ? 'rounded-xl border px-2.5 py-2.5 '.($breakdownSurfaces[$breakdownTone] ?? $breakdownSurfaces['neutral']) : '' }}">
                        <dt class="flex min-w-0 items-start text-[10px] font-bold uppercase text-neutral-500 dark:text-neutral-400 {{ $analytics ? 'gap-1.5 leading-tight tracking-normal' : 'gap-2 truncate tracking-wide' }}">
                            @if ($analytics)
                                <span class="mt-0.5 h-2 w-2 shrink-0 rounded-full {{ $chartTones[$breakdownTone] ?? $chartTones['neutral'] }}"></span>
                            @endif
                            <span class="{{ $analytics ? 'whitespace-normal [overflow-wrap:normal] [word-break:normal]' : 'truncate' }}">{{ $item['label'] }}</span>
                        </dt>
                        <dd class="mt-1 truncate text-sm font-black tabular-nums text-neutral-950 dark:text-white sm:text-base" title="{{ $item['value'] }}">{{ $item['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    @if ($hint)
        <div class="{{ $analytics ? 'mt-4 gap-3 pt-3' : ($compact ? 'mt-2.5 pt-2' : 'mt-3.5 pt-2.5') }} flex items-center border-t border-neutral-200/70 dark:border-neutral-800/80">
            @if ($analytics)
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-100 dark:bg-primary-950/60 dark:text-primary-300 dark:ring-primary-900">
                    <x-ui.icon :name="$hintIcon" class="h-4 w-4" />
                </span>
            @endif
            <p class="{{ $analytics ? 'break-words' : ($compact ? 'line-clamp-2' : 'truncate') }} text-xs font-medium leading-snug text-neutral-600 dark:text-neutral-300 sm:text-sm">{{ $hint }}</p>
        </div>
    @endif

</{{ $tag }}>
