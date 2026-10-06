<x-layouts.supplier title="Catalog">
    <x-ui.page-header title="Supplier Catalog" subtitle="Hospital-approved items and VMI visibility for your organization." />
    @can('supplier_manage_profile')
        <x-ui.card title="Submit catalog product" subtitle="Submissions require hospital review and never edit the hospital item master." class="mt-6">
            <form method="POST" action="{{ route('supplier.catalog.store') }}" class="grid gap-4 md:grid-cols-3">
                @csrf
                <x-ui.field name="item_id" label="Hospital item" type="select" :options="$items" required />
                <x-ui.field name="supplier_product_name" label="Product name" required />
                <x-ui.field name="supplier_sku" label="Supplier product code" required />
                <x-ui.field name="gtin" label="GTIN" />
                <x-ui.field name="unit" label="Unit" required />
                <x-ui.field name="pack_size" label="Packaging" />
                <x-ui.field name="minimum_order_quantity" label="Minimum order quantity" type="number" min="1" required />
                <x-ui.field name="lead_time_days" label="Lead time (days)" type="number" min="0" required />
                <div class="flex items-end"><x-ui.button type="submit">Submit for review</x-ui.button></div>
            </form>
        </x-ui.card>
    @endcan
    <div class="mt-6"><x-ui.table>
        <x-slot:head><x-ui.table.th>Product</x-ui.table.th><x-ui.table.th>Supplier code / GTIN</x-ui.table.th><x-ui.table.th>Approval</x-ui.table.th><x-ui.table.th>VMI stock</x-ui.table.th></x-slot:head>
        @forelse($products as $product)<x-ui.table.row><x-ui.table.td>{{ $product->supplier_product_name }}<span class="block text-xs text-neutral-500">{{ $product->pack_size }} {{ $product->unit }}</span></x-ui.table.td><x-ui.table.td>{{ $product->supplier_sku }}<span class="block text-xs text-neutral-500">{{ $product->gtin ?: 'No GTIN' }}</span></x-ui.table.td><x-ui.table.td><x-ui.badge :status="$product->approval_status" /></x-ui.table.td><x-ui.table.td>@if($product->vmi_enabled){{ $product->item->quantity_on_hand }} / {{ $product->vmi_min }}–{{ $product->vmi_max }}<span class="block text-xs text-neutral-500">Forecast only; not a purchase order</span>@else Not enabled @endif</x-ui.table.td></x-ui.table.row>@empty<x-ui.table.empty colspan="4">No catalog products.</x-ui.table.empty>@endforelse
    </x-ui.table><div class="mt-4">{{ $products->onEachSide(1)->links() }}</div></div>
</x-layouts.supplier>
