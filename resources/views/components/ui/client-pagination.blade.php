@props([
    'label' => 'Pagination',
    'page',
    'count',
])

@php
    $pageCount = "Math.max(1, Math.ceil(($count) / pageSize))";
@endphp

<div x-show="({{ $count }}) > pageSize"
     x-cloak
     class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-800">
    <nav role="navigation"
         aria-label="{{ $label }}"
         class="flex flex-col gap-3 text-xs text-neutral-600 sm:flex-row sm:items-center sm:justify-between dark:text-neutral-400">
        <p>
            Showing
            <span class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="(({{ $page }} - 1) * pageSize) + 1"></span>
            to
            <span class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="Math.min({{ $page }} * pageSize, {{ $count }})"></span>
            of
            <span class="font-semibold text-neutral-900 dark:text-neutral-100" x-text="{{ $count }}"></span>
            items
        </p>

        <div class="flex items-center gap-1">
            <button type="button"
                    x-on:click="{{ $page }} = Math.max(1, {{ $page }} - 1)"
                    :disabled="{{ $page }} === 1"
                    aria-label="Previous page"
                    class="inline-flex items-center justify-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-100/60 disabled:text-neutral-400 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80 dark:disabled:border-neutral-800 dark:disabled:bg-neutral-800/40 dark:disabled:text-neutral-600">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
                <span class="sr-only sm:not-sr-only">Previous</span>
            </button>

            <div class="hidden items-center gap-1 sm:flex">
                <template x-for="(pageNumber, index) in paginationPages({{ $pageCount }}, {{ $page }})" :key="`${pageNumber}-${index}`">
                    <template x-if="pageNumber === '…'">
                        <span aria-hidden="true" class="inline-flex items-center justify-center px-2 py-1.5 text-xs font-medium text-neutral-400 dark:text-neutral-500">…</span>
                    </template>
                    <template x-if="pageNumber !== '…'">
                        <button type="button"
                                x-on:click="{{ $page }} = pageNumber"
                                :aria-label="`Go to page ${pageNumber}`"
                                :aria-current="pageNumber === {{ $page }} ? 'page' : null"
                                :class="pageNumber === {{ $page }}
                                    ? 'bg-neutral-900 text-white shadow-2xs dark:bg-primary-600'
                                    : 'border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80'"
                                class="inline-flex min-w-[2rem] items-center justify-center rounded-lg px-2.5 py-1.5 text-xs font-semibold transition"
                                x-text="pageNumber"></button>
                    </template>
                </template>
            </div>

            <button type="button"
                    x-on:click="{{ $page }} = Math.min({{ $pageCount }}, {{ $page }} + 1)"
                    :disabled="{{ $page }} === {{ $pageCount }}"
                    aria-label="Next page"
                    class="inline-flex items-center justify-center gap-1 rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-100/60 disabled:text-neutral-400 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80 dark:disabled:border-neutral-800 dark:disabled:bg-neutral-800/40 dark:disabled:text-neutral-600">
                <span class="sr-only sm:not-sr-only">Next</span>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </div>
    </nav>
</div>
