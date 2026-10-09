<x-app-layout>
    <div class="space-y-4">
        <x-ui.page-header
            title="Stock Adjustments"
            :breadcrumbs="['Home' => route(\App\Support\AuthenticationContext::dashboardRoute()), 'Inventory' => route('inventory.items'), 'Stock Adjustments' => null]"
        />

        {{-- Consolidated Inventory Workflow Navigation --}}
        @include('inventory.partials.workflow_nav')

        {{-- Flash Alerts --}}
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs sm:text-sm text-emerald-800 flex items-center justify-between shadow-2xs dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                    <span class="font-semibold">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('info'))
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-xs sm:text-sm text-sky-800 flex items-center justify-between shadow-2xs dark:border-sky-800/60 dark:bg-sky-950/40 dark:text-sky-300">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ session('info') }}</span>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs sm:text-sm text-rose-800 shadow-2xs dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-300">
                <div class="flex items-center gap-2 font-semibold">
                    <svg class="h-4 w-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <span>Stock Adjustment Error:</span>
                </div>
                <ul class="mt-2 list-inside list-disc text-xs space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Summary Cards --}}
        <div class="grid gap-3.5 grid-cols-2 sm:grid-cols-4">
            <x-ui.stat compact label="Total Adjustments" :value="$metrics['total']" icon="adjustments-horizontal" tone="neutral" hint="Documented adjustments" :details="$metricDetails['total']" summary-title="Latest stock adjustments" :href="route('inventory.adjustments').'#adjustment-registry'" />
            <x-ui.stat compact label="Pending Review" :value="$metrics['pending_review']" icon="clipboard-document-list" tone="warning" hint="Tier 1 supervisor review" :details="$metricDetails['pending_review']" summary-title="Adjustments awaiting Tier 1 review" :href="route('inventory.adjustments', ['status' => 'pending_approval']).'#adjustment-registry'" />
            <x-ui.stat compact label="Dual-Tier Required" :value="$metrics['dual_tier']" icon="scale" tone="danger" hint="Over ₱25,000 threshold" :details="$metricDetails['dual_tier']" summary-title="Adjustments awaiting Tier 2 approval" :href="route('inventory.adjustments', ['status' => 'pending_second_approval']).'#adjustment-registry'" />
            <x-ui.stat compact label="Posted &amp; Reconciled" :value="$metrics['posted']" icon="check-circle" tone="success" hint="Written to ledger" :details="$metricDetails['posted']" summary-title="Adjustments posted to the ledger" :href="route('inventory.adjustments', ['status' => 'posted']).'#adjustment-registry'" />
        </div>

        @can('adjust_stock')
        {{-- REQUEST STOCK ADJUSTMENT MODAL (CENTERED VIEWPORT DIALOG) --}}
        <x-ui.modal name="request-stock-adjustment" maxWidth="2xl">
            <x-slot:header>
                <div>
                    <h2 class="text-base font-bold text-neutral-900 dark:text-neutral-100">Request Stock Adjustment</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                        Adjustments create formal requests and update the immutable stock movement ledger once authorized.
                    </p>
                </div>
            </x-slot:header>

            <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="space-y-4"
                  data-confirm-title="Confirm stock adjustment"
                  data-confirm-message="Are you sure you want to apply this stock adjustment?"
                  data-confirm-label="Apply Adjustment">
                @csrf

                @if ($preselectedItem)
                    <div class="rounded-xl border border-amber-200 dark:border-amber-900/60 bg-amber-50/80 dark:bg-amber-950/30 p-3 text-xs text-amber-950 dark:text-amber-200 flex items-center justify-between shadow-2xs">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 shrink-0">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                                </svg>
                            </span>
                            <div>
                                <p class="font-bold text-amber-950 dark:text-amber-100">Contextual Item: <span class="font-extrabold underline decoration-amber-300 dark:decoration-amber-600 underline-offset-2">{{ $preselectedItem->name }}</span></p>
                                <p class="text-[11px] text-amber-800 dark:text-amber-300 mt-0.5">
                                    SKU: <strong class="font-mono text-amber-900 dark:text-amber-200">{{ $preselectedItem->sku }}</strong> &bull; 
                                    On Hand: <strong class="tabular-nums text-amber-900 dark:text-amber-100">{{ $preselectedItem->quantity_on_hand }} {{ $preselectedItem->unit }}</strong> &bull; 
                                    Unit Cost: <strong class="tabular-nums text-amber-900 dark:text-amber-100">₱{{ number_format($preselectedItem->unit_cost ?? 0, 2) }}</strong>
                                    @if ($preselectedItem->defaultLocation)
                                        &bull; Default Location: <strong class="text-amber-900 dark:text-amber-200">{{ $preselectedItem->defaultLocation->name }}</strong>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-amber-200/90 dark:bg-amber-900/80 px-2.5 py-0.5 text-[10px] font-bold text-amber-900 dark:text-amber-200">
                            Auto-Carried Forward
                        </span>
                    </div>
                @endif

                <div class="grid gap-3.5 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Inventory Item <span class="text-rose-500">*</span>
                        </label>
                        <select name="item_id" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs font-medium">
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}" @selected(old('item_id', $preselectedItem?->id) == $item->id)>
                                    {{ $item->name }} ({{ $item->sku ?? 'No SKU' }}) &bull; {{ number_format((int) $item->quantity_on_hand) }} on hand &bull; ₱{{ number_format($item->unit_cost ?? 0, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Storage Location <span class="text-rose-500">*</span>
                        </label>
                        <select name="location_id" required class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs font-medium">
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" @selected(old('location_id', $preselectedItem?->default_location_id) == $location->id)>
                                    {{ $location->name }}{{ $location->code ? ' ('.$location->code.')' : '' }} &bull; {{ $location->zone ?? 'Unrestricted' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Adjustment Type <span class="text-rose-500">*</span>
                        </label>
                        <select name="adjustment_type" class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs font-medium">
                            <option value="increase" @selected(old('adjustment_type', $preselectedType ?? '') === 'increase')>Increase (+)</option>
                            <option value="decrease" @selected(old('adjustment_type', $preselectedType ?? '') === 'decrease')>Decrease (-)</option>
                            <option value="correction" @selected(old('adjustment_type', $preselectedType ?? 'correction') === 'correction')>Count Reconciliation / Set Exact</option>
                            <option value="damage" @selected(old('adjustment_type', $preselectedType ?? '') === 'damage')>Damage Write-off (-)</option>
                            <option value="loss" @selected(old('adjustment_type', $preselectedType ?? '') === 'loss')>Loss / Theft (-)</option>
                            <option value="expiry" @selected(old('adjustment_type', $preselectedType ?? '') === 'expiry')>Expired Stock Disposal (-)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Quantity <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" name="quantity" min="0" value="{{ old('quantity') }}" required
                               class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs font-semibold py-2 px-3" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-neutral-700 dark:text-neutral-300 mb-1">
                            Detailed Reason &amp; Clinical Justification <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="reason" value="{{ old('reason', $preselectedItem ? 'Reconciliation adjustment for ' . $preselectedItem->name . ' (' . ($preselectedItem->quantity_on_hand <= 0 ? 'depleted stock' : 'low stock') . ')' : '') }}" required placeholder="e.g. Annual physical count variance, cold chain excursion write-off, bottle breakage in Ward 2"
                               class="block w-full rounded-lg border-neutral-300 dark:border-neutral-700 bg-white dark:bg-neutral-800 text-neutral-800 dark:text-neutral-100 shadow-2xs focus:border-amber-500 focus:ring-1 focus:ring-amber-500 text-xs py-2 px-3" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-neutral-100 dark:border-neutral-800">
                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'request-stock-adjustment')">
                        Cancel
                    </x-ui.button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 hover:bg-amber-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                        Submit Adjustment for Authorization
                    </button>
                </div>
            </form>
        </x-ui.modal>

        @if ($errors->any() || $preselectedItem)
            <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'request-stock-adjustment'))"></div>
        @endif
        @endcan

        {{-- Adjustments Registry Table --}}
        <x-ui.card id="adjustment-registry" :padding="false">
            <x-slot:header>
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Stock Adjustment Registry</h2>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $activeStatusLabel ? 'Showing '.$activeStatusLabel.' adjustments.' : 'History of requested, authorized, and posted inventory balance corrections.' }}
                    </p>
                </div>
            </x-slot:header>
            <x-slot:actions>
                <form method="GET" action="{{ route('inventory.adjustments') }}" x-data="{ dateFrom: {{ Js::from(request('date_from', '')) }}, dateTo: {{ Js::from(request('date_to', '')) }} }" class="flex flex-wrap items-center gap-2">
                    @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
                    <label><span class="sr-only">Adjustment date from</span><input type="date" name="date_from" x-model="dateFrom" @change="if (dateTo && dateFrom > dateTo) dateTo = dateFrom" max="{{ now()->toDateString() }}" class="rounded-lg border-neutral-300 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"></label>
                    <span class="inline-flex min-h-8 shrink-0 items-center text-xs text-neutral-400">to</span>
                    <label><span class="sr-only">Adjustment date to</span><input type="date" name="date_to" x-model="dateTo" @change="if (dateFrom && dateTo < dateFrom) dateTo = dateFrom" :min="dateFrom || null" max="{{ now()->toDateString() }}" class="rounded-lg border-neutral-300 py-1.5 text-xs dark:border-neutral-700 dark:bg-neutral-800"></label>
                    <x-ui.button type="submit" size="sm" icon="funnel">Apply</x-ui.button>
                    @if(request()->hasAny(['status', 'date_from', 'date_to']))<x-ui.button size="sm" variant="secondary" :href="route('inventory.adjustments').'#adjustment-registry'">Clear</x-ui.button>@endif
                </form>
            </x-slot:actions>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-neutral-600 dark:text-neutral-300 divide-y divide-neutral-200 dark:divide-neutral-800">
                    <thead class="bg-neutral-50 dark:bg-neutral-800/60 text-neutral-500 dark:text-neutral-400 font-semibold border-b border-neutral-200 dark:border-neutral-800">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Adjustment #</th>
                            <th class="px-5 py-3 font-semibold">Item &amp; Location</th>
                            <th class="px-5 py-3 font-semibold text-right">Qty Delta</th>
                            <th class="px-5 py-3 font-semibold text-right">Financial Impact</th>
                            <th class="px-5 py-3 font-semibold">Requested By</th>
                            <th class="px-5 py-3 font-semibold">Status &amp; Approvals</th>
                            <th class="px-5 py-3 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800 bg-white dark:bg-neutral-900">
                        @forelse($adjustments as $adj)
                            <tr class="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/50 transition-colors">
                                <td class="px-5 py-3.5 font-mono font-bold text-neutral-900 dark:text-neutral-100">
                                    {{ $adj->adjustment_number }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <p class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $adj->item->name ?? 'Item #' . $adj->inventory_item_id }}</p>
                                    <p class="text-[11px] text-neutral-500 dark:text-neutral-400">{{ $adj->location->name ?? 'Main Storage' }} &bull; {{ ucfirst($adj->adjustment_type) }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono font-bold {{ $adj->quantity_adjusted < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ $adj->quantity_adjusted > 0 ? '+' : '' }}{{ number_format($adj->quantity_adjusted) }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono font-medium text-neutral-900 dark:text-neutral-100">
                                    ₱{{ number_format(abs($adj->total_cost), 2) }}
                                    @if(abs($adj->total_cost) > 25000)
                                        <span class="block text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wide">&gt; ₱25k Dual Threshold</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-xs">
                                    <p class="font-medium text-neutral-800 dark:text-neutral-200">{{ $adj->requestedBy->name ?? 'System' }}</p>
                                    <p class="text-[11px] text-neutral-400">{{ $adj->created_at ? $adj->created_at->format('M d, Y') : '' }}</p>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if($adj->status === 'pending_approval')
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                            Tier 1 Review Pending
                                        </span>
                                    @elseif($adj->status === 'pending_second_approval')
                                        <span class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-semibold text-purple-800 dark:bg-purple-950/60 dark:text-purple-300">
                                            Tier 2 Executive Sign-Off Required
                                        </span>
                                    @elseif($adj->status === 'posted')
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            Posted to Ledger
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300">
                                            {{ ucfirst($adj->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    @if(in_array($adj->status, ['pending_approval', 'pending_second_approval']) && auth()->user()->can(\App\Enums\Permission::ApproveAdjustment->value))
                                        @if($adj->status === 'pending_second_approval' && auth()->id() === $adj->approved_by_id)
                                            <span class="text-xs text-neutral-400 italic">Signed (Tier 1)</span>
                                        @else
                                            <form action="{{ route('inventory.adjustments.approve', $adj) }}" method="POST" class="inline"
                                                  data-confirm-title="Approve stock adjustment"
                                                  data-confirm-message="Are you sure you want to authorize this stock adjustment? On-hand inventory will be adjusted immediately."
                                                  data-confirm-label="Authorize Adjustment">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 transition">
                                                    {{ $adj->status === 'pending_second_approval' ? 'Tier 2 Authorize' : 'Authorize Adjustment' }}
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <span class="text-xs text-neutral-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-ui.table.empty colspan="7" artwork="inventory" title="No inventory adjustments" message="Approved quantity corrections and their reasons will appear here." />
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($adjustments->hasPages())
                <div class="border-t border-neutral-200 dark:border-neutral-800 px-5 py-3">
                    {{ $adjustments->links() }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>
