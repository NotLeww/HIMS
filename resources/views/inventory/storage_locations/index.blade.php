<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/60" aria-hidden="true">
                    <x-ui.icon name="cube" class="size-6" />
                </span>
                <div class="min-w-0">
                    <h2 class="text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">Storage Areas</h2>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">Manage rooms, shelves, and storage zones.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.button variant="secondary" :href="route('inventory.warehousing.dashboard')" icon="arrow-left">Back to Smart Warehousing</x-ui.button>
            </div>
        </div>
    </x-slot>

    {{-- SWS Consolidated Workflow Navigation --}}
    @include('inventory.warehousing.partials.workflow_nav')

    @can(\App\Enums\Permission::ManageLocations->value)
        <x-ui.modal name="add-storage-location" maxWidth="3xl">
            <x-slot:header>
                <div>
                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Add Storage Location</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                        Use only the hierarchy levels your physical warehouse actually needs.
                    </p>
                </div>
            </x-slot:header>

            <form method="POST" action="{{ route('inventory.storage-locations.store') }}"
                  @if ($errors->any()) x-init="open = true" @endif
                  class="space-y-4"
                  data-confirm-title="Create storage location"
                  data-confirm-message="Are you sure you want to add this storage location to the warehouse layout?"
                  data-confirm-label="Create Location">
                @csrf

                @if ($errors->any())
                    <x-ui.alert variant="danger" title="Location could not be saved">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Display name <span class="text-rose-500">*</span></label>
                        <input id="name" type="text" name="name" required maxlength="255" value="{{ old('name') }}" placeholder="e.g. Ambient Bin 01" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                    </div>
                    <div>
                        <label for="type" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Location type <span class="text-rose-500">*</span></label>
                        <select id="type" name="type" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm pl-3 pr-10">
                            <option value="">Select type</option>
                            @foreach(['warehouse'=>'Warehouse','zone'=>'Zone','aisle'=>'Aisle','rack'=>'Rack','shelf'=>'Shelf','level'=>'Level','bin'=>'Bin','pharmacy'=>'Pharmacy stockroom','department'=>'Department stockroom'] as $value=>$label)
                                <option value="{{ $value }}" @selected(old('type')===$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="parent_id" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Parent location</label>
                        <select id="parent_id" name="parent_id" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm pl-3 pr-10">
                            <option value="">None / root</option>
                            @foreach($parentLocations as $parent)
                                <option value="{{ $parent->id }}" @selected((string)old('parent_id')===(string)$parent->id)>{{ $parent->code }} - {{ $parent->fullPath() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="code" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Internal code <span class="font-normal text-neutral-500 dark:text-neutral-400">(optional)</span></label>
                        <input id="code" type="text" name="code" maxlength="100" value="{{ old('code') }}" placeholder="Auto-generated when blank" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm font-mono">
                        <p class="mt-1 text-[11px] text-neutral-500 dark:text-neutral-400">Letters, numbers, dot, dash, and underscore only.</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="storage_classification" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Storage classification</label>
                        <select id="storage_classification" name="storage_classification" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm pl-3 pr-10">
                            <option value="">Any compatible item</option>
                            @foreach(['general'=>'General','medical_supply'=>'Medical supply','pharmaceutical'=>'Pharmaceutical','sterile'=>'Sterile','cold_chain'=>'Cold chain','hazardous'=>'Hazardous','flammable'=>'Flammable','controlled'=>'Controlled / restricted'] as $value=>$label)
                                <option value="{{ $value }}" @selected(old('storage_classification')===$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="temperature_classification" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Temperature classification</label>
                        <select id="temperature_classification" name="temperature_classification" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm pl-3 pr-10">
                            <option value="">Use product/manufacturer requirement</option>
                            @foreach(['ambient'=>'Ambient','controlled_room'=>'Controlled room temperature','refrigerated'=>'Refrigerated','frozen'=>'Frozen','deep_frozen'=>'Deep frozen'] as $value=>$label)
                                <option value="{{ $value }}" @selected(old('temperature_classification')===$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-4">
                    <div>
                        <label for="capacity" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Capacity</label>
                        <input id="capacity" type="number" name="capacity" min="1" step="1" inputmode="numeric" value="{{ old('capacity') }}" placeholder="e.g. 500" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                    </div>
                    <div>
                        <label for="capacity_unit" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Capacity unit</label>
                        <select id="capacity_unit" name="capacity_unit" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm pl-3 pr-10">
                            @foreach(['units'=>'Units','boxes'=>'Boxes','pallets'=>'Pallets'] as $value=>$label)
                                <option value="{{ $value }}" @selected(old('capacity_unit', 'units')===$value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Operational status <span class="text-rose-500">*</span></label>
                        <select id="status" name="status" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm pl-3 pr-10">
                            <option value="active" @selected(old('status', 'active')==='active')>Active</option>
                            <option value="blocked" @selected(old('status')==='blocked')>Blocked</option>
                            <option value="inactive" @selected(old('status')==='inactive')>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label for="sort_sequence" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Pick sort sequence</label>
                        <input id="sort_sequence" type="number" name="sort_sequence" min="0" step="1" value="{{ old('sort_sequence', 0) }}" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">
                    </div>
                </div>

                <fieldset class="space-y-2">
                    <div class="flex items-center justify-between">
                        <legend class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300">Operational Purpose</legend>
                        <span class="text-[11px] text-neutral-500 dark:text-neutral-400">Select all applicable warehouse roles</span>
                    </div>

                    @php
                        $purposes = [
                            'is_receiving_staging' => 'Receiving staging',
                            'is_quarantine'        => 'Quarantine',
                            'is_pick_face'         => 'Pick face',
                            'is_reserve'           => 'Reserve storage',
                            'is_dispatch_staging'  => 'Dispatch staging',
                            'is_in_transit'         => 'In-transit buffer',
                            'is_returns_area'      => 'Returns area',
                            'is_damaged_stock'     => 'Damaged stock',
                        ];
                    @endphp

                    <div class="flex flex-wrap gap-2.5">
                        @foreach($purposes as $name => $label)
                            <label
                                x-data="{ isChecked: {{ old($name) ? 'true' : 'false' }} }"
                                :class="isChecked
                                    ? 'border-primary-500 bg-primary-50/70 text-primary-900 dark:border-primary-500 dark:bg-primary-950/50 dark:text-primary-100 ring-1 ring-primary-500 shadow-2xs'
                                    : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700/80 dark:bg-neutral-800/80 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-800'"
                                class="group relative inline-flex items-center gap-2.5 rounded-lg border px-3.5 py-2.5 shadow-2xs transition-all duration-150 cursor-pointer select-none"
                            >
                                <input
                                    type="checkbox"
                                    name="{{ $name }}"
                                    value="1"
                                    @checked(old($name))
                                    x-on:change="isChecked = $event.target.checked"
                                    class="h-4 w-4 rounded border-neutral-300 dark:border-neutral-600 dark:bg-neutral-700 text-primary-600 focus:ring-primary-500 focus:ring-offset-0 transition cursor-pointer"
                                >
                                <span class="text-xs font-semibold whitespace-nowrap">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div>
                    <label for="description" class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">Description</label>
                    <textarea id="description" name="description" maxlength="255" rows="2" placeholder="Optional notes about location dimensions, accessibility, or handling..." class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-2xs focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-sm">{{ old('description') }}</textarea>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-neutral-200 dark:border-neutral-800">
                    <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary">Save location</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any())
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'add-storage-location'))"></div>
        @endif
    @endcan

    <div x-data="{ deactivateModal: false, confirmDeactivateModal: false, activateModal: false, confirmActivateModal: false, targetLocation: null, reason: '', reasonError: null }" class="space-y-6">
        <x-ui.card :padding="false" title="Storage Areas" subtitle="Occupancy is calculated from the authoritative location balance.">
            @can(\App\Enums\Permission::ManageLocations->value)
                <x-slot:actions>
                    <x-ui.button type="button" size="sm" variant="primary" icon="plus" x-data x-on:click="$dispatch('open-modal', 'add-storage-location')">
                        Add Storage Area
                    </x-ui.button>
                </x-slot:actions>
            @endcan

            <form method="GET" action="{{ route('inventory.storage-locations') }}" class="grid gap-3 border-b border-neutral-200 p-4 dark:border-neutral-800 sm:grid-cols-2 lg:grid-cols-[minmax(18rem,1fr)_minmax(10rem,14rem)_minmax(10rem,14rem)_auto]" aria-label="Filter storage areas">
                <div class="relative sm:col-span-2 lg:col-span-1">
                    <label for="storage-area-search" class="sr-only">Search storage areas</label>
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400" aria-hidden="true">
                        <x-ui.icon name="magnifying-glass" class="size-4" />
                    </span>
                    <input id="storage-area-search" name="search" value="{{ request('search') }}" type="search" placeholder="Search room, department, or zone..." class="block min-h-10 w-full rounded-lg border-neutral-300 bg-white py-2 pl-10 pr-10 text-sm text-neutral-900 shadow-2xs placeholder:text-neutral-500 focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-400">
                    <button type="submit" class="absolute inset-y-0 right-0 inline-flex w-10 items-center justify-center rounded-r-lg text-neutral-500 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500 dark:text-neutral-400 dark:hover:text-primary-300" aria-label="Search storage areas">
                        <x-ui.icon name="arrow-right" class="size-4" />
                    </button>
                </div>

                <div>
                    <label for="storage-area-type" class="sr-only">Location type</label>
                    <select id="storage-area-type" name="type" onchange="this.form.submit()" class="block min-h-10 w-full rounded-lg border-neutral-300 bg-white py-2 pl-3 pr-10 text-sm text-neutral-900 shadow-2xs focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                        <option value="">All location types</option>
                        @foreach($locationTypes as $locationType)
                            <option value="{{ $locationType }}" @selected(request('type') === $locationType)>{{ str($locationType)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="storage-area-status" class="sr-only">Operational status</label>
                    <select id="storage-area-status" name="status" onchange="this.form.submit()" class="block min-h-10 w-full rounded-lg border-neutral-300 bg-white py-2 pl-3 pr-10 text-sm text-neutral-900 shadow-2xs focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100">
                        <option value="">All statuses</option>
                        @foreach(['active' => 'Active', 'blocked' => 'Blocked', 'inactive' => 'Inactive'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                @if(request()->filled('search') || request()->filled('type') || request()->filled('status'))
                    <x-ui.button variant="ghost" :href="route('inventory.storage-locations')">Clear filters</x-ui.button>
                @else
                    <div class="hidden lg:block" aria-hidden="true"></div>
                @endif
            </form>

            <x-ui.table>
                <x-ui.table.head>
                    <tr>
                        <x-ui.table.th>Area ID &amp; name</x-ui.table.th>
                        <x-ui.table.th>Location</x-ui.table.th>
                        <x-ui.table.th class="hidden md:table-cell">Item type</x-ui.table.th>
                        <x-ui.table.th class="hidden lg:table-cell">Purpose</x-ui.table.th>
                        <x-ui.table.th>Capacity / usage</x-ui.table.th>
                        <x-ui.table.th>Status</x-ui.table.th>
                        <x-ui.table.th align="right">Actions</x-ui.table.th>
                    </tr>
                </x-ui.table.head>
                <tbody>
                @forelse($locations as $location)
                    @php
                        $locationIcon = match (true) {
                            in_array($location->temperature_classification, ['refrigerated', 'frozen', 'deep_frozen'], true) => 'beaker',
                            $location->type === 'pharmacy' => 'building-storefront',
                            $location->type === 'department' => 'building-office-2',
                            in_array($location->type, ['rack', 'shelf', 'level'], true) => 'archive-box',
                            $location->type === 'zone' => 'map-pin',
                            default => 'cube',
                        };
                        $locationIconClasses = match (true) {
                            $location->is_quarantine || $location->is_damaged_stock => 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-950/70 dark:text-rose-300 dark:ring-rose-900/60',
                            $location->is_in_transit || $location->is_dispatch_staging => 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-950/70 dark:text-amber-300 dark:ring-amber-900/60',
                            $location->type === 'pharmacy' => 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/70 dark:text-emerald-300 dark:ring-emerald-900/60',
                            default => 'bg-primary-100 text-primary-700 ring-primary-200 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-900/60',
                        };
                        $quantity = $location->totalQuantity();
                        $utilisation = $location->utilisation();
                    @endphp
                    <x-ui.table.row>
                    <x-ui.table.td>
                        <div class="flex min-w-56 items-center gap-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 {{ $locationIconClasses }}" aria-hidden="true">
                                <x-ui.icon :name="$locationIcon" class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-semibold text-primary-700 dark:text-primary-300">{{ $location->code }}</p>
                                <p class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $location->name }}</p>
                            </div>
                        </div>
                    </x-ui.table.td>
                    <x-ui.table.td>
                        <div class="flex items-center gap-2">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300" aria-hidden="true">
                                <x-ui.icon name="map-pin" class="size-4" />
                            </span>
                            <div>
                                <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ str($location->type)->replace('_', ' ')->title() }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $location->parent?->name ?? 'Root location' }}</p>
                            </div>
                        </div>
                    </x-ui.table.td>
                    <x-ui.table.td class="hidden md:table-cell">
                        <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $location->storage_classification ? str($location->storage_classification)->replace('_', ' ')->title() : 'Any compatible item' }}</p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $location->temperature_classification ? str($location->temperature_classification)->replace('_', ' ')->title() : 'No fixed temperature' }}</p>
                    </x-ui.table.td>
                    <x-ui.table.td class="hidden lg:table-cell">
                        <div class="flex max-w-xs flex-wrap gap-1.5">
                            @forelse(['is_receiving_staging'=>'Receiving','is_quarantine'=>'Quarantine','is_pick_face'=>'Pick face','is_reserve'=>'Reserve','is_dispatch_staging'=>'Dispatch','is_in_transit'=>'In transit','is_returns_area'=>'Returns','is_damaged_stock'=>'Damaged'] as $flag=>$label)
                                @if($location->{$flag})
                                    <span class="inline-flex rounded-md bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">{{ $label }}</span>
                                @endif
                            @empty
                            @endforelse
                            @if(! collect(['is_receiving_staging','is_quarantine','is_pick_face','is_reserve','is_dispatch_staging','is_in_transit','is_returns_area','is_damaged_stock'])->contains(fn ($flag) => $location->{$flag}))
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">General storage</span>
                            @endif
                        </div>
                    </x-ui.table.td>
                    <x-ui.table.td>
                        <div class="min-w-32 tabular-nums">
                            <p class="font-semibold text-neutral-900 dark:text-neutral-100">
                                {{ number_format($quantity) }}@if($location->capacity) / {{ number_format($location->capacity) }}@endif
                            </p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $location->capacity_unit ?? 'units' }}</p>
                            @if($utilisation !== null)
                                <div class="mt-1.5 flex items-center gap-2">
                                    <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-700" aria-hidden="true">
                                        <span class="block h-full rounded-full {{ $utilisation > 100 ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ min(100, $utilisation) }}%"></span>
                                    </span>
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400">{{ $utilisation }}%</span>
                                </div>
                            @endif
                        </div>
                    </x-ui.table.td>
                    <x-ui.table.td><x-ui.badge :status="$location->status" :dot="true">{{ str($location->status)->title() }}</x-ui.badge></x-ui.table.td>
                    <x-ui.table.td><div class="flex min-w-40 items-center justify-end gap-2">
                        @can(\App\Enums\Permission::PrintWarehouseLabels->value)
                            <form method="POST" action="{{ route('inventory.storage-locations.label', $location) }}" target="_blank">
                                @csrf
                                <input type="hidden" name="copies" value="1">
                                <x-ui.button type="submit" size="sm" variant="secondary" icon="qr-code">Print QR</x-ui.button>
                            </form>
                        @endcan
                        @if(auth()->user()?->isSuperAdministrator())
                            @if($location->status === 'active')
                                <button
                                    type="button"
                                    @click="targetLocation = { id: {{ $location->id }}, code: '{{ addslashes($location->code) }}', name: '{{ addslashes($location->name) }}' }; reason = ''; reasonError = null; confirmDeactivateModal = false; deactivateModal = true;"
                                    aria-label="Deactivate {{ $location->code }}"
                                    title="Deactivate location"
                                    class="inline-flex size-9 items-center justify-center rounded-md border border-neutral-300 bg-white text-neutral-600 transition-colors hover:bg-neutral-50 hover:text-amber-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-amber-300 dark:focus-visible:ring-offset-neutral-900">
                                    <x-ui.icon name="ellipsis-vertical" class="size-4" />
                                </button>
                            @else
                                <button
                                    type="button"
                                    @click="targetLocation = { id: {{ $location->id }}, code: '{{ addslashes($location->code) }}', name: '{{ addslashes($location->name) }}' }; reason = ''; reasonError = null; confirmActivateModal = false; activateModal = true;"
                                    aria-label="Activate {{ $location->code }}"
                                    title="Activate location"
                                    class="inline-flex size-9 items-center justify-center rounded-md border border-neutral-300 bg-white text-neutral-600 transition-colors hover:bg-neutral-50 hover:text-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:hover:text-emerald-300 dark:focus-visible:ring-offset-neutral-900">
                                    <x-ui.icon name="ellipsis-vertical" class="size-4" />
                                </button>
                            @endif
                        @endif
                    </div></x-ui.table.td>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.empty colspan="7" title="No storage areas found" message="Adjust the filters or add a storage area to the warehouse hierarchy." />
                @endforelse
                </tbody>
            </x-ui.table>
            @if ($locations->hasPages())
                <div class="border-t border-neutral-200 p-4 dark:border-neutral-800">
                    {{ $locations->onEachSide(1)->links() }}
                </div>
            @endif
        </x-ui.card>

        {{-- Step 1: Deactivation Reason Modal (Super Admin only) --}}
        @if(auth()->user()?->isSuperAdministrator())
        <div
            x-show="deactivateModal"
            x-cloak
            x-on:keydown.escape.window="deactivateModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reg-deactivate-modal-title"
        >
            <div
                x-show="deactivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="deactivateModal = false"
                class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            <div
                x-show="deactivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-95"
                class="relative w-full max-w-lg rounded-xl border border-amber-200 bg-white shadow-xl dark:border-amber-900/50 dark:bg-neutral-900 p-6 z-10 space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <h3 id="reg-deactivate-modal-title" class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                            Deactivate Storage Location
                        </h3>
                        <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                            Provide the reason for deactivating <span class="font-mono font-bold" x-text="targetLocation?.code"></span> (<span x-text="targetLocation?.name"></span>) before proceeding to confirmation.
                        </p>
                    </div>
                </div>

                <template x-if="reasonError">
                    <div class="rounded-lg border border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/60 p-3 text-xs text-red-800 dark:text-red-300 flex items-center justify-between">
                        <span x-text="reasonError"></span>
                        <button type="button" @click="reasonError = null" class="text-red-500 hover:text-red-700">&times;</button>
                    </div>
                </template>

                <form @submit.prevent="if (reason && reason.trim()) { reasonError = null; deactivateModal = false; confirmDeactivateModal = true; } else { reasonError = 'Please provide a reason for deactivation.'; }" class="space-y-4">
                    <div>
                        <label for="registry-deactivate-reason" class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                            Reason for Deactivation <span class="text-red-500">*</span> <span class="font-normal text-neutral-500">(Required for audit trail)</span>
                        </label>
                        <textarea
                            id="registry-deactivate-reason"
                            name="reason"
                            rows="2"
                            x-model="reason"
                            required
                            placeholder="e.g. Warehouse under renovation, consolidation to Main Warehouse..."
                            class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100 focus:border-amber-500 focus:ring-amber-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="deactivateModal = false"
                            class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
                        >
                            <span>Deactivate Location</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Step 2: Confirmation Modal for Deactivation (with Yes / Cancel) --}}
        <div
            x-show="confirmDeactivateModal"
            x-cloak
            x-on:keydown.escape.window="confirmDeactivateModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reg-confirm-deactivate-title"
        >
            <div
                x-show="confirmDeactivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="confirmDeactivateModal = false"
                class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            <div
                x-show="confirmDeactivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-95"
                class="relative w-full max-w-md rounded-xl border border-amber-200 bg-white shadow-xl dark:border-amber-900/50 dark:bg-neutral-900 p-6 z-10 space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="reg-confirm-deactivate-title" class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                            Are you sure you want to deactivate this storage location?
                        </h3>
                        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                            Are you sure you want to deactivate <span class="font-mono font-bold text-neutral-900 dark:text-neutral-100" x-text="targetLocation?.code"></span> (<span x-text="targetLocation?.name"></span>)? Once deactivated, this location cannot receive new inventory or Purchase Orders. Existing stock can still be released or transferred out. The location and its history will remain intact.
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 p-3 text-xs space-y-1">
                    <div class="text-neutral-500 dark:text-neutral-400">Recorded Reason:</div>
                    <div class="font-medium text-neutral-800 dark:text-neutral-200 italic" x-text="reason"></div>
                </div>

                <form :action="'{{ url('/inventory/storage-locations') }}/' + (targetLocation?.id || '') + '/status'" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="inactive">
                    <input type="hidden" name="reason" :value="reason">

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="confirmDeactivateModal = false; deactivateModal = true;"
                            class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2"
                        >
                            Yes, Deactivate Location
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Step 1: Reactivation Reason Modal (Super Admin only) --}}
        <div
            x-show="activateModal"
            x-cloak
            x-on:keydown.escape.window="activateModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reg-activate-modal-title"
        >
            <div
                x-show="activateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="activateModal = false"
                class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            <div
                x-show="activateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-95"
                class="relative w-full max-w-md rounded-xl border border-emerald-200 bg-white shadow-xl dark:border-emerald-900/50 dark:bg-neutral-900 p-6 z-10 space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="reg-activate-modal-title" class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                            Reactivate Storage Location
                        </h3>
                        <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                            Specify the reason for reactivating <span class="font-mono font-bold" x-text="targetLocation?.code"></span> before proceeding to confirmation.
                        </p>
                    </div>
                </div>

                <form @submit.prevent="activateModal = false; confirmActivateModal = true;" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="active">

                    <div>
                        <label for="registry-activate-reason" class="block text-xs font-semibold uppercase text-neutral-700 dark:text-neutral-300">
                            Reason for Reactivation <span class="font-normal text-neutral-500">(for audit trail)</span>
                        </label>
                        <textarea
                            id="registry-activate-reason"
                            name="reason"
                            rows="2"
                            x-model="reason"
                            placeholder="e.g. Facility reopening, maintenance completed..."
                            class="mt-1 w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-sm text-neutral-900 dark:text-neutral-100 focus:border-emerald-500 focus:ring-emerald-500"
                        ></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="activateModal = false"
                            class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            <span>Activate Location</span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Step 2: Confirmation Modal for Reactivation (with Yes / Cancel) --}}
        <div
            x-show="confirmActivateModal"
            x-cloak
            x-on:keydown.escape.window="confirmActivateModal = false"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reg-confirm-activate-title"
        >
            <div
                x-show="confirmActivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="confirmActivateModal = false"
                class="fixed inset-0 bg-neutral-900/60 dark:bg-black/75 backdrop-blur-xs"
                aria-hidden="true"
            ></div>

            <div
                x-show="confirmActivateModal"
                x-transition:enter="ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-95"
                class="relative w-full max-w-md rounded-xl border border-emerald-200 bg-white shadow-xl dark:border-emerald-900/50 dark:bg-neutral-900 p-6 z-10 space-y-4"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="reg-confirm-activate-title" class="text-lg font-bold text-neutral-900 dark:text-neutral-100">
                            Are you sure you want to reactivate this storage location?
                        </h3>
                        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                            Are you sure you want to reactivate <span class="font-mono font-bold text-neutral-900 dark:text-neutral-100" x-text="targetLocation?.code"></span> (<span x-text="targetLocation?.name"></span>)? Once reactivated, this location will be able to receive incoming inventory, purchase orders, and stock transfers normally.
                        </p>
                    </div>
                </div>

                <div class="rounded-lg border border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-800/40 p-3 text-xs space-y-1">
                    <div class="text-neutral-500 dark:text-neutral-400">Recorded Reason:</div>
                    <div class="font-medium text-neutral-800 dark:text-neutral-200 italic" x-text="reason || 'Reactivated location'"></div>
                </div>

                <form :action="'{{ url('/inventory/storage-locations') }}/' + (targetLocation?.id || '') + '/status'" method="POST" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="active">
                    <input type="hidden" name="reason" :value="reason">

                    <div class="flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="confirmActivateModal = false; activateModal = true;"
                            class="rounded-lg border border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 px-4 py-2 text-sm font-semibold text-neutral-700 dark:text-neutral-300 hover:bg-neutral-50 dark:hover:bg-neutral-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-2xs hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                        >
                            Yes, Activate Location
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
        </div>
</x-app-layout>
