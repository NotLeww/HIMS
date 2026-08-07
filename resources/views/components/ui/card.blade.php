@props([
    'title' => null,
    'subtitle' => null,
    'padding' => true,
])

<section {{ $attributes->merge([
    'class' => 'bg-white border border-neutral-200 rounded-lg shadow-sm overflow-hidden',
]) }}>
    @if ($title || isset($header) || isset($actions))
        <header class="flex items-start justify-between gap-4 px-5 py-4 border-b border-neutral-200">
            <div class="min-w-0">
                @isset($header)
                    {{ $header }}
                @else
                    <h2 class="text-sm font-semibold text-neutral-900 truncate">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="mt-0.5 text-xs text-neutral-500">{{ $subtitle }}</p>
                    @endif
                @endisset
            </div>

            @isset($actions)
                <div class="flex items-center gap-2 shrink-0">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $padding ? 'p-5' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="px-5 py-3 border-t border-neutral-200 bg-neutral-50">
            {{ $footer }}
        </footer>
    @endisset
</section>
