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

    <div class="mt-6 w-full space-y-5">
        <div>
            <h2 class="text-base font-semibold text-neutral-950 dark:text-white">RFQ invitations</h2>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                {{ number_format($invitations->total()) }} {{ str('invitation')->plural($invitations->total()) }} issued to your supplier.
            </p>
        </div>

        <div class="grid items-start gap-5 xl:grid-cols-2">
            @forelse ($invitations as $invite)
            @php($rfqArtwork = \App\Support\ItemFamilyArtwork::filename($invite->rfq->lines->pluck('item')))
            <x-ui.card :padding="false" class="min-w-0 border-l-2 border-l-primary-500 shadow-sm transition-shadow hover:shadow-md dark:border-l-primary-400" data-rfq-bid-card>
                <x-slot:header>
                    <div class="flex min-w-0 items-start gap-3.5">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 ring-1 ring-primary-200/80 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-800/70">
                            <x-ui.icon name="document-text" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="break-words text-sm font-semibold leading-5 text-neutral-950 dark:text-white">
                                {{ $invite->rfq->rfq_number }} <span class="text-neutral-400 dark:text-neutral-600" aria-hidden="true">&middot;</span> {{ $invite->rfq->title }}
                            </h3>
                            <p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-neutral-500 dark:text-neutral-400">
                                <x-ui.icon name="calendar" class="h-4 w-4 shrink-0" />
                                <span>Deadline:</span>
                                <time datetime="{{ $invite->rfq->submission_deadline->toIso8601String() }}">
                                    {{ $invite->rfq->submission_deadline->format('M j, Y g:i A') }}
                                </time>
                            </p>
                        </div>
                    </div>
                </x-slot:header>
                @can('supplier_submit_bids')
                    @if (! $invite->rfq->isBiddingClosed())
                        <x-slot:actions>
                            <x-ui.button
                                type="button"
                                variant="primary"
                                x-data
                                x-on:click="$dispatch('open-modal', 'rfq-bid-{{ $invite->id }}')"
                            >
                                Prepare bid
                                <x-ui.icon name="arrow-right" class="h-4 w-4" />
                            </x-ui.button>
                        </x-slot:actions>
                    @endif
                @endcan

                @if ($invite->rfq->lines->count() > 1)
                    <x-slot:footer>
                        <button
                            type="button"
                            class="inline-flex min-h-9 items-center gap-2 rounded-md text-xs font-semibold text-primary-700 transition-colors hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:text-primary-300 dark:hover:text-primary-200 dark:focus-visible:ring-offset-neutral-900"
                            x-data
                            x-on:click="$dispatch('open-modal', 'rfq-items-{{ $invite->id }}')"
                        >
                            <x-ui.icon name="clipboard-document-list" class="h-4 w-4" />
                            <span>View {{ $invite->rfq->lines->count() }} line items</span>
                            <x-ui.icon name="chevron-right" class="h-4 w-4" />
                        </button>
                    </x-slot:footer>
                @endif

                <div class="relative isolate min-h-24 overflow-hidden bg-gradient-to-r from-white via-white to-primary-50/60 p-4 sm:min-h-28 sm:p-5 dark:from-neutral-900 dark:via-neutral-900 dark:to-primary-950/25">
                    <img src="{{ asset('img/requisition/'.$rfqArtwork) }}" alt="" aria-hidden="true" loading="lazy" decoding="async" class="pointer-events-none absolute -right-3 top-1/2 hidden h-[145%] w-auto max-w-[36%] -translate-y-1/2 object-contain object-right opacity-40 mix-blend-multiply dark:opacity-20 dark:mix-blend-screen sm:block">
                    <p class="relative text-sm leading-6 text-neutral-600 sm:max-w-[68%] dark:text-neutral-300">{{ $invite->rfq->description }}</p>

                    @if ($invite->rfq->lines->count() > 1)
                        <x-ui.modal
                            name="rfq-items-{{ $invite->id }}"
                            title="Line items — {{ $invite->rfq->rfq_number }}"
                            maxWidth="2xl"
                        >
                            <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
                                @foreach ($invite->rfq->lines as $line)
                                    <li class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                        <span class="min-w-0 break-words text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                            {{ $line->item_description ?: $line->item->name }}
                                        </span>
                                        <span class="shrink-0 text-xs font-medium tabular-nums text-neutral-500 dark:text-neutral-400">
                                            {{ number_format($line->target_quantity) }} {{ $line->uom }} requested
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </x-ui.modal>
                    @endif

                    @can('supplier_submit_bids')
                        @if (! $invite->rfq->isBiddingClosed())
                            @if ($errors->any() && (int) old('_invitation_id') === $invite->id)
                                <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'rfq-bid-{{ $invite->id }}'))"></div>
                            @endif

                            <x-ui.modal
                                name="rfq-bid-{{ $invite->id }}"
                                title="Prepare bid — {{ $invite->rfq->rfq_number }}"
                                maxWidth="6xl"
                                flush
                            >
                                <x-slot:header>
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 ring-1 ring-primary-200/80 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-800/70">
                                            <x-ui.icon name="document-text" class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <h2 id="rfq-bid-{{ $invite->id }}-title" class="break-words text-base font-semibold text-neutral-950 dark:text-white">
                                                Prepare bid — {{ $invite->rfq->rfq_number }}
                                            </h2>
                                            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Submit your commercial offer for this request for quotation.</p>
                                        </div>
                                    </div>
                                </x-slot:header>

                                <form
                                    method="POST"
                                    action="{{ route('supplier.rfqs.bid', $invite) }}"
                                    class="flex min-h-0 flex-col"
                                    data-rfq-bid-form
                                    novalidate
                                    x-data="{ canSubmit: false }"
                                    x-init="$nextTick(() => canSubmit = $el.checkValidity())"
                                    x-on:input="$nextTick(() => canSubmit = $el.checkValidity())"
                                    x-on:change="$nextTick(() => canSubmit = $el.checkValidity())"
                                >
                                    @csrf
                                    <input type="hidden" name="_invitation_id" value="{{ $invite->id }}">
                                    <div class="space-y-5 p-4 sm:p-5">
                                        <div class="grid items-start gap-4 lg:grid-cols-2 xl:grid-cols-[minmax(14rem,0.8fr)_minmax(16rem,1fr)_minmax(22rem,1.4fr)]">
                                            <x-ui.field id="quote-number-{{ $invite->id }}" name="quote_number" label="Quote number" placeholder="Enter your quote number" required />
                                            <div class="space-y-3" x-data="{ paymentTerms: @js($paymentTermsSelection) }">
                                                <x-ui.field
                                                    id="payment-terms-{{ $invite->id }}"
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
                                                        id="payment-terms-custom-{{ $invite->id }}"
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
                                                <x-ui.field id="bid-notes-{{ $invite->id }}" name="notes" label="Bid notes" type="textarea" rows="3" maxlength="2000" placeholder="Enter additional notes, specifications, or remarks..." x-on:input="count = $event.target.value.length" />
                                                <p class="mt-1 text-right text-xs tabular-nums text-neutral-500 dark:text-neutral-400"><span x-text="count">{{ strlen((string) old('notes')) }}</span>/2000</p>
                                            </div>
                                        </div>

                                        <div class="space-y-4">
                                            @foreach ($invite->rfq->lines as $i => $line)
                                                <fieldset class="grid gap-4 rounded-lg border border-neutral-200 border-l-2 border-l-primary-500 bg-neutral-50/70 p-4 md:grid-cols-3 dark:border-neutral-700 dark:border-l-primary-400 dark:bg-neutral-950/30">
                                                    <legend class="sr-only">Bid details for {{ $line->item_description ?: $line->item->name }}</legend>
                                                    <div class="flex min-w-0 items-center gap-3 md:col-span-3">
                                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700 ring-1 ring-primary-200/70 dark:bg-primary-950/70 dark:text-primary-300 dark:ring-primary-800/60">
                                                            <x-ui.icon name="cube" class="h-5 w-5" />
                                                        </span>
                                                        <div class="min-w-0">
                                                            <p class="break-words font-semibold text-neutral-950 dark:text-white">{{ $line->item_description ?: $line->item->name }}</p>
                                                            <p class="mt-0.5 text-xs font-medium tabular-nums text-neutral-500 dark:text-neutral-400">
                                                                Requested: {{ number_format($line->target_quantity) }} {{ $line->uom }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <input type="hidden" name="lines[{{ $i }}][rfq_line_item_id]" value="{{ $line->id }}">

                                                    <div class="min-w-0 space-y-1.5">
                                                        <label for="line-{{ $invite->id }}-{{ $i }}-unit-price" class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">
                                                            Unit price <span class="text-danger-600 dark:text-danger-400" aria-hidden="true">*</span><span class="sr-only">(required)</span>
                                                        </label>
                                                        <div class="relative">
                                                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-neutral-500 dark:text-neutral-400">&#8369;</span>
                                                            <input
                                                                id="line-{{ $invite->id }}-{{ $i }}-unit-price"
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

                                                    <x-ui.field id="line-{{ $invite->id }}-{{ $i }}-quantity" name="lines[{{ $i }}][offered_quantity]" label="Quantity" type="number" min="1" :value="old('lines.'.$i.'.offered_quantity', $line->target_quantity)" required />
                                                    <x-ui.field id="line-{{ $invite->id }}-{{ $i }}-lead-time" name="lines[{{ $i }}][lead_time_days]" label="Lead time (days)" type="number" min="0" max="3650" placeholder="Enter lead time" required />
                                                </fieldset>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-neutral-200 bg-neutral-50 px-4 py-4 sm:px-5 dark:border-neutral-800 dark:bg-neutral-800/60">
                                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'rfq-bid-{{ $invite->id }}')">Cancel</x-ui.button>
                                        <x-ui.button type="submit" icon="paper-airplane" data-loading-text="Submitting sealed bid..." disabled x-bind:disabled="!canSubmit">Submit sealed bid</x-ui.button>
                                    </div>
                                </form>
                            </x-ui.modal>
                        @else
                            <p class="mt-4 text-sm font-semibold text-neutral-600 dark:text-neutral-300">Bidding is closed.</p>
                        @endif
                    @endcan
                </div>
            </x-ui.card>
            @empty
                <x-ui.card class="xl:col-span-2"><p>No RFQ invitations.</p></x-ui.card>
            @endforelse
        </div>

        <div>{{ $invitations->onEachSide(1)->links() }}</div>
    </div>
</x-layouts.supplier>
