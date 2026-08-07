@props([
    'colspan' => 1,
    'icon' => 'inbox',
    'title' => 'Nothing here yet',
    'message' => null,
])

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-14 text-center bg-white">
        <div class="flex flex-col items-center gap-2">
            <span class="flex items-center justify-center w-10 h-10 rounded-full bg-neutral-100 text-neutral-400">
                <x-ui.icon :name="$icon" class="w-5 h-5" />
            </span>
            <p class="text-sm font-medium text-neutral-700">{{ $title }}</p>
            @if ($message)
                <p class="max-w-sm text-xs text-neutral-500">{{ $message }}</p>
            @endif
            @isset($action)
                <div class="mt-2">{{ $action }}</div>
            @endisset
        </div>
    </td>
</tr>
