@php
    $canViewFinancialData = auth()->user()->can(\App\Enums\Permission::ViewProcurementSensitiveData->value);
    $dashboardQuery = array_filter([
        'period' => $currentPeriod,
        'days' => $period['days'],
        'from' => $currentFrom,
        'to' => $currentTo,
        ...$activeFilters,
    ], fn ($value) => $value !== null && $value !== '');
    $drilldownUrl = function (string $type, string|int|null $value = null) use ($activeFilters, $dashboardQuery): string {
        $parameters = [
            ...$dashboardQuery,
            'format' => 'json',
            'report_type' => match ($type) {
                'stock_status' => 'stock_status',
                'reserved' => 'stock_status',
                'movement_type' => 'movement_history',
                'supplier' => 'procurement_expense',
                default => 'expiry_exposure',
            },
        ];

        unset($parameters['stock_status']);

        if (in_array($type, ['stock_status', 'reserved'], true)) {
            $parameters['status'] = $value ?? ($activeFilters['stock_status'] ?? null);
            if ($type === 'reserved') {
                $parameters['reserved_only'] = 1;
            }
        } elseif ($type === 'movement_type') {
            $parameters['movement_type'] = $value ?? ($activeFilters['movement_type'] ?? null);
        } elseif ($type === 'supplier') {
            $parameters['supplier_id'] = $value;
        }

        return route('inventory.reports.generate', array_filter(
            $parameters,
            fn ($parameter) => $parameter !== null && $parameter !== ''
        ));
    };
    $expiryRiskUnits = $expiry['expired']['units'] + $expiry['expiring_soon']['units'];
    $periodContext = $currentPeriod === 'all'
        ? 'all available history through '.$period['to']->format('M d, Y')
        : (!empty($period['is_custom'])
            ? 'the custom date range '.$period['from']->format('M d, Y').' to '.$period['to']->format('M d, Y')
            : 'the '.$period['days'].'-day window ending '.$period['to']->format('M d, Y'));
    $stockStatusTooltipDetails = collect(['in_stock', 'low_stock', 'out_of_stock'])
        ->map(fn (string $status) => match ($status) {
            'in_stock' => number_format($stockStatus[$status]['items']).' in-stock items',
            'low_stock' => number_format($stockStatus[$status]['items']).' low-stock items',
            default => number_format($stockStatus[$status]['items']).' out-of-stock items',
        })
        ->all();
    $reservedTooltipDetails = collect(['in_stock', 'low_stock', 'out_of_stock'])
        ->map(fn (string $status) => match ($status) {
            'in_stock' => number_format($stockStatus[$status]['reserved']).' reserved units from in-stock items',
            'low_stock' => number_format($stockStatus[$status]['reserved']).' reserved units from low-stock items',
            default => number_format($stockStatus[$status]['reserved']).' reserved units from out-of-stock items',
        })
        ->all();
    $expiryTooltipDetails = [
        number_format($expiry['expired']['units']).' expired units across '.number_format($expiry['expired']['batches']).' batches',
        number_format($expiry['expiring_soon']['units']).' expiring-soon units across '.number_format($expiry['expiring_soon']['batches']).' batches',
    ];
    $movementTooltipDetails = $movementsByType
        ->filter(fn (array $row) => $row['movements'] > 0)
        ->take(5)
        ->map(fn (array $row) => number_format($row['movements']).' '.$row['type']->label().' movements')
        ->values()
        ->all();
    $procurementTooltipDetails = [
        'Ordered: ₱'.number_format($spend['ordered']['value'], 2),
        'QC accepted: ₱'.number_format($spend['received']['value'], 2),
        'Outstanding: ₱'.number_format($spend['outstanding']['value'], 2),
    ];
@endphp

