<x-layouts.supplier title="Supplier Dashboard">
    @php
        $metrics = [
            ['label' => 'Orders requiring action', 'value' => $ordersRequiringAction, 'icon' => 'clipboard-document-check', 'tone' => 'warning', 'hint' => 'Review pending purchase orders', 'url' => route('supplier.orders.index')],
            ['label' => 'Upcoming deliveries', 'value' => $upcomingDeliveries, 'icon' => 'truck', 'tone' => 'primary', 'hint' => 'Track scheduled shipments', 'url' => route('supplier.orders.index')],
            ['label' => 'Open discrepancies', 'value' => $openDiscrepancies, 'icon' => 'exclamation-triangle', 'tone' => 'danger', 'hint' => 'Resolve receiving issues', 'url' => route('supplier.discrepancies.index')],
            ['label' => 'Available RFQs', 'value' => $availableRfqs, 'icon' => 'document-text', 'tone' => 'success', 'hint' => 'Review open quotation requests', 'url' => route('supplier.rfqs.index')],
        ];
    @endphp

    <x-ui.page-header title="Supplier Dashboard" subtitle="Orders, deliveries, compliance, invoices, and performance for your organization." />

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
        <section class="relative isolate min-h-[23.5rem] overflow-hidden rounded-2xl border border-neutral-200 bg-gradient-to-br from-white via-white to-cyan-50/60 shadow-sm dark:border-neutral-800 dark:from-neutral-900 dark:via-neutral-900 dark:to-cyan-950/20" aria-labelledby="recent-orders-title">
            <div aria-hidden="true" class="pointer-events-none absolute -right-[8%] -top-[56%] -z-10 h-[24rem] w-[68%] rounded-full bg-cyan-50/70 dark:bg-cyan-950/15"></div>
            <header class="relative flex flex-col gap-4 border-b border-neutral-200 px-5 py-5 dark:border-neutral-800 sm:flex-row sm:items-center sm:justify-between sm:px-7">
                <div class="flex min-w-0 items-center gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300"><x-ui.icon name="clipboard-document-check" class="h-6 w-6" /></span>
                    <div class="min-w-0">
                        <h2 id="recent-orders-title" class="text-xl font-bold tracking-tight text-neutral-950 dark:text-white">Recent purchase orders</h2>
                        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Hospital-issued orders for your supplier account.</p>
                    </div>
                </div>
                <x-ui.button href="{{ route('supplier.orders.index') }}" variant="secondary" size="lg">View all <x-ui.icon name="chevron-right" class="h-4 w-4" /></x-ui.button>
            </header>
            <div class="relative p-4 sm:p-7">
                <x-ui.table :sticky-header="false">
                    <x-slot:head>
                        <x-ui.table.th class="!bg-primary-50/60 px-6 py-4 dark:!bg-primary-950/30">Purchase order no.</x-ui.table.th>
                        <x-ui.table.th class="!bg-primary-50/60 px-6 py-4 dark:!bg-primary-950/30">Status</x-ui.table.th>
                        <x-ui.table.th class="!bg-primary-50/60 px-6 py-4 dark:!bg-primary-950/30">Amount</x-ui.table.th>
                        <x-ui.table.th class="!bg-primary-50/60 px-6 py-4 dark:!bg-primary-950/30">Actions</x-ui.table.th>
                    </x-slot:head>
                    @forelse ($recentOrders as $order)
                        <x-ui.table.row>
                            <x-ui.table.td class="px-6 py-5"><span class="font-semibold text-neutral-950 dark:text-white">{{ $order->po_number }}</span></x-ui.table.td>
                            <x-ui.table.td class="px-6 py-5"><x-ui.badge :status="$order->status" dot class="px-3 py-1 text-sm" /></x-ui.table.td>
                            <x-ui.table.td class="px-6 py-5"><span class="font-semibold tabular-nums text-neutral-950 dark:text-white">&#8369;{{ number_format($order->total_amount, 2) }}</span></x-ui.table.td>
                            <x-ui.table.td class="px-6 py-5"><a class="inline-flex min-h-9 items-center gap-1 rounded-full bg-primary-50 px-3.5 font-semibold text-primary-700 transition-colors hover:bg-primary-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:bg-primary-950/60 dark:text-primary-300 dark:hover:bg-primary-900/70 dark:focus-visible:ring-offset-neutral-900" href="{{ route('supplier.orders.show', $order) }}">View <x-ui.icon name="chevron-right" class="h-4 w-4" /></a></x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty colspan="4" artwork="procurement" title="No purchase orders yet" message="New hospital-issued orders will appear here." />
                    @endforelse
                </x-ui.table>
            </div>
        </section>

        <aside class="min-h-[23.5rem] overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900" aria-labelledby="account-status-title">
            <header class="relative isolate flex min-h-[5.5rem] items-center gap-4 overflow-hidden border-b border-neutral-200 px-5 py-5 dark:border-neutral-800 sm:px-7">
                <img src="{{ asset('img/hims-supplier-dashboard-hero-light.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-0 -z-10 hidden h-full w-[58%] object-cover object-right opacity-20 [mask-image:linear-gradient(to_right,transparent,black_55%)] sm:block dark:hidden">
                <img src="{{ asset('img/hims-supplier-dashboard-hero.png') }}" alt="" aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-0 -z-10 hidden h-full w-[58%] object-cover object-right opacity-20 [mask-image:linear-gradient(to_right,transparent,black_55%)] dark:sm:block">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-950/80 dark:text-primary-300"><x-ui.icon name="building-office-2" class="h-6 w-6" /></span>
                <h2 id="account-status-title" class="text-xl font-bold tracking-tight text-neutral-950 dark:text-white">Account status</h2>
            </header>
            <dl class="divide-y divide-neutral-200 px-5 dark:divide-neutral-800 sm:px-7">
                <div class="flex items-center gap-5 py-5"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-success-50 text-success-700 dark:bg-success-950/50 dark:text-success-300"><x-ui.icon name="shield-check" class="h-6 w-6" /></span><div class="min-w-0"><dt class="text-sm text-neutral-500 dark:text-neutral-400">Compliance</dt><dd class="mt-1 text-lg font-bold leading-tight text-neutral-950 dark:text-white">{{ str($supplier->complianceState())->headline() }}</dd></div></div>
                <div class="flex items-center gap-5 py-5"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"><x-ui.icon name="document-text" class="h-6 w-6" /></span><div class="min-w-0"><dt class="text-sm text-neutral-500 dark:text-neutral-400">Invoice queue</dt><dd class="mt-1 text-lg font-bold leading-tight text-neutral-950 dark:text-white">{{ $invoices->sum() }} submitted records</dd></div></div>
                <div class="flex items-center gap-5 py-5"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300"><x-ui.icon name="chart-bar" class="h-6 w-6" /></span><div class="min-w-0"><dt class="text-sm text-neutral-500 dark:text-neutral-400">Latest score</dt><dd class="mt-1 text-lg font-bold leading-tight tabular-nums text-neutral-950 dark:text-white">{{ $scorecard ? number_format($scorecard->total_score, 1).'%' : 'Not yet scored' }}</dd></div></div>
            </dl>
        </aside>
    </div>
</x-layouts.supplier>
