<x-layouts.supplier title="Invoices">
    <style>
        [data-invoices-header] .hims-page-header {
            --hims-header-image: url('{{ asset('img/hims-supplier-invoices-hero-day.png') }}');
        }

        .dark [data-invoices-header] .hims-page-header {
            --hims-header-image: url('{{ asset('img/hims-supplier-invoices-hero-night.png') }}');
        }
    </style>

    <div data-invoices-header>
        <x-ui.page-header
            title="Invoices"
            subtitle="Submit invoices against accepted receipts; matching does not represent payment."
        />
    </div>

    @can('supplier_manage_invoices')
        <x-ui.card title="Submit invoice" class="mt-6">
            <form method="POST" action="{{ route('supplier.invoices.store') }}" class="grid gap-4 md:grid-cols-3">
                @csrf
                <x-ui.field
                    name="purchase_order_id"
                    label="Purchase order"
                    type="select"
                    :options="$orders->pluck('po_number', 'id')->all()"
                    required
                />
                <x-ui.field name="invoice_number" label="Invoice number" required />
                <x-ui.field name="invoice_date" label="Invoice date" type="date" required />

                <div class="md:col-span-3">
                    <p class="text-sm text-neutral-600">Enter invoice lines for the selected PO. Line IDs are shown on the PO page.</p>
                </div>

                <x-ui.field name="lines[0][po_line_id]" label="PO line ID" type="number" required />
                <x-ui.field name="lines[0][quantity]" label="Quantity" type="number" min="1" required />
                <x-ui.field name="lines[0][unit_price]" label="Unit price" type="number" min="0" step="0.01" required />

                <div>
                    <x-ui.button type="submit">Submit invoice</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan

    <div class="mt-6">
        <x-ui.table>
            <x-slot:head>
                <x-ui.table.th>Invoice</x-ui.table.th>
                <x-ui.table.th>PO</x-ui.table.th>
                <x-ui.table.th>Total</x-ui.table.th>
                <x-ui.table.th>Match status</x-ui.table.th>
            </x-slot:head>

            @forelse($invoices as $invoice)
                <x-ui.table.row>
                    <x-ui.table.td>{{ $invoice->invoice_number }}</x-ui.table.td>
                    <x-ui.table.td>{{ $invoice->purchaseOrder->po_number }}</x-ui.table.td>
                    <x-ui.table.td>₱{{ number_format($invoice->total_amount, 2) }}</x-ui.table.td>
                    <x-ui.table.td><x-ui.badge :status="$invoice->status" /></x-ui.table.td>
                </x-ui.table.row>
            @empty
                <x-ui.table.empty colspan="4">No invoices submitted.</x-ui.table.empty>
            @endforelse
        </x-ui.table>

        <div class="mt-4">{{ $invoices->onEachSide(1)->links() }}</div>
    </div>
</x-layouts.supplier>
