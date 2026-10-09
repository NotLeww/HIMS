@props([
    'artwork',
    'title',
    'message',
])

{{--
    Full-width empty state for non-table data regions.
    Callers must select original artwork that represents the actual record category.
--}}
<section {{ $attributes->merge([
    'class' => 'hims-empty-surface flex min-h-[18rem] w-full items-center justify-center rounded-2xl border border-primary-100 px-6 py-12 text-center shadow-sm dark:border-primary-900/50 sm:min-h-[19rem]',
]) }} data-empty-state data-empty-category="{{ $artwork }}">
    <div class="flex max-w-xl flex-col items-center">
        <x-ui.empty-artwork :category="$artwork" :surface="false" />
        <h2 class="mt-3 text-xl font-bold tracking-tight text-neutral-950 dark:text-white">{{ $title }}</h2>
        <p class="mt-2 text-sm leading-6 text-neutral-500 dark:text-neutral-400 sm:text-base">{{ $message }}</p>

        @isset($action)
            <div class="mt-5">{{ $action }}</div>
        @endisset
    </div>
</section>
