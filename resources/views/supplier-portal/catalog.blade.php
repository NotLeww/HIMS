<x-layouts.supplier title="Catalog">
    <x-ui.page-header title="Supplier Catalog" subtitle="Hospital-approved items and VMI visibility for your organization." />
    @can('supplier_manage_profile')
        <x-ui.card class="mt-6">
            <x-slot:header>
                <h2 class="text-xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">Submit catalog product</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Submissions require hospital review and never edit the hospital item master.</p>
            </x-slot:header>

            <form
                method="POST"
                action="{{ route('supplier.catalog.store') }}"
                class="grid gap-x-6 gap-y-6 md:grid-cols-2 xl:grid-cols-3"
                x-data="{ isValid: false }"
                x-init="$nextTick(() => isValid = $el.checkValidity())"
                x-on:input="isValid = $el.checkValidity()"
                x-on:change="isValid = $el.checkValidity()"
            >
                @csrf
                <x-ui.field name="item_id" label="Hospital item" type="select" icon="cube" :options="$items" required />
                <x-ui.field name="supplier_product_name" label="Product name" icon="tag" placeholder="Enter product name" required />
                <x-ui.field name="supplier_sku" label="Supplier product code" icon="qr-code" placeholder="Enter supplier product code" required />
                <x-ui.field name="gtin" label="GTIN" icon="qr-code" placeholder="Enter GTIN" />
                <x-ui.field name="unit" label="Unit" icon="cube" placeholder="e.g. box, piece, pack" required />
                <x-ui.field name="pack_size" label="Packaging" icon="archive-box" placeholder="Enter packaging details" />
                <x-ui.field name="minimum_order_quantity" label="Minimum order quantity" icon="squares-2x2" type="number" min="1" placeholder="Enter minimum quantity" required />
                <x-ui.field name="lead_time_days" label="Lead time (days)" icon="calendar" type="number" min="0" placeholder="Enter lead time in days" required />

                <div class="flex flex-col-reverse gap-3 border-t border-neutral-200 pt-6 md:col-span-2 sm:flex-row sm:justify-end xl:col-span-3 dark:border-neutral-800">
                    <x-ui.button
                        type="reset"
                        variant="secondary"
                        size="lg"
                        class="w-full px-8 sm:w-auto"
                        x-on:click="$nextTick(() => isValid = $el.form.checkValidity())"
                    >Cancel</x-ui.button>
                    <x-ui.button
                        type="submit"
                        icon="paper-airplane"
                        size="lg"
                        class="w-full px-8 sm:w-auto"
                        disabled
                        x-bind:disabled="! isValid"
                        data-loading-text="Submitting..."
                    >Submit for review</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @endcan
    <div class="mt-6"><x-ui.table>
        <x-slot:head><x-ui.table.th>Product</x-ui.table.th><x-ui.table.th>Supplier code / GTIN</x-ui.table.th><x-ui.table.th>Approval</x-ui.table.th><x-ui.table.th>VMI stock</x-ui.table.th></x-slot:head>
        @forelse($products as $product)<x-ui.table.row><x-ui.table.td>{{ $product->supplier_product_name }}<span class="block text-xs text-neutral-500">{{ $product->pack_size }} {{ $product->unit }}</span></x-ui.table.td><x-ui.table.td>{{ $product->supplier_sku }}<span class="block text-xs text-neutral-500">{{ $product->gtin ?: 'No GTIN' }}</span></x-ui.table.td><x-ui.table.td><x-ui.badge :status="$product->approval_status" /></x-ui.table.td><x-ui.table.td>@if($product->vmi_enabled){{ $product->item->quantity_on_hand }} / {{ $product->vmi_min }}–{{ $product->vmi_max }}<span class="block text-xs text-neutral-500">Forecast only; not a purchase order</span>@else Not enabled @endif</x-ui.table.td></x-ui.table.row>@empty<x-ui.table.empty colspan="4">No catalog products.</x-ui.table.empty>@endforelse
    </x-ui.table><div class="mt-4">{{ $products->onEachSide(1)->links() }}</div></div>
</x-layouts.supplier>