<x-app-layout>
    <x-ui.page-header
        title="Inventory Analytics"
        subtitle="Healthcare inventory overview and key metrics."
        :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Reports' => null]">
        <x-slot name="actions">
            <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-white/90 px-4 py-3 text-xs font-medium text-neutral-600 shadow-xs dark:border-neutral-700 dark:bg-neutral-900/90 dark:text-neutral-300">
                <span class="inline-flex items-center gap-2">
                    <x-ui.icon name="calendar" class="h-4 w-4 text-primary-600 dark:text-primary-300" />
                    <span>Snapshot</span>
                </span>
                <span class="h-5 w-px bg-neutral-200 dark:bg-neutral-700" aria-hidden="true"></span>
                <a href="{{ route('inventory.items') }}" class="font-semibold text-primary-700 hover:text-primary-800 hover:underline focus-visible:rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-primary-300 dark:hover:text-primary-200">
                    Live Inventory View
                </a>
            </div>

            {{-- Dashboard Timeline Filter Dropdown --}}
            <div x-data="dashboardTimelineFilter({
                    period: '{{ $currentPeriod }}',
                    from: '{{ $currentFrom ?? $period['from']->format('Y-m-d') }}',
                    to: '{{ $currentTo ?? $period['to']->format('Y-m-d') }}',
                    isCustom: {{ !empty($period['is_custom']) ? 'true' : 'false' }},
                    dashboardUrl: '{{ route('inventory.reports') }}',
                    today: '{{ now()->format('Y-m-d') }}'
                 })"
                 x-cloak
                 class="hidden">
                <button type="button" @click="isOpen = !isOpen"
                        class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold rounded-md border border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50 hover:text-neutral-900 shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-primary-500">
                    <x-ui.icon name="calendar" class="w-4 h-4 text-primary-600 shrink-0" />
                    <span class="max-w-[180px] truncate">
                        @if (!empty($period['is_custom']))
                            Custom: {{ $period['from']->format('M d') }} — {{ $period['to']->format('M d, Y') }}
                        @else
                            {{ $period['days'] == 1 ? 'Today' : ($period['days'] == 365 ? 'Last 12 months' : 'Last '.$period['days'].' days') }}
                        @endif
                    </span>
                    <x-ui.icon name="chevron-down" class="w-3.5 h-3.5 text-neutral-400 shrink-0 transition-transform duration-200"
                               ::class="isOpen ? 'rotate-180' : ''" />
                </button>

                {{-- Dropdown Menu --}}
                <div x-show="isOpen"
                     @click.outside="isOpen = false"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 z-40 mt-1.5 w-72 sm:w-80 rounded-xl bg-white shadow-xl border border-neutral-200 p-3 text-neutral-800 space-y-3">

                    <div class="flex items-center justify-between pb-2 border-b border-neutral-100">
                        <span class="text-xs font-bold text-neutral-800 flex items-center gap-1.5">
                            <x-ui.icon name="funnel" class="w-3.5 h-3.5 text-primary-600" />
                            <span>Dashboard Timeline</span>
                        </span>
                        <span class="text-[10px] text-neutral-400 font-medium">Filter on-screen data</span>
                    </div>

                    {{-- Error banner --}}
                    <div x-show="error" x-cloak
                         class="p-2 rounded-lg bg-danger-50 border border-danger-200 text-danger-800 text-[11px] font-medium"
                         x-text="error"></div>

                    {{-- Timeline options grid --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-neutral-600 mb-1.5">Preset Windows</label>
                        <div class="grid grid-cols-2 gap-1.5">
                            <button type="button" @click="selectPreset('1')"
                                    :class="period === '1' && !isCustom ? 'bg-primary-50 text-primary-700 font-bold border-primary-300' : 'bg-neutral-50 hover:bg-neutral-100 text-neutral-700 border-neutral-200'"
                                    class="px-2.5 py-1.5 text-xs rounded-lg border text-left transition-colors">
                                Today
                            </button>
                            <button type="button" @click="selectPreset('7')"
                                    :class="period === '7' && !isCustom ? 'bg-primary-50 text-primary-700 font-bold border-primary-300' : 'bg-neutral-50 hover:bg-neutral-100 text-neutral-700 border-neutral-200'"
                                    class="px-2.5 py-1.5 text-xs rounded-lg border text-left transition-colors">
                                Last 7 days
                            </button>
                            <button type="button" @click="selectPreset('30')"
                                    :class="period === '30' && !isCustom ? 'bg-primary-50 text-primary-700 font-bold border-primary-300' : 'bg-neutral-50 hover:bg-neutral-100 text-neutral-700 border-neutral-200'"
                                    class="px-2.5 py-1.5 text-xs rounded-lg border text-left transition-colors">
                                Last 30 days
                            </button>
                            <button type="button" @click="selectPreset('90')"
                                    :class="period === '90' && !isCustom ? 'bg-primary-50 text-primary-700 font-bold border-primary-300' : 'bg-neutral-50 hover:bg-neutral-100 text-neutral-700 border-neutral-200'"
                                    class="px-2.5 py-1.5 text-xs rounded-lg border text-left transition-colors">
                                Last 90 days
                            </button>
                            <button type="button" @click="selectPreset('365')"
                                    :class="period === '365' && !isCustom ? 'bg-primary-50 text-primary-700 font-bold border-primary-300' : 'bg-neutral-50 hover:bg-neutral-100 text-neutral-700 border-neutral-200'"
                                    class="px-2.5 py-1.5 text-xs rounded-lg border text-left transition-colors">
                                Last 12 months
                            </button>
                            <button type="button" @click="selectPreset('all')"
                                    :class="period === 'all' && !isCustom ? 'bg-primary-50 text-primary-700 font-bold border-primary-300' : 'bg-neutral-50 hover:bg-neutral-100 text-neutral-700 border-neutral-200'"
                                    class="px-2.5 py-1.5 text-xs rounded-lg border text-left transition-colors">
                                All time
                            </button>
                        </div>
                    </div>

                    {{-- Custom Range Section --}}
                    <div class="pt-2 border-t border-neutral-100">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[11px] font-semibold text-neutral-600">Custom Date Range</span>
                            <button type="button" @click="isCustom = true; period = 'custom'"
                                    class="text-[11px] text-primary-600 hover:text-primary-800 font-medium underline">
                                Select custom
                            </button>
                        </div>

                        <div x-show="isCustom" class="space-y-2.5 mt-1.5">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-medium text-neutral-500 mb-0.5">Start Date</label>
                                    <input type="date" x-model="from" max="{{ now()->format('Y-m-d') }}"
                                           class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1 px-2 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-medium text-neutral-500 mb-0.5">End Date</label>
                                    <input type="date" x-model="to" max="{{ now()->format('Y-m-d') }}"
                                           class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1 px-2 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                </div>
                            </div>

                            <button type="button" @click="applyCustom()"
                                    class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg text-white bg-primary-600 hover:bg-primary-700 shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <x-ui.icon name="calendar" class="w-3.5 h-3.5" />
                                <span>Apply to Dashboard</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Single Primary Generate Report Button --}}
            @can(\App\Enums\Permission::ManageScheduledReports->value)
                <x-ui.button variant="secondary" icon="calendar" :href="route('inventory.reports.schedules')" class="print:hidden">
                    Scheduled Reports
                </x-ui.button>
            @endcan
            <x-ui.button icon="arrow-down-tray" class="print:hidden"
                         @click="$dispatch('open-report-modal')">
                Generate Report
            </x-ui.button>
        </x-slot>
    </x-ui.page-header>

    <x-ui.validation-summary />

    <form method="GET" action="{{ route('inventory.reports') }}"
          x-data="{ period: @js($currentPeriod) }"
          class="rounded-xl border border-neutral-200 bg-white p-3 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 print:hidden sm:p-4">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-neutral-500 dark:text-neutral-400">Filters apply to all charts and tables.</p>
            @if ($activeFilters !== [] || $currentPeriod !== '30')
                <a href="{{ route('inventory.reports') }}" class="shrink-0 text-xs font-semibold text-primary-700 hover:text-primary-800 dark:text-primary-300 dark:hover:text-primary-200">Clear all</a>
            @endif
        </div>

        <div class="mt-3 grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7">
            <label class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                <span class="mb-1 block">Reporting period</span>
                <select name="period" x-model="period" class="w-full rounded-lg border-neutral-300 py-2 pl-3 pr-10 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800">
                    @foreach ($periodOptions as $value => $label)
                        <option value="{{ $value }}" @selected($currentPeriod === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                <span class="mb-1 block">Category</span>
                <select name="category_id" class="w-full rounded-lg border-neutral-300 py-2 pl-3 pr-10 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($activeFilters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                <span class="mb-1 block">Storage location</span>
                <select name="storage_location_id" class="w-full rounded-lg border-neutral-300 py-2 pl-3 pr-10 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">All locations</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) ($activeFilters['storage_location_id'] ?? '') === (string) $location->id)>{{ $location->name }}{{ $location->status === 'inactive' ? ' (Inactive)' : '' }}</option>
                    @endforeach
                </select>
            </label>

            <label class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                <span class="mb-1 block">Stock status</span>
                <select name="stock_status" class="w-full rounded-lg border-neutral-300 py-2 pl-3 pr-10 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">All statuses</option>
                    <option value="in_stock" @selected(($activeFilters['stock_status'] ?? '') === 'in_stock')>In stock</option>
                    <option value="low_stock" @selected(($activeFilters['stock_status'] ?? '') === 'low_stock')>Low stock</option>
                    <option value="out_of_stock" @selected(($activeFilters['stock_status'] ?? '') === 'out_of_stock')>Out of stock</option>
                </select>
            </label>

            <label class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                <span class="mb-1 block">Movement type</span>
                <select name="movement_type" class="w-full rounded-lg border-neutral-300 py-2 pl-3 pr-10 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">All movement types</option>
                    @foreach ($movementTypes as $movementType)
                        <option value="{{ $movementType->value }}" @selected(($activeFilters['movement_type'] ?? '') === $movementType->value)>{{ $movementType->label() }}</option>
                    @endforeach
                </select>
            </label>

            @if ($canViewFinancialData)
                <label class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                    <span class="mb-1 block">Supplier</span>
                    <select name="supplier_id" class="w-full rounded-lg border-neutral-300 py-2 pl-3 pr-10 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) ($activeFilters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <x-ui.button type="submit" icon="funnel" class="w-full xl:self-end">Apply filters</x-ui.button>
        </div>

        <div x-show="period === 'custom'" x-cloak class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:max-w-xl">
            <label class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                <span class="mb-1 block">From</span>
                <input type="date" name="from" value="{{ $currentFrom }}" max="{{ now()->format('Y-m-d') }}"
                       :required="period === 'custom'"
                       class="w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800">
            </label>
            <label class="text-xs font-semibold text-neutral-700 dark:text-neutral-300">
                <span class="mb-1 block">To</span>
                <input type="date" name="to" value="{{ $currentTo }}" max="{{ now()->format('Y-m-d') }}"
                       :required="period === 'custom'"
                       class="w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-800">
            </label>
        </div>

    </form>

    {{-- ------------------------------------------------ Report Configuration & Generation Overlay Modal --}}
    <div id="report-generator"
         x-data="reportGenerator({
             initialPeriod: '{{ $currentPeriod }}',
             initialFrom: '{{ $currentFrom ?? $period['from']->format('Y-m-d') }}',
             initialTo: '{{ $currentTo ?? $period['to']->format('Y-m-d') }}',
             initialCategoryId: @js((string) ($activeFilters['category_id'] ?? '')),
             initialLocationId: @js((string) ($activeFilters['storage_location_id'] ?? '')),
             initialSupplierId: @js((string) ($activeFilters['supplier_id'] ?? '')),
             initialMovementType: @js($activeFilters['movement_type'] ?? ''),
             initialStockStatus: @js($activeFilters['stock_status'] ?? 'all'),
             initialReportType: 'stock_status',
             generateUrl: '{{ route('inventory.reports.generate') }}',
             dashboardUrl: '{{ route('inventory.reports') }}',
             todayDate: '{{ now()->format('Y-m-d') }}',
             canViewFinancial: @json($canViewFinancialData)
         })"
         x-on:open-report-modal.window="openModal()"
         x-on:close-report-modal.window="closeModal()"
         x-on:keydown.escape.window="closeModal()"
         x-show="isOpen"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto p-4 sm:p-6 lg:p-8 flex items-center justify-center print:hidden"
         role="dialog"
         aria-modal="true"
         aria-labelledby="report-modal-title">

        {{-- Backdrop --}}
        <div x-show="isOpen"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closeModal()"
             class="fixed inset-0 bg-neutral-900/60 backdrop-blur-sm transition-opacity"
             aria-hidden="true"></div>

        {{-- Modal Dialog Panel --}}
        <div x-show="isOpen"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative w-full max-w-4xl bg-white rounded-2xl shadow-2xl border border-neutral-200 overflow-hidden flex flex-col max-h-[92vh] my-auto z-10">

            {{-- Modal Header --}}
            <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-neutral-200 bg-white flex items-center justify-between gap-4 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-primary-50 border border-primary-100 flex items-center justify-center text-primary-600 shrink-0">
                        <x-ui.icon name="document-chart-bar" class="w-5 h-5" />
                    </div>
                    <div class="min-w-0">
                        <h2 id="report-modal-title" class="text-base font-bold text-neutral-900 truncate">
                            Report Generator & Timeline Controls
                        </h2>
                        <p class="text-xs text-neutral-500 truncate mt-0.5">
                            Customize report parameters, dynamic filters, sorting, and export formats.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" @click="resetFilters()"
                            class="text-xs text-neutral-500 hover:text-neutral-800 underline font-medium transition-colors hidden sm:inline-block mr-2">
                        Reset Defaults
                    </button>
                    <button type="button" @click="closeModal()"
                            class="p-2 -mr-1 rounded-lg text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            aria-label="Close dialog">
                        <x-ui.icon name="x-mark" class="w-5 h-5" />
                    </button>
                </div>
            </div>

            {{-- Modal Body: Scrollable Form Content --}}
            <div class="p-5 sm:p-6 overflow-y-auto space-y-6">
                {{-- Feedback / Alert Banners --}}
                <div x-show="errorMessage" x-cloak
                     class="flex items-start gap-2.5 p-3.5 rounded-xl bg-danger-50 border border-danger-200 text-danger-800 text-xs transition-all">
                    <x-ui.icon name="exclamation-circle" class="w-4 h-4 text-danger-600 shrink-0 mt-0.5" />
                    <div class="flex-1 font-medium" x-text="errorMessage"></div>
                    <button type="button" @click="errorMessage = ''" class="text-danger-500 hover:text-danger-700 p-0.5">
                        <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                    </button>
                </div>

                <div x-show="successMessage" x-cloak
                     class="flex items-start gap-2.5 p-3.5 rounded-xl bg-success-50 border border-success-200 text-success-800 text-xs transition-all">
                    <x-ui.icon name="check-circle" class="w-4 h-4 text-success-600 shrink-0 mt-0.5" />
                    <div class="flex-1 font-medium" x-text="successMessage"></div>
                    <button type="button" @click="successMessage = ''" class="text-success-500 hover:text-success-700 p-0.5">
                        <x-ui.icon name="x-mark" class="w-3.5 h-3.5" />
                    </button>
                </div>

                {{-- Section 1: Scope & Timeline --}}
                <div>
                    <div class="flex items-center justify-between pb-2 mb-3 border-b border-neutral-100">
                        <span class="text-xs font-bold text-neutral-800 uppercase tracking-wider flex items-center gap-1.5">
                            <x-ui.icon name="document-text" class="w-3.5 h-3.5 text-primary-600" />
                            <span>1. Report Module & Timeline Window</span>
                        </span>
                        <span class="text-[11px] text-neutral-400">Required fields</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
                        {{-- Report Type Selection --}}
                        <div class="md:col-span-6">
                            <label class="block text-xs font-semibold text-neutral-700 mb-1.5 flex items-center justify-between">
                                <span>Report Module <span class="text-danger-500">*</span></span>
                                <span class="text-[10px] text-neutral-400 font-normal">10 modules available</span>
                            </label>
                            <select x-model="reportType" @change="onReportTypeChange()"
                                    class="w-full text-xs font-medium rounded-lg border-neutral-300 bg-white py-2 focus:border-primary-500 focus:ring-primary-500 shadow-sm text-neutral-900">
                                <optgroup label="Complete Dossier">
                                    <option value="all">All Reports (Complete Hospital Dossier)</option>
                                </optgroup>
                                <optgroup label="Stock & Valuation">
                                    <option value="stock_status">Stock Status & Health</option>
                                    <option value="valuation">Inventory Valuation by Category</option>
                                    <option value="stock_by_location">Stock Distribution by Storage Location</option>
                                    <option value="expiry_exposure">Expiry Exposure & Risk Batches</option>
                                </optgroup>
                                <optgroup label="Movements & Consumption">
                                    <option value="movement_history">Stock Movement History & Ledger</option>
                                    <option value="most_consumed">Most Consumed Items (Usage Velocity)</option>
                                    <option value="movements_by_type">Activity by Movement Type</option>
                                </optgroup>
                                @if ($canViewFinancialData)
                                <optgroup label="Procurement & Financial (Protected)">
                                    <option value="procurement_expense">Procurement Expense Breakdown</option>
                                    <option value="spend_by_supplier">PO Commitments by Supplier & Fulfilment</option>
                                </optgroup>
                                @endif
                            </select>
                            <p class="text-[11px] text-neutral-500 mt-1.5" x-text="reportModuleDescription"></p>
                        </div>

                        {{-- Timeline / Window --}}
                        <div class="md:col-span-6">
                            <label class="block text-xs font-semibold text-neutral-700 mb-1.5 flex items-center justify-between">
                                <span>Timeline / Window <span class="text-danger-500">*</span></span>
                                <span class="text-[10px] text-neutral-400 font-normal">Presets & Custom</span>
                            </label>
                            <select x-model="period" @change="onPeriodChange()"
                                    class="w-full text-xs font-medium rounded-lg border-neutral-300 bg-white py-2 focus:border-primary-500 focus:ring-primary-500 shadow-sm text-neutral-900">
                                <option value="1">Today</option>
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                                <option value="365">Last 12 months</option>
                                <option value="all">All time</option>
                                <option value="custom">Custom Date Range...</option>
                            </select>

                            {{-- Custom Date Range Pickers --}}
                            <div x-show="period === 'custom'" x-cloak class="grid grid-cols-2 gap-2 mt-2.5">
                                <div>
                                    <label class="block text-[11px] font-medium text-neutral-700 mb-1 flex items-center justify-between">
                                        <span>From Date</span>
                                        <span class="text-[10px] text-neutral-400 font-normal">start</span>
                                    </label>
                                    <input type="date" x-model="fromDate" max="{{ now()->format('Y-m-d') }}"
                                           class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-neutral-700 mb-1 flex items-center justify-between">
                                        <span>To Date</span>
                                        <span class="text-[10px] text-neutral-400 font-normal">end</span>
                                    </label>
                                    <input type="date" x-model="toDate" max="{{ now()->format('Y-m-d') }}"
                                           class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                </div>
                            </div>

                            {{-- Active Window Badge for Presets --}}
                            <div x-show="period !== 'custom'" class="mt-2.5 flex items-center justify-between p-2 rounded-lg bg-neutral-50 border border-neutral-200">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-ui.icon name="calendar" class="w-4 h-4 text-primary-600 shrink-0" />
                                    <div class="min-w-0">
                                        <div class="text-[11px] font-semibold text-neutral-800 truncate" x-text="computedWindowText"></div>
                                        <div class="text-[10px] text-neutral-500 truncate">
                                            <span x-show="isPointInTimeReport()">Point-in-time catalogue snapshot (Dates apply to audit/history)</span>
                                            <span x-show="!isPointInTimeReport()">Historical transaction boundaries</span>
                                        </div>
                                    </div>
                                </div>
                                <span class="text-[10px] font-medium px-2 py-0.5 rounded bg-white border border-neutral-200 text-neutral-600 shrink-0">
                                    Active Window
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section 2: Dynamic Filters & Sorting --}}
                <div>
                    <div class="flex items-center justify-between pb-2 mb-3 border-b border-neutral-100">
                        <span class="text-xs font-bold text-neutral-800 uppercase tracking-wider flex items-center gap-1.5">
                            <x-ui.icon name="funnel" class="w-3.5 h-3.5 text-primary-600" />
                            <span>2. Dynamic Filters & Sorting</span>
                        </span>
                        <span class="text-[11px] text-neutral-400">Only relevant parameters shown for active module</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        {{-- Category Filter --}}
                        <div x-show="hasCategoryFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Item Category</label>
                            <select x-model="categoryId"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="">All Categories</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }} ({{ $category->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Storage Location Filter --}}
                        <div x-show="hasLocationFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Storage Location</label>
                            <select x-model="locationId"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="">All Locations</option>
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->code }}){{ $location->status === 'inactive' ? ' [Inactive]' : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Supplier Filter --}}
                        @if ($canViewFinancialData)
                        <div x-show="hasSupplierFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Supplier / Vendor</label>
                            <select x-model="supplierId"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="">All Suppliers</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        {{-- Movement Type Filter --}}
                        <div x-show="hasMovementTypeFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Movement Type</label>
                            <select x-model="movementType"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="">All Movement Types</option>
                                @foreach ($movementTypes as $mType)
                                    <option value="{{ $mType->value }}">{{ $mType->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Stock Status Filter --}}
                        <div x-show="hasStockStatusFilter()">
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Stock Status</label>
                            <select x-model="status"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="all">All Levels</option>
                                <option value="in_stock">In Stock Only</option>
                                <option value="low_stock">Low Stock Only</option>
                                <option value="out_of_stock">Out of Stock Only</option>
                            </select>
                        </div>

                        {{-- Dynamic Sort Field --}}
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Sort By</label>
                            <select x-model="sortBy"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <template x-for="opt in currentSortOptions" :key="opt.value">
                                    <option :value="opt.value" x-text="opt.label" :selected="opt.value === sortBy"></option>
                                </template>
                            </select>
                        </div>

                        {{-- Sort Direction --}}
                        <div>
                            <label class="block text-xs font-medium text-neutral-700 mb-1">Order</label>
                            <select x-model="sortDir"
                                    class="w-full text-xs rounded-lg border-neutral-300 bg-white py-1.5 focus:border-primary-500 focus:ring-primary-500 shadow-sm">
                                <option value="desc">Descending (High/New)</option>
                                <option value="asc">Ascending (Low/Old)</option>
                            </select>
                        </div>
                    </div>

                    {{-- Notice when no dynamic filters are active for the module --}}
                    <div x-show="!hasAnyDynamicFilter()" x-cloak class="mt-2 text-xs text-neutral-500 bg-neutral-50 p-2.5 rounded-lg border border-neutral-200">
                        No module-specific category or location filters apply to this report. Sorting and timeline parameters are active.
                    </div>
                </div>

                {{-- Section 3: Export Format --}}
                <div>
                    <div class="flex items-center justify-between pb-2 mb-3 border-b border-neutral-100">
                        <span class="text-xs font-bold text-neutral-800 uppercase tracking-wider flex items-center gap-1.5">
                            <x-ui.icon name="arrow-down-tray" class="w-3.5 h-3.5 text-primary-600" />
                            <span>3. Export Format</span>
                        </span>
                        <span class="text-[11px] text-neutral-400">Choose output destination</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <button type="button" @click="format = 'pdf'"
                                :class="format === 'pdf' ? 'border-primary-600 bg-primary-50/70 text-primary-900 ring-2 ring-primary-500' : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:bg-neutral-50'"
                                class="p-3 rounded-xl border text-left transition-all flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <x-ui.icon name="document-text" class="w-4 h-4 text-primary-600" />
                                <span x-show="format === 'pdf'" class="w-2 h-2 rounded-full bg-primary-600"></span>
                            </div>
                            <div class="mt-2">
                                <div class="text-xs font-bold">PDF</div>
                                <div class="text-[10px] text-neutral-500">Official hospital layout</div>
                            </div>
                        </button>

                        <button type="button" @click="format = 'excel'"
                                :class="format === 'excel' ? 'border-primary-600 bg-primary-50/70 text-primary-900 ring-2 ring-primary-500' : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:bg-neutral-50'"
                                class="p-3 rounded-xl border text-left transition-all flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <x-ui.icon name="table" class="w-4 h-4 text-success-600" />
                                <span x-show="format === 'excel'" class="w-2 h-2 rounded-full bg-primary-600"></span>
                            </div>
                            <div class="mt-2">
                                <div class="text-xs font-bold">Excel (.xls)</div>
                                <div class="text-[10px] text-neutral-500">Structured spreadsheets</div>
                            </div>
                        </button>

                        <button type="button" @click="format = 'csv'"
                                :class="format === 'csv' ? 'border-primary-600 bg-primary-50/70 text-primary-900 ring-2 ring-primary-500' : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:bg-neutral-50'"
                                class="p-3 rounded-xl border text-left transition-all flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <x-ui.icon name="arrow-down-tray" class="w-4 h-4 text-neutral-600" />
                                <span x-show="format === 'csv'" class="w-2 h-2 rounded-full bg-primary-600"></span>
                            </div>
                            <div class="mt-2">
                                <div class="text-xs font-bold">CSV</div>
                                <div class="text-[10px] text-neutral-500">UTF-8 compatible data</div>
                            </div>
                        </button>

                        <button type="button" @click="format = 'json'"
                                :class="format === 'json' ? 'border-primary-600 bg-primary-50/70 text-primary-900 ring-2 ring-primary-500' : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:bg-neutral-50'"
                                class="p-3 rounded-xl border text-left transition-all flex flex-col justify-between">
                            <div class="flex items-center justify-between">
                                <x-ui.icon name="code-bracket" class="w-4 h-4 text-indigo-600" />
                                <span x-show="format === 'json'" class="w-2 h-2 rounded-full bg-primary-600"></span>
                            </div>
                            <div class="mt-2">
                                <div class="text-xs font-bold">JSON</div>
                                <div class="text-[10px] text-neutral-500">API & analytical export</div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="px-5 py-4 sm:px-6 bg-neutral-50 border-t border-neutral-200 flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3 shrink-0">
                <div>
                    <button type="button" @click="closeModal()"
                            class="w-full sm:w-auto px-4 py-2 text-xs font-semibold text-neutral-700 bg-white border border-neutral-300 rounded-lg hover:bg-neutral-100 shadow-sm transition-colors text-center">
                        Cancel
                    </button>
                </div>

                <div class="flex items-center justify-end">
                    <button type="button" @click="generate()" :disabled="loading"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2 text-xs font-bold rounded-lg text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition-all focus:ring-2 focus:ring-primary-500 focus:ring-offset-1">
                        <template x-if="loading">
                            <svg class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </template>
                        <template x-if="!loading">
                            <x-ui.icon name="arrow-down-tray" class="w-3.5 h-3.5 text-white" />
                        </template>
                        <span x-text="loading ? 'Generating Report...' : (format === 'pdf' ? 'Download PDF' : 'Generate & Export Report')"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------ 1. inventory summary --}}

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-12">
        <x-ui.stat
            analytics
            compact
            class="2xl:col-span-3"
            label="Total items"
            :value="number_format($summary['items'])"
            suffix="items"
            icon="cube"
            tone="primary"
            context="Snapshot"
            href="#report-detail-tabs"
            summary="Open the item-level records included in the current filtered snapshot."
            summary-title="Current stock status"
            :details="$stockStatusTooltipDetails"
            :data-drilldown-url="$drilldownUrl('stock_status')"
            data-drilldown-title="Total Items — Inventory Records"
            data-drilldown-focus="items"
            x-on:click.prevent="$dispatch('open-chart-drilldown', { url: $el.dataset.drilldownUrl, title: $el.dataset.drilldownTitle, focus: $el.dataset.drilldownFocus })"
            aria-label="View item-level records for total items"
            chart-label="Item distribution"
            :chart="[
                ['label' => 'In stock', 'value' => $stockStatus['in_stock']['items'], 'tone' => 'success'],
                ['label' => 'Low stock', 'value' => $stockStatus['low_stock']['items'], 'tone' => 'warning'],
                ['label' => 'Out of stock', 'value' => $stockStatus['out_of_stock']['items'], 'tone' => 'danger'],
            ]"
            :breakdown="[
                ['label' => 'In stock', 'value' => number_format($stockStatus['in_stock']['items']).' items', 'tone' => 'success'],
                ['label' => 'Needs attention', 'value' => number_format($summary['needs_attention']).' items', 'tone' => 'warning'],
            ]"
            hint-icon="clipboard-document-list"
            :hint="number_format($summary['units_on_hand']).' units on hand in the filtered snapshot'" />

        @if ($canViewFinancialData)
        <x-ui.stat
            analytics
            compact
            featured
            class="2xl:col-span-6"
            label="Stock valuation"
            prefix="₱"
            :value="number_format($summary['stock_value'], 2)"
            icon="chart-bar"
            tone="success"
            context="Snapshot"
            href="#report-detail-tabs"
            summary="Open the item-level quantities, unit costs, and total values behind this valuation."
            summary-title="Valuation records"
            :details="$stockStatusTooltipDetails"
            :data-drilldown-url="$drilldownUrl('stock_status')"
            data-drilldown-title="Stock Valuation — Inventory Records"
            data-drilldown-focus="valuation"
            x-on:click.prevent="$dispatch('open-chart-drilldown', { url: $el.dataset.drilldownUrl, title: $el.dataset.drilldownTitle, focus: $el.dataset.drilldownFocus })"
            aria-label="View item-level stock valuation records"
            chart-label="Value by stock status"
            :chart="[
                ['label' => 'Healthy', 'value' => $stockStatus['in_stock']['value'], 'tone' => 'success'],
                ['label' => 'Low stock', 'value' => $stockStatus['low_stock']['value'], 'tone' => 'warning'],
                ['label' => 'Out of stock', 'value' => $stockStatus['out_of_stock']['value'], 'tone' => 'danger'],
            ]"
            :breakdown="[
                ['label' => 'Healthy stock', 'value' => '₱'.number_format($stockStatus['in_stock']['value'], 2), 'tone' => 'success'],
                ['label' => 'Low stock', 'value' => '₱'.number_format($stockStatus['low_stock']['value'], 2), 'tone' => 'warning'],
                ['label' => 'Units valued', 'value' => number_format($summary['units_on_hand']), 'tone' => 'neutral'],
            ]"
            hint-icon="document-text"
            hint="Filtered units on hand × unit cost." />
        @endif

        <x-ui.stat
            analytics
            compact
            class="2xl:col-span-3"
            label="Low / out of stock"
            :value="number_format($summary['needs_attention'])"
            suffix="items"
            icon="exclamation-triangle"
            :tone="$summary['needs_attention'] > 0 ? 'warning' : 'success'"
            context="Snapshot"
            href="#inventory-health"
            summary="Open the status breakdown, then select Low stock or Out of stock to inspect its matching items."
            summary-title="Items needing attention"
            :details="[
                number_format($stockStatus['low_stock']['items']).' low-stock items',
                number_format($stockStatus['out_of_stock']['items']).' out-of-stock items',
            ]"
            aria-label="View low-stock and out-of-stock breakdown"
            chart-label="Attention distribution"
            :chart="[
                ['label' => 'Low stock', 'value' => $stockStatus['low_stock']['items'], 'tone' => 'warning'],
                ['label' => 'Out of stock', 'value' => $stockStatus['out_of_stock']['items'], 'tone' => 'danger'],
            ]"
            :breakdown="[
                ['label' => 'Low stock', 'value' => number_format($stockStatus['low_stock']['items']), 'tone' => 'warning'],
                ['label' => 'Out of stock', 'value' => number_format($stockStatus['out_of_stock']['items']), 'tone' => 'danger'],
            ]"
            hint="At or below reorder level, or out of stock." />

        <x-ui.stat
            analytics
            compact
            class="2xl:col-span-2"
            label="Reserved units"
            :value="number_format($summary['reserved_units'])"
            suffix="units"
            icon="clipboard-document-list"
            tone="primary"
            context="Snapshot"
            href="#report-detail-tabs"
            summary="Open only the filtered items that currently have reserved units."
            summary-title="Reserved-unit breakdown"
            :details="$reservedTooltipDetails"
            :data-drilldown-url="$drilldownUrl('reserved')"
            data-drilldown-title="Reserved Units — Inventory Records"
            data-drilldown-focus="reserved"
            x-on:click.prevent="$dispatch('open-chart-drilldown', { url: $el.dataset.drilldownUrl, title: $el.dataset.drilldownTitle, focus: $el.dataset.drilldownFocus })"
            aria-label="View item-level reserved unit records"
            chart-label="Reserved by stock status"
            :chart="[
                ['label' => 'In stock', 'value' => $stockStatus['in_stock']['reserved'], 'tone' => 'success'],
                ['label' => 'Low stock', 'value' => $stockStatus['low_stock']['reserved'], 'tone' => 'warning'],
                ['label' => 'Out of stock', 'value' => $stockStatus['out_of_stock']['reserved'], 'tone' => 'danger'],
            ]"
            :breakdown="[
                ['label' => 'In-stock reserved', 'value' => number_format($stockStatus['in_stock']['reserved']), 'tone' => 'success'],
                ['label' => 'At-risk reserved', 'value' => number_format($stockStatus['low_stock']['reserved'] + $stockStatus['out_of_stock']['reserved']), 'tone' => 'warning'],
            ]"
            hint="Committed elsewhere and unavailable to issue." />

        <x-ui.stat
            analytics
            compact
            class="2xl:col-span-2"
            label="Expiry risk units"
            :value="number_format($expiryRiskUnits)"
            suffix="units"
            icon="calendar"
            :tone="$expiryRiskUnits > 0 ? 'warning' : 'success'"
            context="Snapshot"
            href="#report-detail-tabs"
            summary="Open the dated batches currently contributing to expiry exposure."
            summary-title="Expiry exposure"
            :details="$expiryTooltipDetails"
            :data-drilldown-url="$drilldownUrl('expiry')"
            data-drilldown-title="Expiry Risk Units — Affected Batches"
            data-drilldown-focus="expiry"
            x-on:click.prevent="$dispatch('open-chart-drilldown', { url: $el.dataset.drilldownUrl, title: $el.dataset.drilldownTitle, focus: $el.dataset.drilldownFocus })"
            aria-label="View batches contributing to expiry risk"
            chart-label="Expiry exposure"
            :chart="[
                ['label' => 'Expired', 'value' => $expiry['expired']['units'], 'tone' => 'danger'],
                ['label' => 'Expiring soon', 'value' => $expiry['expiring_soon']['units'], 'tone' => 'warning'],
            ]"
            :breakdown="[
                ['label' => 'Expired', 'value' => number_format($expiry['expired']['units']), 'tone' => 'danger'],
                ['label' => 'Expiring soon', 'value' => number_format($expiry['expiring_soon']['units']), 'tone' => 'warning'],
            ]"
            :hint="number_format($expiry['expired']['batches'] + $expiry['expiring_soon']['batches']).' affected batches'" />

        <x-ui.stat
            analytics
            compact
            class="2xl:col-span-2"
            label="Stock movements"
            :value="number_format($movementTotals['movements'])"
            suffix="events"
            icon="arrows-right-left"
            tone="primary"
            :context="$period['days'].'d'"
            href="#report-detail-tabs"
            summary="Open the matching stock movement ledger for the active filters and reporting period."
            summary-title="Movement activity"
            :details="$movementTooltipDetails"
            :data-drilldown-url="$drilldownUrl('movement_type')"
            data-drilldown-title="Stock Movements — Ledger Records"
            data-drilldown-focus="movement"
            x-on:click.prevent="$dispatch('open-chart-drilldown', { url: $el.dataset.drilldownUrl, title: $el.dataset.drilldownTitle, focus: $el.dataset.drilldownFocus })"
            aria-label="View stock movement ledger records"
            chart-label="Events by movement type"
            chart-type="bars"
            :chart="$movementsByType
                ->sortByDesc('movements')
                ->take(4)
                ->map(fn (array $row) => [
                    'label' => $row['type']->label(),
                    'value' => $row['movements'],
                    'tone' => 'primary',
                ])
                ->values()
                ->all()"
            hint-icon="calendar"
            :hint="$period['description']" />

        @if ($canViewFinancialData)
        <x-ui.stat
            analytics
            compact
            featured
            class="2xl:col-span-6"
            label="Procurement spending"
            prefix="₱"
            :value="number_format($spend['ordered']['value'], 2)"
            icon="truck"
            tone="primary"
            :context="$period['days'].'d'"
            href="#report-detail-tabs"
            summary="Open the purchase orders included in procurement spending for the active reporting period."
            summary-title="Procurement values"
            :details="$procurementTooltipDetails"
            :data-drilldown-url="$drilldownUrl('supplier')"
            data-drilldown-title="Procurement Spending — Purchase Orders"
            data-drilldown-focus="spending"
            x-on:click.prevent="$dispatch('open-chart-drilldown', { url: $el.dataset.drilldownUrl, title: $el.dataset.drilldownTitle, focus: $el.dataset.drilldownFocus })"
            aria-label="View purchase orders included in procurement spending"
            chart-label="Procurement value comparison"
            chart-type="comparison"
            :chart="[
                ['label' => 'Ordered', 'value' => $spend['ordered']['value'], 'tone' => 'primary'],
                ['label' => 'QC accepted', 'value' => $spend['received']['value'], 'tone' => 'success'],
                ['label' => 'Outstanding', 'value' => $spend['outstanding']['value'], 'tone' => 'warning'],
            ]"
            :breakdown="[
                ['label' => 'Ordered', 'value' => '₱'.number_format($spend['ordered']['value'], 2), 'tone' => 'primary'],
                ['label' => 'QC accepted', 'value' => '₱'.number_format($spend['received']['value'], 2), 'tone' => 'success'],
                ['label' => 'Outstanding', 'value' => '₱'.number_format($spend['outstanding']['value'], 2), 'tone' => 'warning'],
            ]"
            hint-icon="clipboard-document-list"
            :hint="number_format($spend['ordered']['orders']).' purchase orders in period'" />
        @endif
    </div>

    {{-- --------------------------------------------------- 2. stock status & executive health overview --}}

    <div class="grid items-stretch gap-4 lg:grid-cols-3">
        <x-ui.card id="inventory-health" class="h-full scroll-mt-4 [&>header]:py-3 [&>div]:p-4">
            <x-slot name="header">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/50">
                        <x-ui.icon name="chart-bar" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Inventory Health</h2>
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Current item count by stock status.</p>
                    </div>
                </div>
            </x-slot>
            <x-slot name="actions">
                <x-ui.button variant="ghost" size="sm" :href="route('inventory.items')">View items &rarr;</x-ui.button>
            </x-slot>
            @php
                $bars = [
                    'in_stock' => ['label' => 'In stock', 'variant' => 'success', 'bar' => 'bg-success-500'],
                    'low_stock' => ['label' => 'Low stock', 'variant' => 'warning', 'bar' => 'bg-warning-500'],
                    'out_of_stock' => ['label' => 'Out of stock', 'variant' => 'danger', 'bar' => 'bg-danger-500'],
                ];
            @endphp

            <div class="space-y-2">
                @foreach ($bars as $key => $bar)
                    @php
                        $bucket = $stockStatus[$key];
                        $share = $summary['items'] > 0
                            ? round(($bucket['items'] / $summary['items']) * 100)
                            : 0;
                    @endphp
                    <button type="button"
                       data-drilldown-url="{{ $drilldownUrl('stock_status', $key) }}"
                       data-drilldown-title="Inventory Health — {{ $bar['label'] }}"
                       data-drilldown-focus="stock"
                       x-on:click.prevent="$dispatch('open-chart-drilldown', { url: $el.dataset.drilldownUrl, title: $el.dataset.drilldownTitle, focus: $el.dataset.drilldownFocus })"
                       class="group block w-full rounded-lg p-1.5 text-left transition hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-neutral-800/60"
                       aria-label="View {{ $bar['label'] }} inventory records">
                        <div class="flex items-center justify-between gap-3">
                            <span>
                                <x-ui.badge :variant="$bar['variant']" dot>{{ $bar['label'] }}</x-ui.badge>
                            </span>
                            <span class="text-sm font-semibold tabular-nums text-neutral-900 dark:text-neutral-100">
                                {{ number_format($bucket['items']) }}
                                <span class="text-xs font-normal text-neutral-500">({{ $share }}%)</span>
                            </span>
                        </div>

                        <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800" aria-hidden="true">
                            <div class="h-full rounded-full {{ $bar['bar'] }} transition-all group-hover:brightness-90" style="width: {{ $share }}%"></div>
                        </div>

                        <p class="mt-1 text-xs text-neutral-500">
                            {{ number_format($bucket['units']) }} units
                            @if ($canViewFinancialData)
                                &middot; ₱{{ number_format($bucket['value'], 2) }}
                            @endif
                        </p>
                    </button>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card class="h-full [&>header]:py-3 [&>div]:p-4">
            <x-slot name="header">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950/80 dark:text-emerald-300 dark:ring-emerald-800/50">
                        <x-ui.icon name="shield-check" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Expiry Exposure</h2>
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Batches still holding stock, valued at risk.</p>
                    </div>
                </div>
            </x-slot>
            <x-slot name="actions">
                <x-ui.button
                    variant="ghost"
                    size="sm"
                    class="print:hidden"
                    @click="$dispatch('open-report-tab', { tab: 'expiry' })">
                    View batches &rarr;
                </x-ui.button>
            </x-slot>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-lg border border-danger-200 bg-danger-50 px-3 py-2.5 dark:border-danger-900 dark:bg-danger-950/40">
                    <p class="text-xs font-semibold uppercase tracking-wide text-danger-700">Expired</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-danger-800">
                        {{ number_format($expiry['expired']['units']) }}
                        <span class="text-xs font-normal">units</span>
                    </p>
                    <p class="text-xs text-danger-700">
                        {{ $expiry['expired']['batches'] }} batches
                        @if ($canViewFinancialData)
                            &middot; ₱{{ number_format($expiry['expired']['value'], 2) }}
                        @endif
                    </p>
                </div>

                <div class="rounded-lg border border-warning-200 bg-warning-50 px-3 py-2.5 dark:border-warning-900 dark:bg-warning-950/40">
                    <p class="text-xs font-semibold uppercase tracking-wide text-warning-700">Expiring soon</p>
                    <p class="mt-1 text-lg font-semibold tabular-nums text-warning-800">
                        {{ number_format($expiry['expiring_soon']['units']) }}
                        <span class="text-xs font-normal">units</span>
                    </p>
                    <p class="text-xs text-warning-700">
                        {{ $expiry['expiring_soon']['batches'] }} batches
                        &middot; 1&ndash;90 days
                        @if ($canViewFinancialData)
                            &middot; ₱{{ number_format($expiry['expiring_soon']['value'], 2) }}
                        @endif
                    </p>
                    <p class="mt-1 text-xs font-medium text-danger-700">
                        {{ $expiry['critical']['batches'] }} Critical / Near Expiry (1&ndash;30 days)
                    </p>
                </div>
            </div>

            @if ($expiry['rows']->isNotEmpty())
                <ul class="mt-3 space-y-2 border-t border-neutral-200 pt-3">
                    @foreach ($expiry['rows']->take(3) as $batch)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-neutral-900">
                                    {{ $batch->item?->name ?? 'Unknown item' }}
                                </span>
                                <span class="block text-xs text-neutral-500">
                                    {{ $batch->batch_number }} &middot; {{ number_format((int) $batch->units_on_hand) }} units
                                </span>
                            </span>
                            <x-ui.badge :status="$batch->expiryClassification()">
                                {{ $batch->expiryStatusLabel() }} &middot; {{ $batch->expiry_date?->format('M d, Y') }}
                            </x-ui.badge>
                        </li>
                    @endforeach
                </ul>
                @if ($expiry['rows']->count() > 3)
                    <p class="mt-2 text-xs text-neutral-500 text-right print:hidden">
                        +{{ $expiry['rows']->count() - 3 }} more in detailed tab below
                    </p>
                @endif
            @else
                <p class="mt-3 rounded-lg border border-primary-100 bg-primary-50/50 px-3 py-2 text-xs text-neutral-600 dark:border-primary-900 dark:bg-primary-950/30 dark:text-neutral-300">
                    No dated batch is expired or inside its warning window.
                </p>
            @endif
        </x-ui.card>

        <x-ui.card class="h-full [&>header]:py-3 [&>div]:p-4">
            <x-slot name="header">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/50">
                        <x-ui.icon name="arrows-right-left" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Movement Activity</h2>
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $period['description'] }}</p>
                    </div>
                </div>
            </x-slot>
            <x-slot name="actions">
                <x-ui.button variant="ghost" size="sm" :href="route('inventory.stock-movements')">View ledger &rarr;</x-ui.button>
            </x-slot>
            @php $movementMax = max(1, (int) $movementsByType->max('movements')); @endphp
            <div class="max-h-56 space-y-1 overflow-y-auto pr-1">
                @foreach ($movementsByType as $row)
                    @php $movementShare = round(($row['movements'] / $movementMax) * 100); @endphp
                    <button type="button"
                       data-drilldown-url="{{ $drilldownUrl('movement_type', $row['type']->value) }}"
                       data-drilldown-title="Movement Activity — {{ $row['type']->label() }}"
                       data-drilldown-focus="movement"
                       x-on:click.prevent="$dispatch('open-chart-drilldown', { url: $el.dataset.drilldownUrl, title: $el.dataset.drilldownTitle, focus: $el.dataset.drilldownFocus })"
                       class="group block w-full rounded-lg p-1.5 text-left transition hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-neutral-800/60"
                       aria-label="View {{ $row['type']->label() }} movement records">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="truncate font-medium text-neutral-700 dark:text-neutral-300">{{ $row['type']->label() }}</span>
                            <span class="shrink-0 font-semibold tabular-nums text-neutral-900 dark:text-neutral-100">
                                {{ number_format($row['movements']) }}
                                <span class="font-normal text-neutral-500">· {{ number_format($row['units']) }} units</span>
                            </span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800" aria-hidden="true">
                            <div class="h-full rounded-full bg-primary-500 transition-all group-hover:bg-primary-600" style="width: {{ $movementShare }}%"></div>
                        </div>
                    </button>
                @endforeach
            </div>

            <p class="mt-3 rounded-lg border border-primary-100 bg-primary-50/50 px-3 py-2 text-xs text-neutral-600 dark:border-primary-900 dark:bg-primary-950/30 dark:text-neutral-300">
                Counts and units come directly from the filtered stock movement ledger; zero means no matching event occurred.
            </p>
        </x-ui.card>
    </div>

    {{-- --------------------------------------------------- 3. interactive detailed report center --}}

    <div
        id="report-detail-tabs"
        x-data="{ activeTab: '{{ $canViewFinancialData ? 'valuation' : 'movements' }}' }"
        x-on:open-report-tab.window="activeTab = $event.detail.tab; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
        class="space-y-4">
        {{-- Navigation tab bar (hidden on print) --}}
        <div class="rounded-xl border border-neutral-200 bg-white px-2 py-2 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 print:hidden">
            <nav class="flex flex-wrap items-center gap-1.5" aria-label="Detailed Report Sections">
                <button
                    type="button"
                    x-on:click="activeTab = 'valuation'"
                    :class="activeTab === 'valuation'
                        ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-2xs'
                        : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 border-transparent dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-neutral-100'"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors">
                    <x-ui.icon name="chart-bar" class="w-4 h-4" />
                    <span>Valuation &amp; Locations</span>
                </button>

                @if ($canViewFinancialData)
                <button
                    type="button"
                    x-on:click="activeTab = 'procurement'"
                    :class="activeTab === 'procurement'
                        ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-2xs'
                        : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 border-transparent dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-neutral-100'"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors">
                    <x-ui.icon name="truck" class="w-4 h-4" />
                    <span>Procurement &amp; Spending</span>
                </button>
                @endif

                <button
                    type="button"
                    x-on:click="activeTab = 'movements'"
                    :class="activeTab === 'movements'
                        ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-2xs'
                        : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 border-transparent dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-neutral-100'"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors">
                    <x-ui.icon name="arrows-right-left" class="w-4 h-4" />
                    <span>Movements &amp; Consumption</span>
                </button>

                <button
                    type="button"
                    x-on:click="activeTab = 'expiry'"
                    :class="activeTab === 'expiry'
                        ? 'bg-primary-50 text-primary-700 border-primary-300 font-semibold shadow-2xs'
                        : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 border-transparent dark:text-neutral-300 dark:hover:bg-neutral-800 dark:hover:text-neutral-100'"
                    class="inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors">
                    <x-ui.icon name="exclamation-triangle" class="w-4 h-4" />
                    <span>Expiry Risk Batches</span>
                    @if ($expiry['rows']->isNotEmpty())
                        <span class="rounded-full bg-warning-100 text-warning-800 px-1.5 py-0.2 text-[10px] font-mono">
                            {{ $expiry['rows']->count() }}
                        </span>
                    @endif
                </button>
            </nav>
        </div>

        {{-- Tab 1: Valuation & Locations --}}
        <div x-show="activeTab === 'valuation'" x-cloak class="space-y-4 print:!block">
            <div class="grid gap-4 lg:grid-cols-2">
                <x-ui.card :padding="false">
                    <x-slot name="header">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/50">
                                <x-ui.icon name="chart-bar" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Valuation by Category</h2>
                                <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Where the money is tied up.</p>
                            </div>
                        </div>
                    </x-slot>
                    <x-slot name="actions">
                        <x-ui.button variant="secondary" size="sm" icon="arrow-down-tray" @click="$dispatch('open-report-modal')">Export</x-ui.button>
                    </x-slot>
                    <x-ui.table :sticky-header="false">
                        <x-ui.table.head>
                            <x-ui.table.th class="w-10 px-3 py-2.5">#</x-ui.table.th>
                            <x-ui.table.th class="px-3 py-2.5">Category</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Items</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Units</x-ui.table.th>
                            @if ($canViewFinancialData)
                                <x-ui.table.th numeric class="px-3 py-2.5">Value</x-ui.table.th>
                                <x-ui.table.th numeric class="px-3 py-2.5">Share</x-ui.table.th>
                            @endif
                        </x-ui.table.head>
                        <tbody>
                            @forelse ($valuationByCategory as $row)
                                @php
                                    $categoryShare = $summary['stock_value'] > 0
                                        ? round(($row->value / $summary['stock_value']) * 100)
                                        : null;
                                @endphp
                                <x-ui.table.row>
                                    <x-ui.table.td muted class="px-3 py-2.5 text-xs">{{ $loop->iteration }}</x-ui.table.td>
                                    <x-ui.table.td class="px-3 py-2.5 text-xs font-medium text-neutral-900 dark:text-neutral-100">{{ $row->category }}</x-ui.table.td>
                                    <x-ui.table.td numeric muted class="px-3 py-2.5 text-xs">{{ number_format($row->items) }}</x-ui.table.td>
                                    <x-ui.table.td numeric muted class="px-3 py-2.5 text-xs">{{ number_format($row->units) }}</x-ui.table.td>
                                    @if ($canViewFinancialData)
                                        <x-ui.table.td numeric class="px-3 py-2.5 text-xs font-semibold">₱{{ number_format($row->value, 2) }}</x-ui.table.td>
                                        <x-ui.table.td numeric muted class="px-3 py-2.5 text-xs">
                                            @if ($categoryShare === null)
                                                —
                                            @else
                                                <span class="inline-flex min-w-[7.5rem] items-center justify-end gap-2.5">
                                                    <span>{{ $categoryShare }}%</span>
                                                    <span class="h-1.5 w-20 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800" aria-hidden="true">
                                                        <span class="block h-full rounded-full bg-primary-500" style="width: {{ $categoryShare }}%"></span>
                                                    </span>
                                                </span>
                                            @endif
                                        </x-ui.table.td>
                                    @endif
                                </x-ui.table.row>
                            @empty
                                <x-ui.table.empty
                                    :colspan="$canViewFinancialData ? 6 : 4"
                                    icon="cube"
                                    title="No items yet"
                                    message="Add inventory items and the valuation fills in." />
                            @endforelse
                        </tbody>
                        @if ($valuationByCategory->isNotEmpty())
                            <tfoot>
                                <tr class="border-t border-neutral-200 bg-neutral-50 font-semibold text-neutral-900 dark:border-neutral-800 dark:bg-neutral-800/60 dark:text-neutral-100">
                                    <td colspan="2" class="px-3 py-3 text-xs">Total</td>
                                    <td class="px-3 py-3 text-right text-xs tabular-nums">{{ number_format($summary['items']) }}</td>
                                    <td class="px-3 py-3 text-right text-xs tabular-nums">{{ number_format($summary['units_on_hand']) }}</td>
                                    @if ($canViewFinancialData)
                                        <td class="px-3 py-3 text-right text-xs tabular-nums">₱{{ number_format($summary['stock_value'], 2) }}</td>
                                        <td class="px-3 py-3 text-right text-xs tabular-nums">100%</td>
                                    @endif
                                </tr>
                            </tfoot>
                        @endif
                    </x-ui.table>
                </x-ui.card>

                <x-ui.card :padding="false">
                    <x-slot name="header">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/50">
                                <x-ui.icon name="map-pin" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Stock by Location</h2>
                                <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">What each storage location is holding.</p>
                            </div>
                        </div>
                    </x-slot>
                    <x-slot name="actions">
                        <x-ui.button variant="secondary" size="sm" icon="arrow-down-tray" @click="$dispatch('open-report-modal')">Export</x-ui.button>
                    </x-slot>
                    <x-ui.table :sticky-header="false">
                        <x-ui.table.head>
                            <x-ui.table.th class="w-10 px-3 py-2.5">#</x-ui.table.th>
                            <x-ui.table.th class="px-3 py-2.5">Location</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Items</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Units</x-ui.table.th>
                            @if ($canViewFinancialData)
                                <x-ui.table.th numeric class="px-3 py-2.5">Value</x-ui.table.th>
                            @endif
                            <x-ui.table.th numeric class="px-3 py-2.5">Utilisation</x-ui.table.th>
                        </x-ui.table.head>
                        <tbody>
                            @forelse ($stockByLocation as $row)
                                <x-ui.table.row>
                                    <x-ui.table.td muted class="px-3 py-2.5 text-xs">{{ $loop->iteration }}</x-ui.table.td>
                                    <x-ui.table.td class="px-3 py-2.5 text-xs">
                                        <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $row['location'] }}</span>
                                        @if ($row['code'])
                                            <span class="mt-0.5 block font-mono text-[10px] leading-tight text-neutral-500 dark:text-neutral-400">{{ $row['code'] }}</span>
                                        @endif
                                    </x-ui.table.td>
                                    <x-ui.table.td numeric muted class="px-3 py-2.5 text-xs">{{ number_format($row['items']) }}</x-ui.table.td>
                                    <x-ui.table.td numeric class="px-3 py-2.5 text-xs font-semibold">{{ number_format($row['units']) }}</x-ui.table.td>
                                    @if ($canViewFinancialData)
                                        <x-ui.table.td numeric muted class="px-3 py-2.5 text-xs">₱{{ number_format($row['value'], 2) }}</x-ui.table.td>
                                    @endif
                                    <x-ui.table.td numeric class="px-3 py-2.5 text-xs">
                                        @if ($row['utilisation'] === null)
                                            <span class="text-neutral-400">—</span>
                                        @else
                                            <span class="inline-flex min-w-[8rem] items-center justify-end gap-2.5">
                                                <span>
                                                    <span @class([
                                                        'block font-semibold leading-tight',
                                                        'text-danger-700' => $row['utilisation'] >= 90,
                                                        'text-warning-700' => $row['utilisation'] >= 75 && $row['utilisation'] < 90,
                                                        'text-neutral-800 dark:text-neutral-200' => $row['utilisation'] < 75,
                                                    ])>{{ $row['utilisation'] }}%</span>
                                                    <span class="block text-[10px] leading-tight text-neutral-500 dark:text-neutral-400">of {{ number_format($row['capacity']) }}</span>
                                                </span>
                                                <span class="h-1.5 w-20 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800" aria-hidden="true">
                                                    <span @class([
                                                        'block h-full rounded-full',
                                                        'bg-danger-500' => $row['utilisation'] >= 90,
                                                        'bg-warning-500' => $row['utilisation'] >= 75 && $row['utilisation'] < 90,
                                                        'bg-primary-500' => $row['utilisation'] < 75,
                                                    ]) style="width: {{ min(100, $row['utilisation']) }}%"></span>
                                                </span>
                                            </span>
                                        @endif
                                    </x-ui.table.td>
                                </x-ui.table.row>
                            @empty
                                <x-ui.table.empty
                                    :colspan="$canViewFinancialData ? 6 : 5"
                                    icon="building-storefront"
                                    title="No stock in any location"
                                    message="Record a stock in and the location balances appear here." />
                            @endforelse
                        </tbody>
                    </x-ui.table>
                </x-ui.card>
            </div>
        </div>

        {{-- Tab 2: Procurement & Spending --}}
        @if ($canViewFinancialData)
        <div x-show="activeTab === 'procurement'" x-cloak class="space-y-4 print:!block">
            <section class="space-y-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white shadow-sm">
                        <x-ui.icon name="shopping-cart" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-xl font-bold tracking-tight text-neutral-950 dark:text-white">Procurement Expense</h2>
                        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                            Purchase orders raised in the last {{ $period['days'] }} days, plus everything still outstanding.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="flex items-center gap-3 rounded-xl border border-primary-200 bg-primary-50/50 p-4 dark:border-primary-900 dark:bg-primary-950/30">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                            <x-ui.icon name="clipboard-document-list" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Ordered</p>
                            <p class="mt-1 break-words text-xl font-bold tracking-tight tabular-nums text-neutral-950 dark:text-white">₱{{ number_format($spend['ordered']['value'], 2) }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $spend['ordered']['orders'] }} purchase orders</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-900 dark:bg-success-950/40">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-success-100 text-success-700 dark:bg-success-900 dark:text-success-300">
                            <x-ui.icon name="check" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-success-700">QC Accepted</p>
                            <p class="mt-1 break-words text-xl font-bold tracking-tight tabular-nums text-success-800">₱{{ number_format($spend['received']['value'], 2) }}</p>
                            <p class="mt-0.5 text-xs text-success-700">{{ $spend['received']['orders'] }} purchase orders with accepted goods</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-900 dark:bg-warning-950/40">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-warning-100 text-warning-700 dark:bg-warning-900 dark:text-warning-300">
                            <x-ui.icon name="clock" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-warning-700">Outstanding</p>
                            <p class="mt-1 break-words text-xl font-bold tracking-tight tabular-nums text-warning-800">₱{{ number_format($spend['outstanding']['value'], 2) }}</p>
                            <p class="mt-0.5 text-xs text-warning-700">{{ $spend['outstanding']['orders'] }} awaiting acceptance, all time</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-violet-200 bg-violet-50/60 p-4 dark:border-violet-900 dark:bg-violet-950/40">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-700 dark:bg-violet-900 dark:text-violet-300">
                            <x-ui.icon name="chart-bar" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-violet-700">Average order</p>
                            <p class="mt-1 break-words text-xl font-bold tracking-tight tabular-nums text-neutral-950 dark:text-white">₱{{ number_format($spend['average_order_value'], 2) }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Across orders raised in the window</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-start gap-2 rounded-lg border border-primary-200 bg-primary-50/50 px-3 py-2.5 text-xs text-primary-800 dark:border-primary-900 dark:bg-primary-950/30 dark:text-primary-200">
                    <x-ui.icon name="information-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>
                        Ordered is dated by order creation. QC accepted value is dated by disposition; legacy completed orders use their receipt date.
                        Outstanding is the value still awaiting acceptance across open orders.
                    </p>
                </div>
            </section>

            <x-ui.card>
                <x-slot name="header">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300">
                            <x-ui.icon name="cube" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-neutral-950 dark:text-white">Receiving Reconciliation</h2>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                                Latest 100 GRN lines in the selected period. Purchase quantities use the shown UOM; warehouse quantities use base units. Current available is the shared lot or item balance and may include other receipts.
                            </p>
                        </div>
                    </div>
                </x-slot>
                <div class="overflow-x-auto">
                    <table class="min-w-[80rem] w-full border-separate border-spacing-0 text-left text-xs [&_td]:border-r [&_td]:border-neutral-100 [&_td:last-child]:border-r-0 [&_th]:border-r [&_th]:border-neutral-200 [&_th:last-child]:border-r-0 dark:[&_td]:border-neutral-800 dark:[&_th]:border-neutral-700">
                        <thead class="bg-primary-50/70 text-neutral-700 dark:bg-primary-950/40 dark:text-neutral-300"><tr>
                            <th class="p-2">PO / GRN / Receiver</th><th class="p-2">Item / Batch / Expiry</th>
                            <th class="p-2">Conversion</th><th class="p-2">Ordered</th><th class="p-2">Delivered</th>
                            <th class="p-2">This GRN</th><th class="p-2">Accepted</th><th class="p-2">Rejected</th>
                            <th class="p-2">Pending QC</th><th class="p-2">Awaiting put-away</th><th class="p-2">Put away from GRN</th><th class="p-2">Current available</th>
                            <th class="p-2">Remaining receivable</th><th class="p-2">Outstanding</th><th class="p-2">Returned</th>
                        </tr></thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                            @forelse($receivingReconciliation as $row)
                                <tr class="transition-colors hover:bg-primary-50/40 dark:hover:bg-primary-950/20">
                                    <td class="p-2"><a class="font-semibold text-primary-700 hover:underline" href="{{ route('inventory.receiving.show', $row['grn']) }}">{{ $row['grn']->grn_number }}</a><br>{{ $row['po']?->po_number ?? 'Legacy' }}<br>{{ $row['receiver'] ?? 'Unknown' }}</td>
                                    <td class="p-2">{{ $row['item']?->name }}<br>{{ $row['batch'] ?? 'No batch' }}<br>{{ $row['expiry']?->format('Y-m-d') ?? 'No expiry' }}</td>
                                    <td class="p-2">1 {{ $row['purchase_unit'] }} = {{ $row['factor'] }} {{ $row['base_unit'] }}</td>
                                    <td class="p-2">{{ $row['ordered'] }}</td><td class="p-2">{{ $row['delivered'] }}</td><td class="p-2">{{ $row['receipt_quantity'] }}</td>
                                    <td class="p-2">{{ $row['accepted'] }}</td><td class="p-2">{{ $row['rejected'] }}</td><td class="p-2">{{ $row['pending_qc'] }}</td>
                                    <td class="p-2">{{ $row['awaiting_put_away_base'] }}</td><td class="p-2">{{ $row['put_away_base'] }}</td><td class="p-2">{{ $row['available_base'] }}</td>
                                    <td class="p-2">{{ $row['remaining'] }}</td><td class="p-2">{{ $row['outstanding'] }}</td><td class="p-2">{{ $row['returned_base'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="15" class="p-4 text-center text-neutral-500">No receiving lines in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card>
                <x-slot name="header">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300">
                            <x-ui.icon name="chart-bar" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-neutral-950 dark:text-white">PO Commitments by Supplier</h2>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $period['description'] }} Bars compare commitment values; select a row to inspect its purchase orders.</p>
                        </div>
                    </div>
                </x-slot>
                @php $supplierSpendMax = max(1, (float) $spendBySupplier->max('value')); @endphp
                <div class="hidden grid-cols-[minmax(14rem,28rem)_minmax(0,1fr)_auto] items-center gap-4 border-b border-neutral-200 px-2 py-2 text-[0.6875rem] font-semibold uppercase tracking-wide text-neutral-500 dark:border-neutral-800 dark:text-neutral-400 md:grid">
                    <span>Supplier / receiving status</span>
                    <span>Commitment vs. largest supplier</span>
                    <span class="text-right">Value</span>
                </div>
                <div class="divide-y divide-neutral-100 dark:divide-neutral-800">
                    @forelse ($spendBySupplier as $row)
                        @php
                            $spendShare = round(((float) $row->value / $supplierSpendMax) * 100);
                        @endphp
                        @if ($row->supplier_id)
                            <button type="button"
                               data-drilldown-url="{{ $drilldownUrl('supplier', $row->supplier_id) }}"
                               data-drilldown-title="Procurement Spend — {{ $row->supplier }}"
                               data-drilldown-focus="spending"
                               x-on:click.prevent="$dispatch('open-chart-drilldown', { url: $el.dataset.drilldownUrl, title: $el.dataset.drilldownTitle, focus: $el.dataset.drilldownFocus })"
                               class="group grid w-full gap-2 px-2 py-2.5 text-left transition hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:hover:bg-neutral-800/60 md:grid-cols-[minmax(14rem,28rem)_minmax(0,1fr)_auto] md:items-center md:gap-4"
                               aria-label="View purchase orders for {{ $row->supplier }}">
                                <div class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ $row->supplier }}</span>
                                    <span class="mt-0.5 block text-xs text-neutral-500 dark:text-neutral-400">{{ number_format($row->received_orders) }} received out of {{ number_format($row->orders) }} {{ $row->orders == 1 ? 'order' : 'orders' }}</span>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800" role="img" aria-label="{{ $spendShare }}% of the largest supplier commitment" title="{{ $spendShare }}% of the largest supplier commitment">
                                    <div class="h-full rounded-full bg-primary-500 transition-all group-hover:bg-primary-600" style="width: {{ $spendShare }}%"></div>
                                </div>
                                <span class="shrink-0 text-sm font-bold tabular-nums text-neutral-950 dark:text-white">₱{{ number_format($row->value, 2) }}</span>
                            </button>
                        @else
                            <div class="grid gap-2 px-2 py-2.5 md:grid-cols-[minmax(14rem,28rem)_minmax(0,1fr)_auto] md:items-center md:gap-4">
                                <div class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-neutral-800 dark:text-neutral-200">{{ $row->supplier }}</span>
                                    <span class="mt-0.5 block text-xs text-neutral-500 dark:text-neutral-400">{{ number_format($row->received_orders) }} received out of {{ number_format($row->orders) }} {{ $row->orders == 1 ? 'order' : 'orders' }} · Supplier record unavailable</span>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800" role="img" aria-label="{{ $spendShare }}% of the largest supplier commitment" title="{{ $spendShare }}% of the largest supplier commitment">
                                    <div class="h-full rounded-full bg-neutral-400" style="width: {{ $spendShare }}%"></div>
                                </div>
                                <span class="shrink-0 text-sm font-bold tabular-nums text-neutral-950 dark:text-white">₱{{ number_format($row->value, 2) }}</span>
                            </div>
                        @endif
                    @empty
                        <p class="py-8 text-center text-sm text-neutral-500">No purchase orders match this period and supplier filter.</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>
        @endif

        {{-- Tab 3: Movements & Consumption --}}
        <div x-show="activeTab === 'movements'" x-cloak class="space-y-4 print:!block">
            <div
                x-data="{
                    consumedSearch: '',
                    consumedSearchIndex: @js($topConsumedItems->map(fn ($row) => strtolower($row->item.' '.$row->sku))->values()),
                    get hasConsumedMatches() {
                        const query = this.consumedSearch.trim().toLowerCase();
                        return !query || this.consumedSearchIndex.some(value => value.includes(query));
                    }
                }"
            >
                <x-ui.card :padding="false">
                    <x-slot name="header">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 ring-1 ring-primary-100 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-800/60">
                                <x-ui.icon name="chart-bar" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-neutral-950 dark:text-white">Most Consumed Items</h2>
                                <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">By units issued or taken out.</p>
                            </div>
                        </div>
                    </x-slot>
                    @if ($topConsumedItems->count() > 1)
                        <x-slot name="actions">
                            <label class="relative block w-full sm:w-72">
                                <span class="sr-only">Search most consumed items</span>
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400">
                                    <x-ui.icon name="magnifying-glass" class="h-4 w-4" />
                                </span>
                                <input
                                    type="search"
                                    x-model.debounce.150ms="consumedSearch"
                                    placeholder="Search items or SKU..."
                                    class="w-full rounded-xl border border-neutral-300 bg-white py-2 pl-9 pr-3 text-xs text-neutral-900 shadow-2xs placeholder:text-neutral-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:placeholder:text-neutral-500"
                                />
                            </label>
                        </x-slot>
                    @endif
                    <x-ui.table :sticky-header="false">
                        <x-ui.table.head class="bg-primary-50/70 dark:bg-primary-950/35">
                            <x-ui.table.th class="px-3 py-2.5">Item</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">Consumed</x-ui.table.th>
                            <x-ui.table.th numeric class="px-3 py-2.5">On Hand</x-ui.table.th>
                            @if ($canViewFinancialData)
                                <x-ui.table.th numeric class="px-3 py-2.5">Value</x-ui.table.th>
                            @endif
                        </x-ui.table.head>
                        <tbody>
                            @forelse ($topConsumedItems as $row)
                                <x-ui.table.row
                                    x-show="!consumedSearch.trim() || @js(strtolower($row->item.' '.$row->sku)).includes(consumedSearch.trim().toLowerCase())"
                                >
                                    <x-ui.table.td class="px-3 py-2.5">
                                        <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $row->item }}</span>
                                        <span class="block text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ $row->sku }} &middot; {{ $row->movements }} movements
                                        </span>
                                    </x-ui.table.td>
                                    <x-ui.table.td numeric class="px-3 py-2.5 font-medium">
                                        {{ number_format($row->units) }}
                                        <span class="block text-xs font-normal text-neutral-400">{{ $row->unit ?? 'units' }}</span>
                                    </x-ui.table.td>
                                    <x-ui.table.td numeric muted class="px-3 py-2.5">{{ number_format($row->on_hand) }}</x-ui.table.td>
                                    @if ($canViewFinancialData)
                                        <x-ui.table.td numeric muted class="px-3 py-2.5">₱{{ number_format($row->value, 2) }}</x-ui.table.td>
                                    @endif
                                </x-ui.table.row>
                            @empty
                                <x-ui.table.empty
                                    :colspan="$canViewFinancialData ? 4 : 3"
                                    icon="arrows-right-left"
                                    title="Nothing consumed in this window"
                                    message="Stock out and issuance movements are what this counts." />
                            @endforelse
                            @if ($topConsumedItems->isNotEmpty())
                                <tr x-show="consumedSearch.trim() && !hasConsumedMatches" class="border-b border-neutral-100 dark:border-neutral-800">
                                    <td colspan="{{ $canViewFinancialData ? 4 : 3 }}" class="px-4 py-8 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                        No consumed items match “<span class="font-semibold text-neutral-700 dark:text-neutral-200" x-text="consumedSearch"></span>”.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </x-ui.table>
                </x-ui.card>
            </div>

            <x-ui.card :padding="false">
                <x-slot name="header">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 ring-1 ring-primary-100 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-800/60">
                            <x-ui.icon name="arrows-right-left" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-neutral-950 dark:text-white">Movement History</h2>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $recentMovements->count() }} most recent in this window</p>
                        </div>
                    </div>
                </x-slot>
                <x-slot name="actions">
                    <x-ui.button variant="ghost" size="sm" :href="route('inventory.stock-movements')" class="print:hidden">
                        View all &rarr;
                    </x-ui.button>
                </x-slot>

                <x-ui.table :sticky-header="false">
                    <x-ui.table.head class="bg-primary-50/70 dark:bg-primary-950/35">
                        <x-ui.table.th class="px-3 py-2.5">Item</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Type</x-ui.table.th>
                        <x-ui.table.th numeric class="px-3 py-2.5">Qty</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">From</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">To / Reference</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Recorded</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($recentMovements as $movement)
                            <x-ui.table.row>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $movement->item?->name ?? '—' }}</span>
                                    @if ($movement->item?->sku)
                                        <span class="block font-mono text-xs text-neutral-500 dark:text-neutral-400">{{ $movement->item->sku }}</span>
                                    @endif
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <x-ui.badge :status="$movement->movement_type->value">
                                        {{ $movement->movement_type->label() }}
                                    </x-ui.badge>
                                </x-ui.table.td>
                                <x-ui.table.td numeric class="px-3 py-2.5 font-medium">{{ number_format($movement->quantity) }}</x-ui.table.td>
                                <x-ui.table.td muted class="px-3 py-2.5">{{ $movement->fromLocation?->name ?? '—' }}</x-ui.table.td>
                                <x-ui.table.td muted class="px-3 py-2.5">
                                    @if ($movement->toLocation)
                                        {{ $movement->toLocation->name }}
                                    @elseif ($movement->reference)
                                        {{ $movement->reference->name ?? class_basename($movement->reference) }}
                                    @else
                                        —
                                    @endif
                                </x-ui.table.td>
                                <x-ui.table.td muted class="px-3 py-2.5">
                                    {{ $movement->moved_at?->format('M d, Y g:i A') ?? '—' }}
                                    @if ($movement->user)
                                        <span class="block text-xs text-neutral-400">{{ $movement->user->name }}</span>
                                    @endif
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="6"
                                icon="arrows-right-left"
                                title="No movements in this window"
                                message="Widen the reporting period, or record a movement to start the history." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>

        {{-- Tab 4: Expiry Risk Batches --}}
        <div x-show="activeTab === 'expiry'" x-cloak class="space-y-6 print:!block">
            <x-ui.card title="Expiry Exposure - Detailed Batches" subtitle="Batches holding stock that are expired or approaching expiry." :padding="false">
                <x-ui.table :sticky-header="false">
                    <x-ui.table.head>
                        <x-ui.table.th class="px-3 py-2.5">Item Name</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Batch Number</x-ui.table.th>
                        <x-ui.table.th numeric class="px-3 py-2.5">Units on Hand</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Expiry Date</x-ui.table.th>
                        <x-ui.table.th class="px-3 py-2.5">Status</x-ui.table.th>
                        @if ($canViewFinancialData)
                            <x-ui.table.th numeric class="px-3 py-2.5">At-Risk Value</x-ui.table.th>
                        @endif
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($expiry['rows'] as $batch)
                            <x-ui.table.row>
                                <x-ui.table.td class="px-3 py-2.5 font-medium text-neutral-900">
                                    {{ $batch->item?->name ?? 'Unknown item' }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5 font-mono text-xs text-neutral-600">
                                    {{ $batch->batch_number }}
                                </x-ui.table.td>
                                <x-ui.table.td numeric class="px-3 py-2.5 font-medium">
                                    {{ number_format((int) $batch->units_on_hand) }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5">
                                    {{ $batch->expiry_date?->format('M d, Y') }}
                                </x-ui.table.td>
                                <x-ui.table.td class="px-3 py-2.5">
                                    <x-ui.badge :status="$batch->expiryClassification()">
                                        {{ $batch->expiryStatusLabel() }}
                                    </x-ui.badge>
                                </x-ui.table.td>
                                @if ($canViewFinancialData)
                                    <x-ui.table.td numeric class="px-3 py-2.5 font-medium text-danger-700">
                                        ₱{{ number_format((float) ($batch->units_on_hand * ($batch->unit_cost ?? $batch->item?->unit_cost ?? 0)), 2) }}
                                    </x-ui.table.td>
                                @endif
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty
                                :colspan="$canViewFinancialData ? 6 : 5"
                                icon="check-circle"
                                title="No expired or expiring batches"
                                message="All dated inventory batches are currently healthy and well outside expiry warning windows." />
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>

    <p class="text-xs text-neutral-400">
        Generated {{ now()->format('M d, Y g:i A') }} for {{ auth()->user()?->name }}.
        Every figure is read live from the same records the operational screens use.
    </p>

    <div
        x-data="chartDrilldownModal()"
        x-on:open-chart-drilldown.window="openDrilldown($event.detail)"
        x-on:keydown.escape.window="if (isOpen) closeModal()"
        class="print:hidden"
    >
        <div
            x-show="isOpen"
            x-cloak
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-5"
            role="dialog"
            aria-modal="true"
            aria-labelledby="chart-drilldown-modal-title"
            :aria-busy="loading"
        >
            <button
                type="button"
                tabindex="-1"
                class="fixed inset-0 cursor-default bg-neutral-950/60 backdrop-blur-[2px]"
                aria-label="Close chart details"
                x-on:click="closeModal()"
                x-on:wheel.prevent
                x-on:touchmove.prevent
            ></button>

            <section
                x-show="isOpen"
                x-transition
                class="relative flex max-h-[calc(100dvh-1rem)] w-full max-w-7xl flex-col overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-700 dark:bg-neutral-900 sm:max-h-[88dvh]"
            >
                <header class="flex shrink-0 flex-col gap-3 border-b border-neutral-200 px-4 py-3 dark:border-neutral-800 sm:flex-row sm:items-start sm:justify-between sm:px-5 sm:py-4">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 ring-1 ring-primary-100 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-800/60">
                            <x-ui.icon name="document-chart-bar" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-primary-700 dark:text-primary-300">Chart drill-down</p>
                            <h2 id="chart-drilldown-modal-title" class="mt-0.5 break-words text-base font-bold leading-tight text-neutral-950 dark:text-white sm:text-lg" x-text="title"></h2>
                            <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400" x-show="!loading && !errorMessage" x-text="resultSummary"></p>
                        </div>
                    </div>
                    <div class="flex w-full shrink-0 items-center justify-end sm:w-auto">
                        <button
                            x-ref="closeButton"
                            type="button"
                            x-on:click="closeModal()"
                            class="inline-flex min-h-10 min-w-10 shrink-0 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white sm:-mr-1"
                        >
                            <span class="sr-only">Close chart details</span>
                            <x-ui.icon name="x-mark" class="h-5 w-5" />
                        </button>
                    </div>
                </header>

                <div x-ref="modalBody" class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain p-3 sm:p-4">
                    <div x-show="loading" class="rounded-xl border border-neutral-200 bg-white p-3 dark:border-neutral-700 dark:bg-neutral-900 sm:p-4" role="status" aria-live="polite">
                        <span class="sr-only">Loading drill-down records...</span>
                        <div class="space-y-3 motion-safe:animate-pulse" aria-hidden="true">
                            <div class="flex items-start gap-3">
                                <span class="h-9 w-9 shrink-0 rounded-lg bg-primary-100 dark:bg-primary-950/70"></span>
                                <div class="min-w-0 flex-1 space-y-2 pt-0.5">
                                    <div class="flex gap-2">
                                        <span class="h-5 w-36 rounded-full bg-neutral-200 dark:bg-neutral-700"></span>
                                        <span class="h-5 w-20 rounded-full bg-success-100 dark:bg-success-950/70"></span>
                                    </div>
                                    <span class="block h-4 w-full max-w-lg rounded bg-neutral-200 dark:bg-neutral-700"></span>
                                </div>
                            </div>
                            <div class="grid overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50/70 dark:border-neutral-700 dark:bg-neutral-800/60 sm:grid-cols-3">
                                @for ($placeholder = 0; $placeholder < 3; $placeholder++)
                                    <div class="space-y-2 p-3 sm:border-l sm:first:border-l-0 sm:border-neutral-200 sm:dark:border-neutral-700">
                                        <span class="block h-3 w-24 rounded bg-neutral-200 dark:bg-neutral-700"></span>
                                        <span class="block h-5 w-32 max-w-full rounded bg-neutral-300 dark:bg-neutral-600"></span>
                                    </div>
                                @endfor
                            </div>
                            <div class="grid gap-x-5 gap-y-3 sm:grid-cols-2 lg:grid-cols-4">
                                @for ($placeholder = 0; $placeholder < 4; $placeholder++)
                                    <div class="space-y-2 border-t border-neutral-100 pt-2 dark:border-neutral-800">
                                        <span class="block h-3 w-20 rounded bg-neutral-200 dark:bg-neutral-700"></span>
                                        <span class="block h-4 w-28 max-w-full rounded bg-neutral-300 dark:bg-neutral-600"></span>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    </div>

                    <div
                        x-show="!loading && errorMessage"
                        class="flex min-h-48 flex-col items-center justify-center rounded-xl border border-danger-200 bg-danger-50 p-6 text-center dark:border-danger-800 dark:bg-danger-950/40"
                        role="alert"
                    >
                        <x-ui.icon name="exclamation-triangle" class="h-8 w-8 text-danger-600" />
                        <p class="mt-3 text-sm font-semibold text-danger-800 dark:text-danger-200">Unable to load chart details</p>
                        <p class="mt-1 max-w-lg text-sm text-danger-700 dark:text-danger-300" x-text="errorMessage"></p>
                        <button
                            type="button"
                            x-on:click="retry()"
                            class="mt-4 inline-flex min-h-10 items-center justify-center rounded-lg border border-danger-300 bg-white px-4 py-2 text-sm font-semibold text-danger-700 hover:bg-danger-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-500 dark:border-danger-700 dark:bg-neutral-900 dark:text-danger-300 dark:hover:bg-danger-950"
                        >
                            Try again
                        </button>
                    </div>

                    <div
                        x-show="!loading && !errorMessage && rowCount === 0"
                        class="flex min-h-48 flex-col items-center justify-center rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center dark:border-neutral-700 dark:bg-neutral-800/50"
                        role="status"
                    >
                        <x-ui.icon name="chart-bar" class="h-8 w-8 text-neutral-400" />
                        <p class="mt-3 text-sm font-semibold text-neutral-800 dark:text-neutral-100">No data found</p>
                        <p
                            class="mt-1 max-w-lg text-sm text-neutral-500 dark:text-neutral-400"
                            x-text="report?.empty_message || 'No records match the selected chart value and active filters.'"
                        ></p>
                    </div>

                    <div x-show="!loading && !errorMessage && rowCount > 0">
                        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <label class="relative block w-full sm:max-w-md">
                                <span class="sr-only">Search drill-down records</span>
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400">
                                    <x-ui.icon name="magnifying-glass" class="h-4 w-4" />
                                </span>
                                <input
                                    type="search"
                                    x-model.debounce.150ms="searchQuery"
                                    x-on:input="currentPage = 1"
                                    placeholder="Search records, item name, SKU, barcode, or category..."
                                    class="w-full rounded-xl border border-neutral-300 bg-white py-2.5 pl-10 pr-3 text-sm text-neutral-900 shadow-2xs placeholder:text-neutral-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 dark:border-neutral-700 dark:bg-neutral-900 dark:text-white dark:placeholder:text-neutral-500"
                                />
                            </label>

                            <div
                                class="inline-flex shrink-0 self-end rounded-xl border border-neutral-200 bg-neutral-100 p-1 text-sm font-semibold dark:border-neutral-700 dark:bg-neutral-800 sm:self-auto"
                                role="group"
                                aria-label="Drill-down display mode"
                            >
                                <button
                                    type="button"
                                    x-on:click="viewMode = 'cards'"
                                    class="inline-flex min-h-9 items-center gap-2 rounded-lg px-3 py-1.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                    :class="viewMode === 'cards' ? 'bg-white text-primary-700 shadow-2xs font-bold dark:bg-neutral-900 dark:text-primary-300' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                                    :aria-pressed="viewMode === 'cards'"
                                >
                                    <x-ui.icon name="squares-2x2" class="h-4 w-4" />
                                    <span>Cards</span>
                                </button>
                                <button
                                    type="button"
                                    x-on:click="viewMode = 'table'"
                                    class="inline-flex min-h-9 items-center gap-2 rounded-lg px-3 py-1.5 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                    :class="viewMode === 'table' ? 'bg-white text-primary-700 shadow-2xs font-bold dark:bg-neutral-900 dark:text-primary-300' : 'text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-white'"
                                    :aria-pressed="viewMode === 'table'"
                                >
                                    <x-ui.icon name="table-cells" class="h-4 w-4" />
                                    <span>Table</span>
                                </button>
                            </div>
                        </div>

                        <div
                            x-show="searchQuery.trim() && filteredRows.length === 0"
                            class="mb-4 rounded-xl border border-neutral-200 bg-neutral-50 p-5 text-center text-sm text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800/50 dark:text-neutral-300"
                        >
                            No drill-down records match “<span class="font-semibold" x-text="searchQuery"></span>”.
                        </div>

                        {{-- VIEW 1: Responsive Cards Stream (Zero Horizontal Bar) --}}
                        <div
                            x-show="viewMode === 'cards' && filteredRows.length > 0"
                            class="w-full space-y-3"
                        >
                            <template x-for="(row, rowIndex) in paginatedRows" :key="row.id ?? rowIndex">
                                <article class="space-y-3 rounded-xl border border-neutral-200 bg-white p-3 shadow-2xs transition hover:border-neutral-300 dark:border-neutral-700 dark:bg-neutral-900 dark:hover:border-neutral-600 sm:p-4">
                                    <div class="flex items-start gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 ring-1 ring-primary-100 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-800/60">
                                            <x-ui.icon name="document-check" class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <template x-if="getPrimaryRef(row)">
                                                    <span class="inline-flex items-center gap-1 rounded-full border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-[11px] font-semibold text-neutral-700 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                                                        <span class="uppercase text-neutral-500 dark:text-neutral-400" x-text="getPrimaryRefLabel(row)"></span>
                                                        <span class="font-bold tabular-nums text-neutral-900 dark:text-white" x-text="getPrimaryRef(row)"></span>
                                                    </span>
                                                </template>
                                                <template x-if="getBadge(row)">
                                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold" :class="getBadgeClass(row)" x-text="getBadgeLabel(row)"></span>
                                                </template>
                                                <template x-if="getSku(row)">
                                                    <span class="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-[11px] font-semibold text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300" x-text="'SKU: ' + getSku(row)"></span>
                                                </template>
                                            </div>
                                            <h3 class="mt-2 break-words text-sm font-bold leading-snug text-neutral-950 dark:text-white sm:text-base" x-text="getItemTitle(row, pageOffset + rowIndex)"></h3>
                                        </div>
                                    </div>

                                    {{-- The selected KPI controls which record values lead the card hierarchy. --}}
                                    <dl x-show="getPrimaryAttributes(row).length > 0" class="grid overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50/70 text-xs dark:border-neutral-700 dark:bg-neutral-800/50 sm:grid-cols-3">
                                        <template x-for="([column, label], metricIndex) in getPrimaryAttributes(row)" :key="column">
                                            <div
                                                class="flex min-w-0 items-center gap-3 border-t border-neutral-200 p-3 first:border-t-0 dark:border-neutral-700 sm:border-l sm:border-t-0 sm:first:border-l-0"
                                                :class="getPrimaryMetricSurfaceClass(column)"
                                            >
                                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1" :class="getAttributeIconClass(column)" aria-hidden="true">
                                                    <template x-if="getPrimaryMetricIcon(column) === 'tag'">
                                                        <x-ui.icon name="tag" class="h-5 w-5" />
                                                    </template>
                                                    <template x-if="getPrimaryMetricIcon(column) === 'cube'">
                                                        <x-ui.icon name="cube" class="h-5 w-5" />
                                                    </template>
                                                    <template x-if="getPrimaryMetricIcon(column) === 'calendar'">
                                                        <x-ui.icon name="calendar" class="h-5 w-5" />
                                                    </template>
                                                    <template x-if="getPrimaryMetricIcon(column) === 'building'">
                                                        <x-ui.icon name="building-office-2" class="h-5 w-5" />
                                                    </template>
                                                    <template x-if="getPrimaryMetricIcon(column) === 'currency'">
                                                        <x-ui.icon name="currency-dollar" class="h-5 w-5" />
                                                    </template>
                                                    <template x-if="getPrimaryMetricIcon(column) === 'document'">
                                                        <x-ui.icon name="document-text" class="h-5 w-5" />
                                                    </template>
                                                </span>
                                                <div class="min-w-0">
                                                    <dt class="text-[0.625rem] font-bold uppercase tracking-wide text-neutral-500 dark:text-neutral-400" x-text="label"></dt>
                                                    <dd
                                                        class="mt-1 break-words font-black leading-tight"
                                                        :class="[getAttributeValueClass(column), metricIndex === 0 ? 'text-xl sm:text-2xl' : 'text-base sm:text-lg']"
                                                        x-text="formatCell(column, label, row[column])"
                                                    ></dd>
                                                </div>
                                            </div>
                                        </template>
                                    </dl>

                                    <dl x-show="getSupportingAttributes(row).length > 0" class="grid gap-x-5 gap-y-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
                                        <template x-for="[column, label] in getSupportingAttributes(row)" :key="column">
                                            <div class="min-w-0 border-t border-neutral-100 pt-2 dark:border-neutral-800">
                                                <dt class="text-[0.625rem] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400" x-text="label"></dt>
                                                <dd
                                                    class="mt-0.5 break-words font-semibold leading-snug"
                                                    :class="getAttributeValueClass(column)"
                                                    x-text="formatCell(column, label, row[column])"
                                                ></dd>
                                            </div>
                                        </template>
                                    </dl>
                                </article>
                            </template>
                        </div>

                        {{-- VIEW 2: Compact Table View (locally scrollable on narrow screens) --}}
                        <div
                            x-show="viewMode === 'table' && filteredRows.length > 0"
                            class="hims-table-scroll w-full overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-700"
                        >
                            <table class="min-w-[64rem] w-full divide-y divide-neutral-200 text-xs table-fixed">
                                <thead class="sticky top-0 z-10 bg-neutral-50 shadow-2xs dark:bg-neutral-800">
                                    <tr>
                                        <template x-for="([column, label]) in columnEntries" :key="column">
                                            <th
                                                scope="col"
                                                class="break-words px-3 py-2.5 text-xs font-semibold uppercase leading-tight tracking-wider text-neutral-600 dark:text-neutral-300"
                                                :class="isNumericColumn(column) ? 'text-right' : 'text-left'"
                                                x-text="label"
                                            ></th>
                                        </template>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-100 bg-white dark:divide-neutral-800 dark:bg-neutral-900">
                                    <template x-for="(row, rowIndex) in paginatedRows" :key="row.id ?? rowIndex">
                                        <tr class="transition-colors hover:bg-neutral-50/80 dark:hover:bg-neutral-800/70">
                                            <template x-for="([column, label]) in columnEntries" :key="column">
                                                <td
                                                    class="break-words px-3 py-2.5 leading-normal text-neutral-700 dark:text-neutral-300"
                                                    :class="isFinancialColumn(column) ? 'text-right font-mono font-bold text-primary-700' : (isNumericColumn(column) ? 'text-right tabular-nums font-medium' : 'text-left')"
                                                    x-text="formatCell(column, label, row[column])"
                                                ></td>
                                            </template>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <nav
                            x-show="totalPages > 1"
                            class="mt-4 flex flex-col gap-3 border-t border-neutral-200 pt-3 text-xs dark:border-neutral-700 sm:flex-row sm:items-center sm:justify-between"
                            aria-label="Drill-down record pagination"
                        >
                            <p class="text-neutral-600 dark:text-neutral-400">
                                Showing
                                <span class="font-semibold tabular-nums text-neutral-900 dark:text-neutral-100" x-text="pageStart"></span>–<span class="font-semibold tabular-nums text-neutral-900 dark:text-neutral-100" x-text="pageEnd"></span>
                                of <span class="font-semibold tabular-nums text-neutral-900 dark:text-neutral-100" x-text="filteredRows.length"></span>
                            </p>
                            <div class="flex flex-wrap items-center gap-1 sm:justify-end">
                                <button
                                    type="button"
                                    x-on:click="goToPage(currentPage - 1)"
                                    :disabled="currentPage === 1"
                                    class="inline-flex min-h-8 items-center justify-center rounded-lg border border-neutral-300 bg-white px-3 font-semibold text-neutral-700 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-100/60 disabled:text-neutral-400 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80 dark:disabled:border-neutral-800 dark:disabled:bg-neutral-800/40 dark:disabled:text-neutral-600"
                                >Previous</button>
                                <template x-for="item in pageItems" :key="String(item)">
                                    <button
                                        type="button"
                                        x-on:click="typeof item === 'number' && goToPage(item)"
                                        :disabled="typeof item !== 'number'"
                                        :aria-current="item === currentPage ? 'page' : null"
                                        :aria-label="typeof item === 'number' ? `Go to page ${item}` : 'More pages'"
                                        :class="typeof item !== 'number'
                                            ? 'inline-flex min-h-8 min-w-8 cursor-default items-center justify-center border border-transparent px-1 font-medium text-neutral-400 dark:text-neutral-500'
                                            : item === currentPage
                                                ? 'inline-flex min-h-8 min-w-8 items-center justify-center rounded-lg bg-neutral-900 px-2.5 py-1.5 font-semibold text-white shadow-2xs dark:bg-primary-600'
                                                : 'inline-flex min-h-8 min-w-8 items-center justify-center rounded-lg border border-neutral-300 bg-white px-2.5 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80'"
                                        x-text="typeof item === 'number' ? item : String.fromCharCode(8230)"
                                    ></button>
                                </template>
                                <button
                                    type="button"
                                    x-on:click="goToPage(currentPage + 1)"
                                    :disabled="currentPage === totalPages"
                                    class="inline-flex min-h-8 items-center justify-center rounded-lg border border-neutral-300 bg-white px-3 font-semibold text-neutral-700 transition hover:bg-neutral-50 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-100/60 disabled:text-neutral-400 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700/80 dark:disabled:border-neutral-800 dark:disabled:bg-neutral-800/40 dark:disabled:text-neutral-600"
                                >Next</button>
                            </div>
                        </nav>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
        function chartDrilldownModal() {
            return {
                isOpen: false,
                loading: false,
                title: 'Chart details',
                report: null,
                errorMessage: '',
                lastRequest: null,
                abortController: null,
                requestSequence: 0,
                returnFocusTo: null,
                rootOverflow: null,
                pageSize: 10,
                currentPage: 1,
                viewMode: 'cards',
                focus: null,
                searchQuery: '',

                get rows() {
                    return Array.isArray(this.report?.data) ? this.report.data : [];
                },

                get rowCount() {
                    return this.rows.length;
                },

                get filteredRows() {
                    const query = this.searchQuery.trim().toLocaleLowerCase();
                    if (!query) return this.rows;

                    return this.rows.filter(row => this.columnEntries.some(([column]) =>
                        String(row[column] ?? '').toLocaleLowerCase().includes(query)
                    ));
                },

                get totalPages() {
                    return Math.max(1, Math.ceil(this.filteredRows.length / this.pageSize));
                },

                get pageItems() {
                    if (this.totalPages <= 7) {
                        return Array.from({ length: this.totalPages }, (_, index) => index + 1);
                    }

                    if (this.currentPage <= 4) {
                        return [1, 2, 3, 4, 5, 'ellipsis-right', this.totalPages];
                    }

                    if (this.currentPage >= this.totalPages - 3) {
                        return [
                            1,
                            'ellipsis-left',
                            ...Array.from({ length: 5 }, (_, index) => this.totalPages - 4 + index),
                        ];
                    }

                    return [
                        1,
                        'ellipsis-left',
                        this.currentPage - 1,
                        this.currentPage,
                        this.currentPage + 1,
                        'ellipsis-right',
                        this.totalPages,
                    ];
                },

                get pageOffset() {
                    return (this.currentPage - 1) * this.pageSize;
                },

                get pageStart() {
                    return this.filteredRows.length === 0 ? 0 : this.pageOffset + 1;
                },

                get pageEnd() {
                    return Math.min(this.pageOffset + this.pageSize, this.filteredRows.length);
                },

                get paginatedRows() {
                    return this.filteredRows.slice(this.pageOffset, this.pageEnd);
                },

                get columnEntries() {
                    return Object.entries(this.report?.columns || {});
                },

                get resultSummary() {
                    const noun = this.rowCount === 1 ? 'record' : 'records';
                    return `${this.rowCount.toLocaleString()} matching ${noun} using the active filters and reporting period.`;
                },

                isFinancialColumn(column) {
                    return ['unit_cost', 'total_value', 'total_amount', 'accepted_value', 'outstanding_value', 'risk_value', 'value', 'amount'].includes(column) ||
                           (this.report?.columns?.[column] || '').includes('₱');
                },

                isNumericColumn(column) {
                    return ['quantity_on_hand', 'reserved_quantity', 'available_quantity', 'reorder_level', 'unit_cost', 'total_value', 'quantity', 'value', 'units', 'risk_value', 'total_amount', 'accepted_value', 'outstanding_value', 'days_remaining'].includes(column);
                },

                isDateColumn(column) {
                    return column.includes('date') || column.endsWith('_at');
                },

                isQuantityColumn(column) {
                    return column === 'quantity' || column === 'units' || column.includes('quantity');
                },

                getAttributeCardClass(column) {
                    if (column === 'accepted_value') return 'border-success-200 bg-success-50/70 dark:border-success-800/60 dark:bg-success-950/30';
                    if (column === 'outstanding_value') return 'border-warning-200 bg-warning-50/70 dark:border-warning-800/60 dark:bg-warning-950/30';
                    if (column === 'supplier' || column.includes('location')) return 'border-violet-200 bg-violet-50/60 dark:border-violet-800/60 dark:bg-violet-950/25';
                    if (this.isQuantityColumn(column)) return 'border-success-200 bg-success-50/50 dark:border-success-800/60 dark:bg-success-950/25';
                    if (this.isFinancialColumn(column) || column === 'requested_at') return 'border-primary-200 bg-primary-50/55 dark:border-primary-800/60 dark:bg-primary-950/25';
                    return 'border-neutral-200 bg-neutral-50/80 dark:border-neutral-700 dark:bg-neutral-800/60';
                },

                getAttributeIconClass(column) {
                    if (column === 'accepted_value') return 'bg-success-100 text-success-700 ring-success-200 dark:bg-success-900/50 dark:text-success-300 dark:ring-success-800';
                    if (column === 'outstanding_value') return 'bg-warning-100 text-warning-700 ring-warning-200 dark:bg-warning-900/50 dark:text-warning-300 dark:ring-warning-800';
                    if (column === 'supplier' || column.includes('location')) return 'bg-violet-100 text-violet-700 ring-violet-200 dark:bg-violet-900/50 dark:text-violet-300 dark:ring-violet-800';
                    if (this.isQuantityColumn(column)) return 'bg-success-100 text-success-700 ring-success-200 dark:bg-success-900/50 dark:text-success-300 dark:ring-success-800';
                    if (this.isFinancialColumn(column) || column === 'requested_at') return 'bg-primary-100 text-primary-700 ring-primary-200 dark:bg-primary-900/50 dark:text-primary-300 dark:ring-primary-800';
                    return 'bg-neutral-200/70 text-neutral-600 ring-neutral-200 dark:bg-neutral-700 dark:text-neutral-300 dark:ring-neutral-600';
                },

                getAttributeValueClass(column) {
                    if (column === 'accepted_value') return 'text-success-700 tabular-nums dark:text-success-300';
                    if (column === 'outstanding_value') return 'text-warning-700 tabular-nums dark:text-warning-300';
                    if (this.isFinancialColumn(column)) return 'text-primary-700 tabular-nums dark:text-primary-300';
                    if (this.isNumericColumn(column)) return 'text-neutral-950 tabular-nums dark:text-white';
                    return 'text-neutral-900 dark:text-neutral-100';
                },

                getPrimaryRef(row) {
                    return row.reference_number || row.po_number || row.batch_number || row.batch || row.id || '';
                },

                getPrimaryRefLabel(row) {
                    if (row.reference_number) return 'Ref #';
                    if (row.po_number) return 'PO #';
                    if (row.batch_number || row.batch) return 'Batch #';
                    if (row.id) return 'Ref #';
                    return 'ID';
                },

                getBadge(row) {
                    return row.movement_type || row.type || row.status || '';
                },

                getBadgeLabel(row) {
                    return String(this.getBadge(row))
                        .replaceAll('_', ' ')
                        .replace(/\b\w/g, character => character.toUpperCase());
                },

                getBadgeClass(row) {
                    const val = String(this.getBadge(row)).toLowerCase();
                    if (val.includes('stock_in') || val.includes('in_stock') || val.includes('received') || val.includes('approved') || val.includes('complete')) {
                        return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800';
                    }
                    if (val.includes('low_stock') || val.includes('expiring') || val.includes('pending') || val.includes('issued')) {
                        return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800';
                    }
                    if (val.includes('out_of_stock') || val.includes('expired') || val.includes('damage') || val.includes('loss') || val.includes('reject')) {
                        return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800';
                    }
                    if (val.includes('adjustment') || val.includes('transfer')) {
                        return 'bg-primary-50 text-primary-700 border-primary-200 dark:bg-primary-950/60 dark:text-primary-300 dark:border-primary-800';
                    }
                    return 'bg-neutral-100 text-neutral-700 border-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:border-neutral-700';
                },

                getItemTitle(row, index) {
                    return row.item_description || row.name || row.item || row.supplier || ('Record #' + (index + 1));
                },

                getSku(row) {
                    return row.sku || '';
                },

                getDateValue(row) {
                    return row.occurred_at || row.moved_at || row.requested_at || row.date || row.expiry_date || '';
                },

                getGridAttributes(row) {
                    const excludedCols = new Set([
                        'item_description', 'name', 'item', 'sku', 'reference_number', 'po_number', 'occurred_at', 'date'
                    ]);
                    if (this.getBadge(row)) {
                        excludedCols.add('movement_type');
                        excludedCols.add('type');
                        excludedCols.add('status');
                    }
                    return this.columnEntries.filter(([col]) =>
                        !excludedCols.has(col) && row[col] !== null && row[col] !== undefined && row[col] !== ''
                    );
                },

                getPrimaryAttributes(row) {
                    const available = this.getGridAttributes(row);
                    const priorities = {
                        items: ['quantity_on_hand', 'reserved_quantity', 'reorder_level'],
                        valuation: ['total_value', 'quantity_on_hand', 'unit_cost'],
                        reserved: ['reserved_quantity', 'quantity_on_hand', 'reorder_level'],
                        stock: ['quantity_on_hand', 'reserved_quantity', 'reorder_level'],
                        expiry: ['units', 'days_remaining', 'risk_value'],
                        movement: ['quantity', 'value', 'moved_at'],
                        spending: ['total_amount', 'accepted_value', 'outstanding_value'],
                    };
                    const preferred = priorities[this.focus] || [];
                    const selected = preferred
                        .map(column => available.find(([candidate]) => candidate === column))
                        .filter(Boolean);

                    for (const attribute of available) {
                        if (selected.length >= 3) break;
                        if (!selected.some(([column]) => column === attribute[0]) &&
                            (this.isNumericColumn(attribute[0]) || this.isFinancialColumn(attribute[0]))) {
                            selected.push(attribute);
                        }
                    }

                    for (const attribute of available) {
                        if (selected.length >= 3) break;
                        if (!selected.some(([column]) => column === attribute[0])) selected.push(attribute);
                    }

                    return selected;
                },

                getSupportingAttributes(row) {
                    const primaryColumns = new Set(this.getPrimaryAttributes(row).map(([column]) => column));
                    return this.getGridAttributes(row).filter(([column]) => !primaryColumns.has(column));
                },

                getPrimaryMetricSurfaceClass(column) {
                    if (column === 'accepted_value') return 'bg-success-50/80 dark:bg-success-950/30';
                    if (column === 'outstanding_value') return 'bg-warning-50/80 dark:bg-warning-950/30';
                    if (column === 'risk_value') return 'bg-danger-50/80 dark:bg-danger-950/30';
                    if (this.isFinancialColumn(column)) return 'bg-primary-50/80 dark:bg-primary-950/30';
                    return 'bg-white dark:bg-neutral-900';
                },

                getPrimaryMetricIcon(column) {
                    if (column === 'unit_cost') return 'tag';
                    if (this.isDateColumn(column) || column === 'days_remaining') return 'calendar';
                    if (column === 'supplier' || column.includes('location')) return 'building';
                    if (this.isFinancialColumn(column)) return 'currency';
                    if (this.isQuantityColumn(column) || column === 'reorder_level') return 'cube';
                    return 'document';
                },

                savedScrollY: null,

                goToPage(page) {
                    this.currentPage = Math.min(Math.max(1, page), this.totalPages);
                    this.$nextTick(() => this.$refs.modalBody?.scrollTo({ top: 0, behavior: 'instant' }));
                },

                async openDrilldown(request) {
                    if (!request?.url) return;

                    this.lastRequest = request;
                    this.returnFocusTo = document.activeElement;
                    this.savedScrollY = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
                    if (this.rootOverflow === null) {
                        this.rootOverflow = document.documentElement.style.overflow;
                        document.documentElement.style.overflow = 'hidden';
                    }
                    this.title = request.title || 'Chart details';
                    this.focus = request.focus || null;
                    this.report = null;
                    this.errorMessage = '';
                    this.currentPage = 1;
                    this.searchQuery = '';
                    this.loading = true;
                    this.isOpen = true;

                    this.abortController?.abort();
                    this.abortController = new AbortController();
                    const sequence = ++this.requestSequence;

                    this.$nextTick(() => {
                        this.$refs.closeButton?.focus({ preventScroll: true });
                        if (typeof this.savedScrollY === 'number' && Math.abs((window.pageYOffset || document.documentElement.scrollTop || 0) - this.savedScrollY) > 2) {
                            window.scrollTo({ top: this.savedScrollY, behavior: 'instant' });
                        }
                    });

                    try {
                        const response = await fetch(request.url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            signal: this.abortController.signal,
                        });

                        if (!response.ok) {
                            let message = 'The drill-down records could not be retrieved.';
                            try {
                                const payload = await response.json();
                                message = payload.message || message;
                            } catch (error) {
                                // Keep the safe fallback for a non-JSON server response.
                            }
                            throw new Error(message);
                        }

                        const report = await response.json();
                        if (sequence !== this.requestSequence) return;

                        this.report = report;
                    } catch (error) {
                        if (error.name === 'AbortError' || sequence !== this.requestSequence) return;
                        this.errorMessage = error.message || 'The drill-down records could not be retrieved.';
                    } finally {
                        if (sequence === this.requestSequence) this.loading = false;
                    }
                },

                retry() {
                    if (this.lastRequest) this.openDrilldown(this.lastRequest);
                },

                closeModal() {
                    ++this.requestSequence;
                    this.abortController?.abort();
                    this.abortController = null;
                    this.loading = false;
                    this.isOpen = false;
                    if (this.rootOverflow !== null) {
                        document.documentElement.style.overflow = this.rootOverflow;
                        this.rootOverflow = null;
                    }

                    const focusTarget = this.returnFocusTo;
                    const targetScroll = this.savedScrollY;
                    this.returnFocusTo = null;

                    this.$nextTick(() => {
                        if (typeof targetScroll === 'number') {
                            window.scrollTo({ top: targetScroll, behavior: 'instant' });
                        }
                        if (focusTarget && focusTarget.isConnected) {
                            focusTarget.focus({ preventScroll: true });
                        }
                    });
                },

                formatCell(column, label, value) {
                    if (value === null || value === undefined || value === '') return '—';
                    if (!this.isNumericColumn(column) || Number.isNaN(Number(value))) return String(value);

                    const number = Number(value);
                    if (label.includes('₱')) {
                        return new Intl.NumberFormat('en-PH', {
                            style: 'currency',
                            currency: 'PHP',
                        }).format(number);
                    }

                    return new Intl.NumberFormat('en-PH', {
                        maximumFractionDigits: Number.isInteger(number) ? 0 : 2,
                    }).format(number);
                },
            };
        }

        function reportGenerator(config) {
            return {
                isOpen: false,
                reportType: config.initialReportType || 'stock_status',
                period: config.initialPeriod || '30',
                fromDate: config.initialFrom || '',
                toDate: config.initialTo || '',
                categoryId: config.initialCategoryId || '',
                locationId: config.initialLocationId || '',
                supplierId: config.initialSupplierId || '',
                movementType: config.initialMovementType || '',
                status: config.initialStockStatus || 'all',
                sortBy: 'name',
                sortDir: 'asc',
                format: 'pdf',
                loading: false,
                errorMessage: '',
                successMessage: '',
                computedWindowText: '',

                init() {
                    this.updateComputedWindow();
                    this.updateSortOptions();
                },

                openModal() {
                    this.isOpen = true;
                    this.errorMessage = '';
                    this.successMessage = '';
                },

                closeModal() {
                    this.isOpen = false;
                },

                onPeriodChange() {
                    this.updateComputedWindow();
                    this.errorMessage = '';
                },

                onReportTypeChange() {
                    this.updateSortOptions();
                    this.errorMessage = '';
                },

                isPointInTimeReport() {
                    return ['stock_status', 'valuation', 'stock_by_location', 'expiry_exposure'].includes(this.reportType);
                },

                hasCategoryFilter() {
                    return ['all', 'stock_status', 'valuation', 'stock_by_location', 'expiry_exposure', 'movement_history', 'most_consumed', 'movements_by_type'].includes(this.reportType);
                },

                hasLocationFilter() {
                    return ['all', 'stock_status', 'valuation', 'stock_by_location', 'expiry_exposure', 'movement_history', 'most_consumed', 'movements_by_type'].includes(this.reportType);
                },

                hasSupplierFilter() {
                    return ['all', 'procurement_expense', 'spend_by_supplier'].includes(this.reportType);
                },

                hasMovementTypeFilter() {
                    return ['all', 'movement_history', 'movements_by_type'].includes(this.reportType);
                },

                hasStockStatusFilter() {
                    return ['all', 'stock_status', 'valuation', 'stock_by_location'].includes(this.reportType);
                },

                hasAnyDynamicFilter() {
                    return this.hasCategoryFilter() || this.hasLocationFilter() || (config.canViewFinancial && this.hasSupplierFilter()) || this.hasMovementTypeFilter() || this.hasStockStatusFilter();
                },

                get reportModuleDescription() {
                    switch (this.reportType) {
                        case 'all': return 'Compiles all standard hospital operational and analytical sections into a complete dossier.';
                        case 'stock_status': return 'Current stock level health across catalogue items with stock health categorization.';
                        case 'valuation': return 'Financial inventory valuation aggregated by item categories.';
                        case 'stock_by_location': return 'Storage location breakdown showing unit counts, valuation, and capacity utilization.';
                        case 'expiry_exposure': return 'Batches with active stock that are already expired or expiring within exposure windows.';
                        case 'movement_history': return 'Chronological audit ledger of stock issues, receipts, returns, adjustments, and transfers.';
                        case 'most_consumed': return 'Usage velocity analysis ranking items with highest consumption quantity.';
                        case 'movements_by_type': return 'Transaction volume and value aggregated by operational movement classification.';
                        case 'procurement_expense': return 'Purchase order spending breakdown with received and outstanding obligations.';
                        case 'spend_by_supplier': return 'Supplier purchase order commitments with delivery fulfilment rates.';
                        default: return 'Configure parameters and generate the report.';
                    }
                },


                get currentSortOptions() {
                    switch (this.reportType) {
                        case 'stock_status':
                            return [
                                { value: 'name', label: 'Item Description (A-Z)' },
                                { value: 'units', label: 'Units On Hand' },
                                { value: 'value', label: 'Stock Valuation' },
                                { value: 'status', label: 'Stock Health Status' },
                            ];
                        case 'valuation':
                            return [
                                { value: 'value', label: 'Valuation Amount (₱)' },
                                { value: 'name', label: 'Category Name' },
                                { value: 'items', label: 'Catalogue Items Count' },
                                { value: 'units', label: 'Total Units Stored' },
                            ];
                        case 'stock_by_location':
                            return [
                                { value: 'utilisation', label: 'Capacity Utilisation %' },
                                { value: 'name', label: 'Location Name' },
                                { value: 'units', label: 'Total Units Held' },
                                { value: 'value', label: 'Location Valuation' },
                            ];
                        case 'expiry_exposure':
                            return [
                                { value: 'date', label: 'Expiry Date (Soonest)' },
                                { value: 'units', label: 'At-Risk Units' },
                                { value: 'value', label: 'Financial Risk Exposure' },
                            ];
                        case 'movement_history':
                            return [
                                { value: 'date', label: 'Movement Timestamp (Recency)' },
                                { value: 'units', label: 'Quantity Moved' },
                                { value: 'value', label: 'Movement Valuation' },
                            ];
                        case 'procurement_expense':
                            return [
                                { value: 'date', label: 'Order Date' },
                                { value: 'amount', label: 'PO Total Amount' },
                                { value: 'orders', label: 'PO Number' },
                            ];
                        case 'spend_by_supplier':
                            return [
                                { value: 'value', label: 'Total Spend Amount' },
                                { value: 'fulfilment', label: 'Fulfilment Rate %' },
                                { value: 'orders', label: 'Order Count' },
                                { value: 'supplier', label: 'Supplier Name' },
                            ];
                        case 'most_consumed':
                            return [
                                { value: 'units', label: 'Units Consumed' },
                                { value: 'movements', label: 'Consumption Events' },
                                { value: 'value', label: 'Consumption Value' },
                            ];
                        case 'movements_by_type':
                            return [
                                { value: 'movements', label: 'Movement Count' },
                                { value: 'units', label: 'Units Transacted' },
                                { value: 'value', label: 'Total Value' },
                            ];
                        default:
                            return [
                                { value: 'date', label: 'Date / Recency' },
                                { value: 'value', label: 'Financial Value' },
                                { value: 'units', label: 'Units / Volume' },
                            ];
                    }
                },

                updateSortOptions() {
                    const opts = this.currentSortOptions;
                    if (!opts.some(o => o.value === this.sortBy)) {
                        this.sortBy = opts[0]?.value || 'name';
                    }
                },

                updateComputedWindow() {
                    const daysMap = { '1': 1, '7': 7, '30': 30, '90': 90, '365': 365 };
                    if (this.period === 'all') {
                        this.computedWindowText = 'All Historical Records (Up to Today)';
                    } else if (this.period === 'custom') {
                        this.computedWindowText = `Custom: ${this.fromDate || '...'} to ${this.toDate || '...'}`;
                    } else {
                        const days = daysMap[this.period] || 30;
                        this.computedWindowText = `Last ${days} days ending ${config.todayDate}`;
                    }
                },

                resetFilters() {
                    this.reportType = 'stock_status';
                    this.period = '30';
                    this.fromDate = config.initialFrom;
                    this.toDate = config.initialTo;
                    this.categoryId = '';
                    this.locationId = '';
                    this.supplierId = '';
                    this.movementType = '';
                    this.status = 'all';
                    this.sortBy = 'name';
                    this.sortDir = 'asc';
                    this.format = 'pdf';
                    this.errorMessage = '';
                    this.successMessage = '';
                    this.updateComputedWindow();
                    this.updateSortOptions();
                },

                applyToDashboard() {
                    this.errorMessage = '';
                    if (this.period === 'custom') {
                        if (!this.fromDate) {
                            this.errorMessage = 'Please provide a start date for the custom date range.';
                            return;
                        }
                        if (!this.toDate) {
                            this.errorMessage = 'Please provide an end date for the custom date range.';
                            return;
                        }
                        if (this.fromDate > this.toDate) {
                            this.errorMessage = 'The start date cannot be later than the end date.';
                            return;
                        }
                        if (this.toDate > config.todayDate) {
                            this.errorMessage = 'The end date cannot be a future date.';
                            return;
                        }
                    }

                    const params = new URLSearchParams();
                    if (this.period === 'custom') {
                        params.set('period', 'custom');
                        params.set('from', this.fromDate);
                        params.set('to', this.toDate);
                    } else {
                        params.set('period', this.period);
                        params.set('days', this.period === 'all' ? '365' : this.period);
                    }

                    if (this.categoryId) params.set('category_id', this.categoryId);
                    if (this.locationId) params.set('storage_location_id', this.locationId);
                    if (this.supplierId) params.set('supplier_id', this.supplierId);
                    if (this.movementType) params.set('movement_type', this.movementType);
                    if (this.status && this.status !== 'all') params.set('stock_status', this.status);

                    window.himsNavigate(config.dashboardUrl + '?' + params.toString());
                },

                async generate() {
                    this.errorMessage = '';
                    this.successMessage = '';

                    // Validation
                    if (['procurement_expense', 'spend_by_supplier'].includes(this.reportType) && !config.canViewFinancial) {
                        this.errorMessage = 'You do not have permission to view or export procurement financial reports.';
                        return;
                    }

                    if (this.period === 'custom') {
                        if (!this.fromDate) {
                            this.errorMessage = 'Please provide a start date for the custom date range.';
                            return;
                        }
                        if (!this.toDate) {
                            this.errorMessage = 'Please provide an end date for the custom date range.';
                            return;
                        }
                        if (this.fromDate > this.toDate) {
                            this.errorMessage = 'The start date cannot be later than the end date.';
                            return;
                        }
                        if (this.toDate > config.todayDate) {
                            this.errorMessage = 'The end date cannot be a future date.';
                            return;
                        }
                    }

                    this.loading = true;

                    const params = new URLSearchParams();
                    params.set('report_type', this.reportType);
                    params.set('format', this.format);
                    params.set('period', this.period);
                    if (this.period === 'custom') {
                        params.set('from', this.fromDate);
                        params.set('to', this.toDate);
                    } else {
                        params.set('days', this.period === 'all' ? '3650' : this.period);
                    }

                    if (this.categoryId) params.set('category_id', this.categoryId);
                    if (this.locationId) params.set('storage_location_id', this.locationId);
                    if (this.supplierId) params.set('supplier_id', this.supplierId);
                    if (this.movementType) params.set('movement_type', this.movementType);
                    if (this.status && this.status !== 'all') params.set('status', this.status);
                    if (this.sortBy) params.set('sort_by', this.sortBy);
                    if (this.sortDir) params.set('sort_direction', this.sortDir);

                    const url = config.generateUrl + '?' + params.toString();

                    if (this.format === 'pdf' || this.format === 'print') {
                        window.open(url, '_blank');
                        this.loading = false;
                        this.successMessage = this.format === 'pdf'
                            ? 'PDF report generated successfully.'
                            : 'Official report opened in print view.';
                        return;
                    }

                    try {
                        const response = await fetch(url, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': this.format === 'json' ? 'application/json' : '*/*'
                            }
                        });

                        if (!response.ok) {
                            let errMsg = 'Failed to generate report.';
                            try {
                                const errData = await response.json();
                                errMsg = errData.message || (errData.errors ? Object.values(errData.errors).flat().join(' ') : errMsg);
                            } catch (e) {
                                errMsg = `Error ${response.status}: ${response.statusText}`;
                            }
                            throw new Error(errMsg);
                        }

                        const blob = await response.blob();
                        let filename = `hims-${this.reportType}-${new Date().toISOString().slice(0, 10)}.${this.format === 'excel' ? 'xls' : this.format}`;

                        const disposition = response.headers.get('content-disposition');
                        if (disposition && disposition.indexOf('filename=') !== -1) {
                            const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
                            if (matches != null && matches[1]) {
                                filename = matches[1].replace(/['"]/g, '');
                            }
                        }

                        const blobUrl = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = blobUrl;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(blobUrl);
                        a.remove();

                        this.successMessage = `Successfully generated and downloaded ${filename}`;
                    } catch (err) {
                        this.errorMessage = err.message || 'An unexpected error occurred while generating the report.';
                    } finally {
                        this.loading = false;
                    }
                }
            };
        }

        function dashboardTimelineFilter(config) {
            return {
                isOpen: false,
                period: config.period || '30',
                from: config.from || '',
                to: config.to || '',
                isCustom: config.isCustom || false,
                error: '',

                selectPreset(days) {
                    this.period = days;
                    this.isCustom = false;
                    this.error = '';
                    const params = new URLSearchParams();
                    params.set('period', days);
                    params.set('days', days === 'all' ? '365' : days);
                    window.himsNavigate(config.dashboardUrl + '?' + params.toString());
                },

                applyCustom() {
                    this.error = '';
                    if (!this.from) {
                        this.error = 'Please enter a start date.';
                        return;
                    }
                    if (!this.to) {
                        this.error = 'Please enter an end date.';
                        return;
                    }
                    if (this.from > this.to) {
                        this.error = 'Start date cannot be later than end date.';
                        return;
                    }
                    if (this.to > config.today) {
                        this.error = 'End date cannot be in the future.';
                        return;
                    }

                    const params = new URLSearchParams();
                    params.set('period', 'custom');
                    params.set('from', this.from);
                    params.set('to', this.to);
                    window.himsNavigate(config.dashboardUrl + '?' + params.toString());
                }
            };
        }
    </script>
</x-app-layout>
