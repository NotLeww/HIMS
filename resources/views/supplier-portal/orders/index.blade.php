<x-layouts.supplier title="Purchase Orders">
    <x-ui.page-header
        title="Purchase Orders"
        subtitle="Hospital-issued orders for your supplier account."
        :breadcrumbs="['Dashboard' => route('supplier.dashboard'), 'Purchase Orders' => null]"
    />

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
                        <x-ui.button href="{{ route('supplier.orders.show', $order) }}" variant="secondary" size="sm">Open</x-ui.button>
                    </x-ui.table.td>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="5" title="No purchase orders found" message="Hospital-issued orders will appear here." />
            @endforelse
        </x-ui.table>

        <div class="mt-4">{{ $orders->onEachSide(1)->links() }}</div>
    </div>
</x-layouts.supplier>
