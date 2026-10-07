<x-layouts.supplier title="Purchase Orders">
    <style>
        [data-orders-header] .hims-page-header {
            --hims-header-image: url('{{ asset('img/hims-supplier-orders-hero-day.png') }}');
            box-sizing: border-box;
            width: 100% !important;
            height: 112px !important;
            min-height: 112px !important;
            max-height: 112px !important;
        }

        .dark [data-orders-header] .hims-page-header {
            --hims-header-image: url('{{ asset('img/hims-supplier-orders-hero-night.png') }}');
        }
    </style>

    <div data-orders-header>
        <x-ui.page-header
            title="Purchase Orders"
            subtitle="Hospital-issued orders for your supplier account."
            :breadcrumbs="['Dashboard' => route('supplier.dashboard'), 'Purchase Orders' => null]"
        />
    </div>

    <div class="mt-8">
        <x-ui.table :sticky-header="false">
            <x-slot:head>
                <x-ui.table.th>PO number</x-ui.table.th>
                <x-ui.table.th class="hidden md:table-cell">Scheduled delivery</x-ui.table.th>
                <x-ui.table.th>Status</x-ui.table.th>
                <x-ui.table.th class="hidden sm:table-cell">Total</x-ui.table.th>
                <x-ui.table.th align="right">Actions</x-ui.table.th>
            </x-slot:head>

            @forelse ($orders as $order)
                <x-ui.table.row>
                    <x-ui.table.td><span class="font-medium text-neutral-950 dark:text-white">{{ $order->po_number }}</span></x-ui.table.td>
                    <x-ui.table.td class="hidden md:table-cell">{{ $order->delivery_date?->format('M d, Y') ?? 'Not set' }}</x-ui.table.td>
                    <x-ui.table.td><x-ui.badge :status="$order->status" dot class="px-3 py-1 text-sm" /></x-ui.table.td>
                    <x-ui.table.td class="hidden font-medium tabular-nums sm:table-cell">₱{{ number_format($order->total_amount, 2) }}</x-ui.table.td>
                    <x-ui.table.td align="right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <x-ui.button
                                type="button"
                                variant="secondary"
                                size="sm"
                                x-data
                                x-on:click="$dispatch('open-modal', 'supplier-order-quick-view-{{ $order->id }}')"
                            >
                                Quick view
                            </x-ui.button>
                            <x-ui.button href="{{ route('supplier.orders.show', $order) }}" size="sm">Open</x-ui.button>
                        </div>
                    </x-ui.table.td>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="5" title="No purchase orders found" message="Hospital-issued orders will appear here." />
            @endforelse
        </x-ui.table>

        @foreach ($orders as $order)
            <x-ui.modal
                name="supplier-order-quick-view-{{ $order->id }}"
                title="Purchase order {{ $order->po_number }}"
                maxWidth="7xl"
                :flush="true"
                :hide-header="true"
            >
                <div class="relative grid min-h-[min(46rem,calc(100dvh-2rem))] lg:grid-cols-[17rem_minmax(0,1fr)]">
                    <aside class="relative hidden min-h-full overflow-hidden bg-emerald-950 lg:flex lg:flex-col lg:justify-between" aria-hidden="true">
                        <img src="{{ asset('img/hims-supplier-po-items-day.png') }}" alt="" class="absolute inset-0 h-full w-full object-cover object-center dark:hidden">
                        <img src="{{ asset('img/hims-supplier-po-items-night.png') }}" alt="" class="absolute inset-0 hidden h-full w-full object-cover object-center dark:block">
                        <div class="absolute inset-0 bg-emerald-950/75"></div>
                        <span class="relative m-8 flex h-16 w-16 items-center justify-center rounded-xl border border-emerald-300/30 bg-emerald-500/15 text-white shadow-lg backdrop-blur-sm">
                            <x-ui.icon name="document-text" class="h-8 w-8" />
                        </span>
                        <div class="relative p-8 text-white">
                            <p class="text-2xl font-bold leading-tight">Purchase<br>Order</p>
                            <p class="mt-4 text-sm leading-6 text-emerald-50/85">Review the items, quantities, schedule, and total amount before opening the full workflow.</p>
                        </div>
                    </aside>

                    <section class="relative flex min-w-0 flex-col p-5 sm:p-8 lg:p-10">
                        <button
                            type="button"
                            x-on:click="close()"
                            class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-neutral-500 dark:hover:bg-neutral-800 dark:hover:text-neutral-200 sm:right-6 sm:top-6"
                        >
                            <span class="sr-only">Close purchase order preview</span>
                            <x-ui.icon name="x-mark" class="h-6 w-6" />
                        </button>

                        <header class="pr-12">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-neutral-500 dark:text-neutral-400">Purchase order</p>
                            <h2 id="supplier-order-quick-view-{{ $order->id }}-title" class="mt-2 break-words text-2xl font-bold tracking-[-0.025em] text-neutral-950 dark:text-white sm:text-4xl">{{ $order->po_number }}</h2>
                            <div class="mt-4"><x-ui.badge :status="$order->status" dot class="px-3 py-1.5 text-sm" /></div>
                        </header>

                        <dl class="mt-8 grid gap-5 sm:grid-cols-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700"><x-ui.icon name="calendar" class="h-5 w-5" /></span>
                                <div class="min-w-0"><dt class="text-sm text-neutral-500 dark:text-neutral-400">Order date</dt><dd class="mt-1 font-bold text-neutral-950 dark:text-white">{{ $order->requested_at?->format('M d, Y') ?? 'Not set' }}</dd><p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Created on this date</p></div>
                            </div>
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700"><x-ui.icon name="truck" class="h-5 w-5" /></span>
                                <div class="min-w-0"><dt class="text-sm text-neutral-500 dark:text-neutral-400">Scheduled delivery</dt><dd class="mt-1 font-bold text-neutral-950 dark:text-white">{{ $order->delivery_date?->format('M d, Y') ?? 'Not set' }}</dd><p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Expected arrival</p></div>
                            </div>
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600 ring-1 ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700"><x-ui.icon name="currency-dollar" class="h-5 w-5" /></span>
                                <div class="min-w-0"><dt class="text-sm text-neutral-500 dark:text-neutral-400">Total amount</dt><dd class="mt-1 font-bold tabular-nums text-neutral-950 dark:text-white">&#8369;{{ number_format($order->total_amount, 2) }}</dd><p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Inclusive of all items</p></div>
                            </div>
                        </dl>

                        <div class="my-8 border-t border-neutral-200 dark:border-neutral-800"></div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                            <div class="flex items-center gap-3">
                                <x-ui.icon name="archive-box" class="h-6 w-6 text-neutral-500 dark:text-neutral-400" />
                                <h3 class="text-xl font-bold text-neutral-950 dark:text-white">Order lines</h3>
                            </div>
                            <p class="text-sm font-semibold tabular-nums text-neutral-600 dark:text-neutral-300">{{ $order->lines->count() }} {{ str('item')->plural($order->lines->count()) }} &middot; &#8369;{{ number_format($order->total_amount, 2) }}</p>
                        </div>

                        <div class="mt-4">
                            <x-ui.table :sticky-header="false">
                                <x-slot:head>
                                    <x-ui.table.th>#</x-ui.table.th>
                                    <x-ui.table.th>Item</x-ui.table.th>
                                    <x-ui.table.th>Quantity</x-ui.table.th>
                                    <x-ui.table.th>Unit price</x-ui.table.th>
                                    <x-ui.table.th align="right">Amount</x-ui.table.th>
                                </x-slot:head>

                                @foreach ($order->lines as $line)
                                    <x-ui.table.row>
                                        <x-ui.table.td>{{ $loop->iteration }}</x-ui.table.td>
                                        <x-ui.table.td><span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $line->item->name }}</span></x-ui.table.td>
                                        <x-ui.table.td><span class="tabular-nums">{{ number_format($line->ordered_quantity) }} {{ $line->purchase_unit }}</span></x-ui.table.td>
                                        <x-ui.table.td><span class="tabular-nums">&#8369;{{ number_format($line->unit_price, 2) }}</span></x-ui.table.td>
                                        <x-ui.table.td align="right"><span class="font-semibold tabular-nums text-neutral-900 dark:text-neutral-100">&#8369;{{ number_format($line->total_line_amount, 2) }}</span></x-ui.table.td>
                                    </x-ui.table.row>
                                @endforeach
                            </x-ui.table>
                        </div>

                        <div class="mt-auto flex flex-col-reverse gap-3 pt-8 sm:flex-row sm:justify-end">
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        size="lg"
                        x-data
                        x-on:click="$dispatch('close-modal', 'supplier-order-quick-view-{{ $order->id }}')"
                    >
                        Close
                    </x-ui.button>
                            <x-ui.button href="{{ route('supplier.orders.show', $order) }}" size="lg" icon="arrow-top-right-on-square">Open full order</x-ui.button>
                        </div>
                    </section>
                </div>
            </x-ui.modal>
        @endforeach

        <div class="mt-4">{{ $orders->onEachSide(1)->links() }}</div>
    </div>
</x-layouts.supplier>
