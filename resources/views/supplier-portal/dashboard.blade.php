<x-layouts.supplier title="Supplier Dashboard">
    @php
        $metrics = [
            ['label' => 'Orders requiring action', 'value' => $ordersRequiringAction, 'icon' => 'clipboard-document-check', 'tone' => 'warning', 'hint' => 'Review pending purchase orders', 'url' => route('supplier.orders.index')],
            ['label' => 'Upcoming deliveries', 'value' => $upcomingDeliveries, 'icon' => 'truck', 'tone' => 'primary', 'hint' => 'Track scheduled shipments', 'url' => route('supplier.orders.index')],
            ['label' => 'Open discrepancies', 'value' => $openDiscrepancies, 'icon' => 'exclamation-triangle', 'tone' => 'danger', 'hint' => 'Resolve receiving issues', 'url' => route('supplier.discrepancies.index')],
            ['label' => 'Available RFQs', 'value' => $availableRfqs, 'icon' => 'document-text', 'tone' => 'success', 'hint' => 'Review open quotation requests', 'url' => route('supplier.rfqs.index')],
        ];
    @endphp

    <section class="supplier-dashboard-hero relative isolate min-h-[10.5rem] overflow-hidden rounded-2xl border border-slate-300 px-6 py-8 dark:border-slate-700 sm:px-8 lg:px-10 lg:py-9" aria-labelledby="supplier-dashboard-title">
        <img src="{{ asset('img/hims-supplier-dashboard-hero-light.png') }}" alt="" class="absolute inset-0 h-full w-full object-cover object-center dark:hidden" aria-hidden="true">
        <img src="{{ asset('img/hims-supplier-dashboard-hero.png') }}" alt="" class="absolute inset-0 hidden h-full w-full object-cover object-center dark:block" aria-hidden="true">
        <div class="supplier-dashboard-hero__scrim absolute inset-0" aria-hidden="true"></div>
        <div class="relative z-10 max-w-2xl">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-primary-700 dark:text-primary-200">Supplier portal</p>
            <h1 id="supplier-dashboard-title" class="mt-2 text-3xl font-bold tracking-tight text-slate-950 dark:text-white sm:text-[2rem] sm:leading-tight">Supplier Dashboard</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-700 dark:text-slate-200 sm:text-base">Orders, deliveries, compliance, invoices, and performance for your organization.</p>
        </div>
    </section>

    <section class="mt-6 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4 xl:gap-4" aria-label="Supplier operational summary">
        @foreach ($metrics as $metric)
            <x-ui.stat
                :label="$metric['label']"
                :value="number_format($metric['value'])"
                :icon="$metric['icon']"
                :tone="$metric['tone']"
                :hint="$metric['hint']"
                :href="$metric['url']"
            />
        @endforeach
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,2.5fr)_minmax(19rem,0.9fr)]">
        <section class="min-h-[22.5rem] overflow-hidden rounded-2xl border border-neutral-200 bg-white dark:border-slate-700/80 dark:bg-slate-900/75" aria-labelledby="recent-orders-title">
            <header class="flex flex-col gap-4 border-b border-neutral-200 px-5 py-6 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div class="flex min-w-0 items-center gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300"><x-ui.icon name="clipboard-document-check" class="h-6 w-6" /></span>
                    <div class="min-w-0">
                        <h2 id="recent-orders-title" class="text-lg font-bold text-neutral-950 dark:text-white">Recent purchase orders</h2>
                        <p class="mt-0.5 text-sm text-neutral-500 dark:text-slate-400">Hospital-issued orders for your supplier account.</p>
                    </div>
                </div>
                <x-ui.button href="{{ route('supplier.orders.index') }}" variant="secondary" class="dark:!border-slate-600 dark:!bg-transparent dark:hover:!bg-slate-800">View all <x-ui.icon name="chevron-right" class="h-4 w-4" /></x-ui.button>
            </header>
            <div class="p-4 sm:p-6">
                <x-ui.table :sticky-header="false">
                    <x-slot:head>
                        <x-ui.table.th>Purchase order no.</x-ui.table.th>
                        <x-ui.table.th>Status</x-ui.table.th>
                        <x-ui.table.th>Amount</x-ui.table.th>
                        <x-ui.table.th>Actions</x-ui.table.th>
                    </x-slot:head>
                    @forelse ($recentOrders as $order)
                        <x-ui.table.row>
                            <x-ui.table.td><span class="font-medium text-neutral-950 dark:text-white">{{ $order->po_number }}</span></x-ui.table.td>
                            <x-ui.table.td><x-ui.badge :status="$order->status" /></x-ui.table.td>
                            <x-ui.table.td><span class="font-medium tabular-nums">₱{{ number_format($order->total_amount, 2) }}</span></x-ui.table.td>
                            <x-ui.table.td><a class="inline-flex min-h-9 items-center gap-1 rounded-md px-2 font-semibold text-primary-700 hover:bg-primary-50 dark:text-primary-300 dark:hover:bg-primary-950/60" href="{{ route('supplier.orders.show', $order) }}">View <x-ui.icon name="chevron-right" class="h-4 w-4" /></a></x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty colspan="4" title="No purchase orders yet" message="New hospital-issued orders will appear here." />
                    @endforelse
                </x-ui.table>
            </div>
        </section>

        <aside class="min-h-[22.5rem] overflow-hidden rounded-2xl border border-neutral-200 bg-white dark:border-slate-700/80 dark:bg-slate-900/75" aria-labelledby="account-status-title">
            <header class="flex items-center gap-4 border-b border-neutral-200 px-5 py-6 dark:border-slate-800">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300"><x-ui.icon name="building-office-2" class="h-6 w-6" /></span>
                <h2 id="account-status-title" class="text-lg font-bold text-neutral-950 dark:text-white">Account status</h2>
            </header>
            <dl class="divide-y divide-neutral-200 px-5 dark:divide-slate-800">
                <div class="flex items-center gap-4 py-5"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-success-50 text-success-700 dark:bg-success-950/50 dark:text-success-300"><x-ui.icon name="shield-check" class="h-5 w-5" /></span><div><dt class="text-sm text-neutral-500 dark:text-slate-400">Compliance</dt><dd class="mt-1 font-semibold text-neutral-950 dark:text-white">{{ str($supplier->complianceState())->headline() }}</dd></div></div>
                <div class="flex items-center gap-4 py-5"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-600 dark:bg-slate-800 dark:text-slate-300"><x-ui.icon name="document-text" class="h-5 w-5" /></span><div><dt class="text-sm text-neutral-500 dark:text-slate-400">Invoice queue</dt><dd class="mt-1 font-semibold text-neutral-950 dark:text-white">{{ $invoices->sum() }} submitted records</dd></div></div>
                <div class="flex items-center gap-4 py-5"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-600 dark:bg-slate-800 dark:text-slate-300"><x-ui.icon name="chart-bar" class="h-5 w-5" /></span><div><dt class="text-sm text-neutral-500 dark:text-slate-400">Latest score</dt><dd class="mt-1 font-semibold text-neutral-950 dark:text-white">{{ $scorecard ? number_format($scorecard->total_score, 1).'%' : 'Not yet scored' }}</dd></div></div>
            </dl>
        </aside>
    </div>
</x-layouts.supplier>
