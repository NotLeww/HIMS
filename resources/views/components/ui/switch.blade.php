@props([
    'checked' => false,
    'label',
])

<button
    type="button"
    role="switch"
    aria-label="{{ $label }}"
    aria-checked="{{ $checked ? 'true' : 'false' }}"
    {{ $attributes->merge(['class' => 'group relative inline-flex h-7 w-12 shrink-0 cursor-pointer items-center rounded-full border border-neutral-500 bg-neutral-600 transition-colors aria-checked:border-primary-600 aria-checked:bg-primary-600 hover:bg-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:ring-offset-neutral-900']) }}
>
    <span class="pointer-events-none inline-block h-5 w-5 translate-x-1 rounded-full bg-white shadow-sm transition-transform group-aria-checked:translate-x-6" aria-hidden="true"></span>
</button>
