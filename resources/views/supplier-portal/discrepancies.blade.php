<x-layouts.supplier title="Discrepancies">
    <div data-discrepancies-header>
        <style>
            [data-discrepancies-header] .hims-page-header {
                --hims-header-image: url('{{ asset('img/hims-supplier-discrepancies-hero-day.png') }}');
            }

            .dark [data-discrepancies-header] .hims-page-header {
                --hims-header-image: url('{{ asset('img/hims-supplier-discrepancies-hero-night.png') }}');
            }
        </style>

        <x-ui.page-header title="Receiving Discrepancies" subtitle="Respond to documented delivery variances; hospital staff close the resolution." />
    </div>

    <div class="mt-6 space-y-4">
        @forelse($discrepancies as $d)
            <x-ui.card :title="'Discrepancy #'.$d->id" :subtitle="$d->receiptLine->goodsReceiptNote->purchaseOrder->po_number.' · '.$d->receiptLine->item->name">
                <p class="text-sm">{{ str($d->receiptLine->discrepancy_type)->headline() }}: {{ $d->receiptLine->discrepancy_notes ?: 'No additional note.' }}</p>
                @if($d->status === 'open')
                    <form method="POST" action="{{ route('supplier.discrepancies.respond', $d) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf
                        <x-ui.field name="supplier_response_type" label="Response" type="select" :options="['replacement_scheduled' => 'Replacement scheduled', 'supplemental_delivery' => 'Supplemental delivery', 'credit_requested' => 'Credit requested', 'dispute' => 'Dispute', 'explanation' => 'Explanation', 'other' => 'Other']" required />
                        <x-ui.field name="supplier_response" label="Details" type="textarea" required />
                        <div><x-ui.button type="submit">Submit response</x-ui.button></div>
                    </form>
                @else
                    <p class="mt-3 text-sm font-medium">{{ str($d->status)->headline() }} &mdash; {{ $d->supplier_response }}</p>
                @endif
            </x-ui.card>
        @empty
            <x-ui.empty-state
                artwork="receiving-discrepancies"
                title="No receiving discrepancies"
                message="Delivery variances requiring your response will appear here."
            />
        @endforelse

        <div>{{ $discrepancies->onEachSide(1)->links() }}</div>
    </div>
</x-layouts.supplier>
