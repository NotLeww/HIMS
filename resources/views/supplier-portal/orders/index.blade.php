<x-layouts.supplier title="Purchase Orders">
    <x-ui.page-header
        title="Purchase Orders"
        subtitle="Hospital-issued orders for your supplier account."
        :breadcrumbs="['Dashboard' => route('supplier.dashboard'), 'Purchase Orders' => null]"
        class="supplier-orders-header"
    >
        <x-slot:media>
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-100/90 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/70">
                <x-ui.icon name="clipboard-document-list" class="h-9 w-9" />
            </span>
        </x-slot:media>
    </x-ui.page-header>

    <div class="mt-8">
        <x-ui.table :sticky-header="false" class="supplier-orders-table">
            <x-slot:head>
                <x-ui.table.th>PO number</x-ui.table.th>
                <x-ui.table.th class="hidden md:table-cell">Scheduled delivery</x-ui.table.th>
                <x-ui.table.th>Status</x-ui.table.th>
                <x-ui.table.th class="hidden sm:table-cell">Total</x-ui.table.th>
                <x-ui.table.th align="right">Actions</x-ui.table.th>
            </x-slot:head>

            @forelse ($orders as $order)
                <x-ui.table.row class="odd:!bg-white even:!bg-primary-50/20 dark:odd:!bg-slate-900/80 dark:even:!bg-slate-800/45">
                    <x-ui.table.td><span class="font-medium text-slate-950 dark:text-white">{{ $order->po_number }}</span></x-ui.table.td>
                    <x-ui.table.td class="hidden md:table-cell">{{ $order->delivery_date?->format('M d, Y') ?? 'Not set' }}</x-ui.table.td>
                    <x-ui.table.td><x-ui.badge :status="$order->status" dot class="px-3 py-1 text-sm" /></x-ui.table.td>
                    <x-ui.table.td class="hidden font-medium tabular-nums sm:table-cell">₱{{ number_format($order->total_amount, 2) }}</x-ui.table.td>
                    <x-ui.table.td align="right">
                        <a href="{{ route('supplier.orders.show', $order) }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary-50 px-4 py-2 font-semibold text-primary-700 ring-1 ring-inset ring-primary-100 transition-colors hover:bg-primary-100 focus-visible:ring-primary-500 dark:bg-primary-950/70 dark:text-primary-200 dark:ring-primary-800 dark:hover:bg-primary-900/70">
                            <x-ui.icon name="eye" class="h-5 w-5" />
                            <span>Open</span>
                        </a>
                    </x-ui.table.td>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="5" title="No purchase orders found" message="Hospital-issued orders will appear here." />
            @endforelse
        </x-ui.table>

        <div class="mt-4">{{ $orders->onEachSide(1)->links() }}</div>
    </div>
</x-layouts.supplier>
