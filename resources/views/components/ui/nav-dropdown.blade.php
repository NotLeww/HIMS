@props([
    'id' => null,
    'title',
    'icon' => null,
    'active' => false,
    'badge' => null,
])

@php
    $dropdownId = $id ?? \Illuminate\Support\Str::slug($title);
@endphp

<div
    x-data="typeof activeDropdown !== 'undefined' ? {} : { activeDropdown: {{ $active ? "'{$dropdownId}'" : 'null' }} }"
    @keydown.escape.stop="if (activeDropdown === '{{ $dropdownId }}') { activeDropdown = null; $nextTick(() => $refs.trigger.focus()) }"
    class="space-y-1"
>
    <button
        x-ref="trigger"
        type="button"
        @click="activeDropdown = (activeDropdown === '{{ $dropdownId }}' ? null : '{{ $dropdownId }}')"
        class="w-full group flex min-h-11 items-center justify-between rounded-xl px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 {{ $active ? 'bg-emerald-400/20 text-white shadow-lg ring-1 ring-inset ring-emerald-300/20' : 'text-emerald-50/80 hover:bg-white/10 hover:text-white' }}"
        :aria-expanded="activeDropdown === '{{ $dropdownId }}' ? 'true' : 'false'"
        aria-controls="nav-dropdown-{{ $dropdownId }}"
    >
        <span class="flex items-center gap-2.5 truncate">
            @if ($icon)
                <x-ui.icon
                    :name="$icon"
                    class="w-[19px] h-[19px] shrink-0 {{ $active ? 'text-emerald-300' : 'text-emerald-100/65 group-hover:text-emerald-200' }}"
                />
            @endif
            <span class="truncate">{{ $title }}</span>
        </span>

        <span class="flex items-center gap-1.5 shrink-0">
            @if ($badge)
                <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold tabular-nums {{ $active ? 'bg-emerald-300/20 text-emerald-100' : 'bg-white/10 text-emerald-100/75' }}">
                    {{ $badge }}
                </span>
            @endif
            <svg
                class="w-3.5 h-3.5 text-emerald-100/55 group-hover:text-emerald-100 transition-transform duration-300 ease-in-out"
                :class="activeDropdown === '{{ $dropdownId }}' ? 'rotate-180 text-emerald-200' : ''"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </span>
    </button>

    <div
        id="nav-dropdown-{{ $dropdownId }}"
        class="grid transition-[grid-template-rows,opacity] duration-300 ease-in-out overflow-hidden"
        :class="activeDropdown === '{{ $dropdownId }}' ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0 pointer-events-none'"
        :aria-hidden="activeDropdown === '{{ $dropdownId }}' ? 'false' : 'true'"
        :inert="activeDropdown !== '{{ $dropdownId }}'"
    >
        <div class="overflow-hidden min-h-0">
            <div
                class="ml-4 space-y-0.5 border-l border-emerald-200/15 py-1 pl-3 pr-1 transition-transform duration-300 ease-in-out"
                :class="activeDropdown === '{{ $dropdownId }}' ? 'translate-y-0' : '-translate-y-1.5'"
            >
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
