<x-layouts.supplier title="Supplier Dashboard">
    <x-ui.page-header title="Supplier Dashboard" subtitle="Orders, deliveries, compliance, invoices, and performance for your organization." />
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat label="Orders requiring action" :value="$ordersRequiringAction" icon="clipboard-document-check" tone="warning" />
        <x-ui.stat label="Upcoming deliveries" :value="$upcomingDeliveries" icon="truck" tone="primary" />
        <x-ui.stat label="Open discrepancies" :value="$openDiscrepancies" icon="exclamation-triangle" tone="danger" />
        <x-ui.stat label="Available RFQs" :value="$availableRfqs" icon="document-text" tone="success" />
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
        <x-ui.card title="Recent purchase orders" subtitle="Hospital-issued orders for your supplier account.">
            <x-ui.table><x-slot:head><x-ui.table.th>PO</x-ui.table.th><x-ui.table.th>Status</x-ui.table.th><x-ui.table.th>Amount</x-ui.table.th><x-ui.table.th></x-ui.table.th></x-slot:head>
                @forelse($recentOrders as $order)<x-ui.table.row><x-ui.table.td>{{ $order->po_number }}</x-ui.table.td><x-ui.table.td><x-ui.badge :status="$order->status" /></x-ui.table.td><x-ui.table.td>₱{{ number_format($order->total_amount,2) }}</x-ui.table.td><x-ui.table.td><a class="font-semibold text-primary-700" href="{{ route('supplier.orders.show',$order) }}">View</a></x-ui.table.td></x-ui.table.row>@empty<x-ui.table.empty colspan="4">No purchase orders yet.</x-ui.table.empty>@endforelse
            </x-ui.table>
        </x-ui.card>
        <x-ui.card title="Account status"><dl class="space-y-3 text-sm"><div><dt class="text-neutral-500">Compliance</dt><dd class="font-semibold">{{ str($supplier->complianceState())->headline() }}</dd></div><div><dt class="text-neutral-500">Invoice queue</dt><dd class="font-semibold">{{ $invoices->sum() }} submitted records</dd></div><div><dt class="text-neutral-500">Latest score</dt><dd class="font-semibold">{{ $scorecard ? number_format($scorecard->total_score,1).'%' : 'Not yet scored' }}</dd></div></dl></x-ui.card>
    </div>
</x-layouts.supplier>
