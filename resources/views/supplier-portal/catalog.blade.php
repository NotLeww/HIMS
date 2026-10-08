<x-layouts.supplier title="Catalog">
    <div data-catalog-header>
        <style>
            [data-catalog-header] .hims-page-header {
                --hims-header-image: url('{{ asset('img/hims-supplier-catalog-hero-day.png') }}');
            }

            .dark [data-catalog-header] .hims-page-header {
                --hims-header-image: url('{{ asset('img/hims-supplier-catalog-hero-night.png') }}');
            }
        </style>

        <x-ui.page-header title="Supplier Catalog" subtitle="Hospital-approved items and VMI visibility for your organization.">
            @can('supplier_manage_profile')
                <x-slot:actions>
                    <x-ui.button
                        type="button"
                        icon="plus"
                        x-data
                        x-on:click="$dispatch('open-modal', 'submit-catalog-product')"
                        x-init="if ({{ $errors->any() ? 'true' : 'false' }}) $nextTick(() => $dispatch('open-modal', 'submit-catalog-product'))"
                    >
                        Submit product
                    </x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>
    </div>

    @can('supplier_manage_profile')
        <x-ui.modal name="submit-catalog-product" title="Submit catalog product" maxWidth="5xl">
            <x-slot:header>
                <div class="min-w-0">
                    <h2 id="submit-catalog-product-title" class="text-lg font-semibold tracking-tight text-neutral-900 dark:text-neutral-100">Submit catalog product</h2>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Submissions require hospital review and never edit the hospital item master.</p>
                </div>
            </x-slot:header>

            <form
                method="POST"
                action="{{ route('supplier.catalog.store') }}"
                class="grid gap-x-6 gap-y-5 md:grid-cols-2"
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

                <div class="flex flex-col-reverse gap-3 border-t border-neutral-200 pt-5 md:col-span-2 sm:flex-row sm:justify-end dark:border-neutral-800">
                    <x-ui.button
                        type="reset"
                        variant="secondary"
                        size="lg"
                        class="w-full px-8 sm:w-auto"
                        x-on:click="$dispatch('close-modal', 'submit-catalog-product'); $nextTick(() => isValid = $el.form.checkValidity())"
                    >
                        Cancel
                    </x-ui.button>
                    <x-ui.button type="submit" icon="paper-airplane" size="lg" class="w-full px-8 sm:w-auto" disabled x-bind:disabled="! isValid" data-loading-text="Submitting...">Submit for review</x-ui.button>
                </div>
            </form>
        </x-ui.modal>
    @endcan

    <div class="mt-6">
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <x-ui.table :sticky-header="false">
                <x-slot:head>
                    <x-ui.table.th class="w-[44%] px-6 py-4 text-[0.75rem] font-bold tracking-[0.08em]">Product</x-ui.table.th>
                    <x-ui.table.th class="w-[26%] px-6 py-4 text-[0.75rem] font-bold tracking-[0.08em]">Supplier code / GTIN</x-ui.table.th>
                    <x-ui.table.th class="w-[16%] px-6 py-4 text-[0.75rem] font-bold tracking-[0.08em]">Approval</x-ui.table.th>
                    <x-ui.table.th class="w-[14%] px-6 py-4 text-[0.75rem] font-bold tracking-[0.08em]">VMI stock</x-ui.table.th>
                </x-slot:head>

                @forelse($products as $product)
                    <x-ui.table.row class="odd:!bg-white even:!bg-neutral-50/40 dark:odd:!bg-neutral-900 dark:even:!bg-neutral-900/70">
                        <x-ui.table.td class="px-6 py-5">
                            <span class="block text-base font-semibold leading-snug text-neutral-900 dark:text-neutral-100">{{ $product->supplier_product_name }}</span>
                            <span class="mt-1 block text-sm text-neutral-500 dark:text-neutral-400">{{ $product->pack_size }} {{ $product->unit }}</span>
                        </x-ui.table.td>
                        <x-ui.table.td class="px-6 py-5">
                            <span class="block text-base font-semibold leading-snug text-neutral-900 dark:text-neutral-100">{{ $product->supplier_sku }}</span>
                            <span class="mt-1 block text-sm text-neutral-500 dark:text-neutral-400">{{ $product->gtin ?: 'No GTIN' }}</span>
                        </x-ui.table.td>
                        <x-ui.table.td class="px-6 py-5">
                            <x-ui.badge :status="$product->approval_status" class="px-3 py-1 text-sm font-semibold" />
                        </x-ui.table.td>
                        <x-ui.table.td class="px-6 py-5 text-base font-medium text-neutral-700 dark:text-neutral-300">
                            @if($product->vmi_enabled)
                                {{ $product->item->quantity_on_hand }} / {{ $product->vmi_min }}&ndash;{{ $product->vmi_max }}
                                <span class="mt-1 block text-sm font-normal text-neutral-500 dark:text-neutral-400">Forecast only; not a purchase order</span>
                            @else
                                Not enabled
                            @endif
                        </x-ui.table.td>
                    </x-ui.table.row>
                @empty
                    <x-ui.table.empty colspan="4">No catalog products.</x-ui.table.empty>
                @endforelse
            </x-ui.table>
        </div>

        <div class="mt-4">{{ $products->onEachSide(1)->links() }}</div>
    </div>
</x-layouts.supplier>
