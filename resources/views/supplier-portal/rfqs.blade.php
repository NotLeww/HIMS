<x-layouts.supplier title="RFQs">
    @php
        $paymentTermOptions = [
            'Net 15' => 'Net 15',
            'Net 30' => 'Net 30',
            'Net 45' => 'Net 45',
            'Net 60' => 'Net 60',
            'Cash on delivery' => 'Cash on delivery',
            'Advance payment' => 'Advance payment',
            'other' => 'Other / Custom terms',
        ];
        $oldPaymentTerms = old('payment_terms');
        $paymentTermsSelection = array_key_exists((string) $oldPaymentTerms, $paymentTermOptions)
            ? $oldPaymentTerms
            : (filled($oldPaymentTerms) ? 'other' : '');
        $customPaymentTerms = old(
            'payment_terms_custom',
            $paymentTermsSelection === 'other' && $oldPaymentTerms !== 'other' ? $oldPaymentTerms : '',
        );
    @endphp

    <style>
        [data-rfq-header] .hims-page-header {
            --hims-header-image: url('{{ asset('img/hims-supplier-rfq-hero-day.png') }}');
        }

        .dark [data-rfq-header] .hims-page-header {
            --hims-header-image: url('{{ asset('img/hims-supplier-rfq-hero-night.png') }}');
        }
    </style>

    <div data-rfq-header>
        <x-ui.page-header title="RFQs & Bids" subtitle="Only invitations issued to your supplier are shown." />
    </div>

    <div class="mt-6 space-y-5">
        @forelse ($invitations as $invite)
            <x-ui.card :padding="false" data-rfq-bid-card>
                <x-slot:header>
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/70 dark:bg-emerald-950/70 dark:text-emerald-300 dark:ring-emerald-800/60">
                            <x-ui.icon name="document-text" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="break-words text-sm font-semibold text-neutral-950 dark:text-white">{{ $invite->rfq->rfq_number }} &middot; {{ $invite->rfq->title }}</h2>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Deadline: {{ $invite->rfq->submission_deadline->format('M j, Y g:i A') }}</p>
                        </div>
                    </div>
                </x-slot:header>

                <div class="p-4 sm:p-5">
                    <p class="text-sm leading-6 text-neutral-600 dark:text-neutral-300">{{ $invite->rfq->description }}</p>

                    @can('supplier_submit_bids')
                        @if (! $invite->rfq->isBiddingClosed())
                            <form
                                method="POST"
                                action="{{ route('supplier.rfqs.bid', $invite) }}"
                                class="mt-5 space-y-5"
                                data-rfq-bid-form
                                novalidate
                                x-data="{ canSubmit: false }"
                                x-init="$nextTick(() => canSubmit = $el.checkValidity())"
                                x-on:input="$nextTick(() => canSubmit = $el.checkValidity())"
                                x-on:change="$nextTick(() => canSubmit = $el.checkValidity())"
                            >
                                @csrf
                                <div class="grid items-start gap-4 lg:grid-cols-2 xl:grid-cols-[minmax(14rem,0.8fr)_minmax(16rem,1fr)_minmax(22rem,1.4fr)]">
                                    <x-ui.field name="quote_number" label="Quote number" placeholder="Enter your quote number" required />
                                    <div class="space-y-3" x-data="{ paymentTerms: @js($paymentTermsSelection) }">
                                        <x-ui.field
                                            name="payment_terms"
                                            label="Payment terms"
                                            type="select"
                                            placeholder="Select payment terms"
                                            :options="$paymentTermOptions"
                                            :value="$paymentTermsSelection"
                                            x-model="paymentTerms"
                                        />
                                        <div x-show="paymentTerms === 'other'" x-cloak>
                                            <x-ui.field
                                                name="payment_terms_custom"
                                                label="Custom payment terms"
                                                :value="$customPaymentTerms"
                                                maxlength="100"
                                                placeholder="Enter the agreed payment terms"
                                                x-bind:required="paymentTerms === 'other'"
                                            />
                                        </div>
                                    </div>

                                    <div class="lg:col-span-2 xl:col-span-1" x-data="{ count: {{ strlen((string) old('notes')) }} }">
                                        <x-ui.field name="notes" label="Bid notes" type="textarea" rows="3" maxlength="2000" placeholder="Enter additional notes, specifications, or remarks..." x-on:input="count = $event.target.value.length" />
                                        <p class="mt-1 text-right text-xs tabular-nums text-neutral-500 dark:text-neutral-400"><span x-text="count">{{ strlen((string) old('notes')) }}</span>/2000</p>
                                    </div>
                                </div>

                                @foreach ($invite->rfq->lines as $i => $line)
                                    <fieldset class="grid gap-4 rounded-lg border border-neutral-200 border-l-emerald-500 bg-neutral-50/60 p-4 md:grid-cols-3 dark:border-neutral-700 dark:border-l-emerald-400 dark:bg-neutral-950/30">
                                        <legend class="sr-only">Bid details for {{ $line->item_description ?: $line->item->name }}</legend>
                                        <p class="font-semibold text-neutral-950 md:col-span-3 dark:text-white">
                                            {{ $line->item_description ?: $line->item->name }} &mdash; {{ number_format($line->target_quantity) }} {{ $line->uom }}
                                        </p>
                                        <input type="hidden" name="lines[{{ $i }}][rfq_line_item_id]" value="{{ $line->id }}">

                                        <div class="min-w-0 space-y-1.5">
                                            <label for="line-{{ $i }}-unit-price" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                                Unit price <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span><span class="sr-only">(required)</span>
                                            </label>
                                            <div class="relative">
                                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-neutral-500 dark:text-neutral-400">&#8369;</span>
                                                <input
                                                    id="line-{{ $i }}-unit-price"
                                                    name="lines[{{ $i }}][offered_unit_price]"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value="{{ old("lines.$i.offered_unit_price") }}"
                                                    placeholder="0.00"
                                                    class="block min-h-10 w-full rounded-md border border-neutral-300 bg-white py-2 pl-8 pr-3 text-sm text-neutral-900 shadow-sm transition-colors placeholder:text-neutral-400 focus:border-primary-500 focus:ring-2 focus:ring-primary-500/30 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:placeholder:text-neutral-500"
                                                    required
                                                >
                                            </div>
                                            @error("lines.$i.offered_unit_price")<p class="text-xs font-medium text-danger-600 dark:text-danger-400">{{ $message }}</p>@enderror
                                        </div>

                                        <x-ui.field name="lines[{{ $i }}][offered_quantity]" label="Quantity" type="number" min="1" :value="old('lines.'.$i.'.offered_quantity', $line->target_quantity)" required />
                                        <x-ui.field name="lines[{{ $i }}][lead_time_days]" label="Lead time (days)" type="number" min="0" max="3650" placeholder="Enter lead time" required />
                                    </fieldset>
                                @endforeach

                                <x-ui.button type="submit" icon="paper-airplane" data-loading-text="Submitting sealed bid..." disabled x-bind:disabled="!canSubmit">Submit sealed bid</x-ui.button>
                            </form>
                        @else
                            <p class="mt-4 text-sm font-semibold text-neutral-600 dark:text-neutral-300">Bidding is closed.</p>
                        @endif
                    @endcan
                </div>
            </x-ui.card>
        @empty
            <x-ui.card><p>No RFQ invitations.</p></x-ui.card>
        @endforelse

        <div>{{ $invitations->onEachSide(1)->links() }}</div>
    </div>
</x-layouts.supplier>
