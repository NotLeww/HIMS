@props([
    'colspan' => 1,
    'icon' => 'inbox',
    'artwork' => null,
    'title' => 'Nothing here yet',
    'message' => null,
])

<tr>
    <td colspan="{{ $colspan }}" class="p-0 text-center bg-white dark:bg-neutral-900">
        <div class="hims-empty-surface flex min-h-[18rem] w-full flex-col items-center justify-center px-6 py-12 sm:min-h-[19rem]">
            @if ($artwork)
                <x-ui.empty-artwork :category="$artwork" :surface="false" />
            @else
                <span class="flex items-center justify-center w-10 h-10 rounded-full bg-neutral-100 dark:bg-neutral-800 text-neutral-400 dark:text-neutral-500">
                    <x-ui.icon :name="$icon" class="w-5 h-5" />
                </span>
            @endif
            <p class="mt-3 text-xl font-bold tracking-tight text-neutral-950 dark:text-white">{{ $title }}</p>
            @if ($message)
                <p class="mt-2 max-w-xl text-sm leading-6 text-neutral-500 dark:text-neutral-400 sm:text-base">{{ $message }}</p>
            @endif
            @isset($action)
                <div class="mt-2">{{ $action }}</div>
            @endisset
        </div>
    </td>
</tr>
