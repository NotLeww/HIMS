<x-layouts.supplier :title="$order->po_number">
    <div class="space-y-6">
        <section class="relative isolate min-h-44 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
            <div class="absolute inset-0 bg-gradient-to-r from-primary-50/80 via-white to-cyan-50/70 dark:from-primary-950/30 dark:via-neutral-900 dark:to-cyan-950/20"></div>
            <img src="{{ asset('img/requisition/'.$orderArtwork) }}" alt="" aria-hidden="true" class="pointer-events-none absolute right-0 top-1/2 hidden h-[130%] w-auto max-w-[42%] -translate-y-1/2 object-contain object-right opacity-45 mix-blend-multiply dark:opacity-20 dark:mix-blend-screen sm:block">

            <div class="relative flex min-h-44 w-full flex-col justify-center p-5 sm:p-7 sm:pr-[34%]">
                <a href="{{ route('supplier.orders.index') }}" class="inline-flex w-fit items-center gap-2 rounded-md text-sm font-medium text-neutral-600 underline-offset-4 hover:text-primary-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:text-neutral-300 dark:hover:text-primary-300 dark:focus-visible:ring-offset-neutral-900">
                    <x-ui.icon name="arrow-left" class="h-4 w-4" />
                    Back to Purchase Orders
                </a>
                <h1 class="mt-4 break-words text-2xl font-bold tracking-[-0.025em] text-neutral-950 dark:text-white sm:text-3xl">{{ $order->po_number }}</h1>
                <div class="mt-3">
                    <x-ui.badge :status="$order->status" dot class="px-3 py-1 text-sm" />
                </div>
            </div>
        </section>

        <section aria-labelledby="order-summary-heading" class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
            <h2 id="order-summary-heading" class="sr-only">Order summary</h2>
            <dl class="grid sm:grid-cols-2 xl:grid-cols-4">
                <div class="flex min-w-0 items-start gap-3 border-b border-neutral-200 p-5 sm:border-r xl:border-b-0 dark:border-neutral-800">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300"><x-ui.icon name="calendar" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Order date</dt>
                        <dd class="mt-1 font-semibold text-neutral-950 dark:text-white">{{ $order->requested_at?->format('M d, Y') ?? 'Not set' }}</dd>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Created on this date</p>
                    </div>
                </div>
                <div class="flex min-w-0 items-start gap-3 border-b border-neutral-200 p-5 xl:border-b-0 xl:border-r dark:border-neutral-800">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"><x-ui.icon name="truck" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Scheduled delivery</dt>
                        <dd class="mt-1 font-semibold text-neutral-950 dark:text-white">{{ $order->delivery_date?->format('M d, Y') ?? 'Not set' }}</dd>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Expected arrival</p>
                    </div>
                </div>
                <div class="flex min-w-0 items-start gap-3 border-b border-neutral-200 p-5 sm:border-r xl:border-b-0 dark:border-neutral-800">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300"><x-ui.icon name="currency-dollar" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Total amount</dt>
                        <dd class="mt-1 font-semibold tabular-nums text-neutral-950 dark:text-white">&#8369;{{ number_format($order->total_amount, 2) }}</dd>
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Inclusive of all items</p>
                    </div>
                </div>
                <div class="flex min-w-0 items-start gap-3 p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"><x-ui.icon name="document-text" class="h-5 w-5" /></span>
                    <div class="min-w-0">
                        <dt class="text-xs font-medium text-neutral-500 dark:text-neutral-400">Status</dt>
                        <dd class="mt-2"><x-ui.badge :status="$order->status" dot /></dd>
                    </div>
                </div>
            </dl>
        </section>

        <section aria-labelledby="order-lines-heading" class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex flex-col gap-2 border-b border-neutral-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-800">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"><x-ui.icon name="archive-box" class="h-5 w-5" /></span>
                    <h2 id="order-lines-heading" class="text-base font-bold text-neutral-950 dark:text-white">Order lines</h2>
                </div>
                <p class="text-sm font-semibold tabular-nums text-neutral-600 dark:text-neutral-300">{{ $order->lines->count() }} {{ str('item')->plural($order->lines->count()) }} &middot; &#8369;{{ number_format($order->total_amount, 2) }}</p>
            </div>

            <div class="p-4 sm:p-5">
                <x-ui.table :sticky-header="false">
                    <x-slot:head>
                        <x-ui.table.th class="hidden sm:table-cell">#</x-ui.table.th>
                        <x-ui.table.th>Item</x-ui.table.th>
                        <x-ui.table.th>Quantity</x-ui.table.th>
                        <x-ui.table.th>Unit price</x-ui.table.th>
                        <x-ui.table.th align="right">Amount</x-ui.table.th>
                    </x-slot:head>

                    @forelse ($order->lines as $line)
                        <x-ui.table.row>
                            <x-ui.table.td class="hidden sm:table-cell" muted>{{ $loop->iteration }}</x-ui.table.td>
                            <x-ui.table.td>
                                <div class="flex min-w-0 items-center gap-3">
                                    <x-ui.item-icon :item="$line->item" />
                                    <span class="font-semibold text-neutral-950 dark:text-white">{{ $line->item->name }}</span>
                                </div>
                            </x-ui.table.td>
                            <x-ui.table.td><span class="whitespace-nowrap tabular-nums">{{ number_format($line->ordered_quantity) }} {{ $line->purchase_unit }}</span></x-ui.table.td>
                            <x-ui.table.td><span class="whitespace-nowrap tabular-nums">&#8369;{{ number_format($line->unit_price, 2) }}</span></x-ui.table.td>
                            <x-ui.table.td align="right"><span class="whitespace-nowrap font-semibold tabular-nums text-neutral-950 dark:text-white">&#8369;{{ number_format($line->total_line_amount, 2) }}</span></x-ui.table.td>
                        </x-ui.table.row>
                    @empty
                        <x-ui.table.empty colspan="5" artwork="procurement" title="No order lines found" message="This purchase order has no items." />
                    @endforelse
                </x-ui.table>
            </div>
        </section>

        @can('supplier_fulfill_orders')
            @if (in_array($order->status, ['approved', 'dispatched']))
                <x-ui.card title="Respond to order">
                    <form method="POST" action="{{ route('supplier.orders.acknowledge', $order) }}" class="grid gap-4 lg:grid-cols-2">
                        @csrf
                        <x-ui.field name="response" label="Response" type="select" :options="['accepted' => 'Accept order', 'rejected' => 'Reject order', 'change_requested' => 'Request modification']" required />
                        <x-ui.field name="exception_type" label="Fulfillment issue" type="select" :options="['' => 'None', 'insufficient_stock' => 'Insufficient stock', 'partial_availability' => 'Partial availability', 'backorder' => 'Backorder required', 'delivery_date' => 'Cannot meet delivery date', 'discontinued' => 'Product discontinued', 'other' => 'Other']" />
                        <div class="lg:col-span-2"><x-ui.field name="message" label="Explanation" type="textarea" /></div>
                        <div class="lg:col-span-2"><x-ui.button type="submit">Submit response</x-ui.button></div>
                    </form>
                </x-ui.card>
            @endif

            @if (in_array($order->status, ['acknowledged', 'partially_fulfilled']))
                <x-ui.card title="Create Advance Ship Notice">
                    <form method="POST" action="{{ route('supplier.orders.asns.store', $order) }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @csrf
                        <x-ui.field name="shipment_number" label="ASN number" required />
                        <x-ui.field name="carrier_name" label="Carrier" required />
                        <x-ui.field name="tracking_number" label="Tracking number" />
                        <x-ui.field name="sscc" label="SSCC (18 digits)" />
                        <div x-data="{ dispatchDate: @js(old('dispatch_date', today()->toDateString())) }" class="contents">
                            <x-ui.field name="dispatch_date" label="Shipment date" type="date" x-model="dispatchDate" :max="today()->toDateString()" required />
                            <x-ui.field name="estimated_delivery_date" label="Expected arrival" type="date" x-bind:min="dispatchDate || @js(today()->toDateString())" required />
                        </div>

                        @foreach ($order->lines as $i => $line)
                            <input type="hidden" name="lines[{{ $i }}][po_line_id]" value="{{ $line->id }}">
                            <x-ui.field name="lines[{{ $i }}][quantity]" :label="'Quantity: '.$line->item->name" type="number" min="1" :max="$line->ordered_quantity" required />
                        @endforeach

                        <div class="md:col-span-2 xl:col-span-3"><x-ui.button type="submit">Submit ASN</x-ui.button></div>
                    </form>
                </x-ui.card>
            @endif
        @endcan
    </div>
</x-layouts.supplier>
