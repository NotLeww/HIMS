<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700">Commission on Audit (COA) GAM Volume II App. 50</p>
                <h2 class="text-2xl font-bold text-neutral-900">Inspection & Acceptance Reports (IAR)</h2>
            </div>
            <div>
                @can(\App\Enums\Permission::ReceivePurchaseOrder->value)
                <a href="{{ route('inventory.receiving.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    Receiving Dock
                </a>
                @endcan
            </div>
        </div>
    </x-slot>

    @include('inventory.logistics.partials.nav')

    <div class="space-y-6" x-data="{ genModalOpen: false, selectedGrnId: null, selectedGrnNumber: '', defaultDr: '', defaultSi: '' }" @keydown.escape.window="genModalOpen = false">
            <x-ui.validation-summary />

            {{-- Flash Notifications --}}
            @if(session('success'))
                <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 shadow-sm">
                    <svg class="h-5 w-5 flex-shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                    <svg class="h-5 w-5 flex-shrink-0 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            {{-- Unreported Receipts Queue (GRNs without IAR) --}}
            @can(\App\Enums\Permission::ManageLogisticsRecords->value)
            @if($unreportedReceipts->isNotEmpty())
                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="flex items-center gap-2 text-sm font-bold text-amber-900">
                                <x-ui.icon name="arrow-down-tray" class="h-4 w-4 shrink-0" />
                                <span>Goods Receipts Awaiting Statutory IAR Generation ({{ $unreportedReceipts->count() }})</span>
                            </h3>
                            <p class="text-xs text-amber-700">Goods have arrived at receiving dock. Formal COA GAM Appendix 50 report must be generated for Technical Inspection.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($unreportedReceipts as $receipt)
                            <div class="rounded-lg border border-amber-200 bg-white p-4 shadow-sm flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-sm text-neutral-900">{{ $receipt->grn_number }}</span>
                                        <span class="text-xs text-neutral-500">{{ $receipt->received_at?->format('M d, Y') }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-neutral-600">
                                        PO: <span class="font-semibold text-primary-700">{{ $receipt->purchaseOrder?->po_number ?? 'Direct' }}</span>
                                    </div>
                                    <div class="text-xs text-neutral-600">
                                        Supplier: <span class="font-medium text-neutral-800">{{ $receipt->supplier?->name ?? 'N/A' }}</span>
                                    </div>
                                    <div class="mt-2 text-[11px] text-neutral-500">
                                        DR: {{ $receipt->dr_number ?? 'Pending' }} • SI: {{ $receipt->sales_invoice_number ?? 'Pending' }}
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-t border-neutral-100 flex justify-end">
                                    <button @click="selectedGrnId = {{ $receipt->id }}; selectedGrnNumber = '{{ $receipt->grn_number }}'; defaultDr = '{{ $receipt->dr_number }}'; defaultSi = '{{ $receipt->sales_invoice_number }}'; genModalOpen = true"
                                            class="inline-flex items-center gap-1 rounded bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">
                                        Generate COA IAR
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            @endcan

            {{-- Filter & Search Bar --}}
            <div class="rounded-2xl border border-neutral-200 bg-white p-3 shadow-xs dark:border-neutral-800 dark:bg-neutral-900 sm:p-4" data-iar-filter-toolbar>
                <form method="GET" action="{{ route('inventory.logistics.iar.index') }}" class="grid gap-3 md:grid-cols-12">
                    <div class="md:col-span-7">
                        <label for="search" class="sr-only">Search</label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <x-ui.icon name="magnifying-glass" class="h-4 w-4 text-neutral-400" />
                            </div>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   placeholder="Search IAR, PO, DR, invoice, supplier, or item..."
                                   class="block min-h-11 w-full rounded-xl border-neutral-300 bg-white pl-10 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100">
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <label for="status" class="sr-only">Status</label>
                        <select name="status" id="status" class="block min-h-11 w-full rounded-xl border-neutral-300 bg-white text-sm text-neutral-900 focus:border-primary-500 focus:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100">
                            <option value="">All Statuses</option>
                            <option value="pending_inspection" {{ request('status') === 'pending_inspection' ? 'selected' : '' }}>Pending Technical Inspection</option>
                            <option value="inspected_passed" {{ request('status') === 'inspected_passed' ? 'selected' : '' }}>Inspected (Awaiting Property Acceptance)</option>
                            <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                            <option value="inspected_failed" {{ request('status') === 'inspected_failed' ? 'selected' : '' }}>Inspection Failed</option>
                            <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 md:col-span-2">
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-neutral-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-neutral-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-white">
                            <x-ui.icon name="magnifying-glass" class="h-4 w-4" />
                            Search
                        </button>
                        @if(request()->hasAny(['search', 'status']))
                            <a href="{{ route('inventory.logistics.iar.index') }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-neutral-300 text-neutral-600 transition hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800" title="Reset filters" aria-label="Reset filters">
                                <x-ui.icon name="x-mark" class="h-4 w-4" />
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- IAR Table --}}
            <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-xs dark:border-neutral-800 dark:bg-neutral-900" data-iar-ledger>
                <div class="overflow-x-auto">
                    <table class="min-w-[1120px] w-full text-left text-sm">
                        <thead class="border-b border-neutral-200 bg-primary-50/40 text-[11px] font-bold uppercase tracking-wide text-primary-900 dark:border-neutral-800 dark:bg-primary-950/20 dark:text-primary-200">
                            <tr>
                                <th scope="col" class="w-[15%] px-5 py-4">IAR Reference</th>
                                <th scope="col" class="w-[23%] border-l border-neutral-200/70 px-5 py-4 dark:border-neutral-800">PO &amp; Entity</th>
                                <th scope="col" class="w-[15%] border-l border-neutral-200/70 px-5 py-4 dark:border-neutral-800">Commercial Proof (BIR)</th>
                                <th scope="col" class="w-[13%] border-l border-neutral-200/70 px-5 py-4 dark:border-neutral-800">Technical<br>Inspection</th>
                                <th scope="col" class="w-[13%] border-l border-neutral-200/70 px-5 py-4 dark:border-neutral-800">Custodial<br>Acceptance</th>
                                <th scope="col" class="w-[11%] border-l border-neutral-200/70 px-5 py-4 dark:border-neutral-800">COA Transmittal</th>
                                <th scope="col" class="w-[10%] border-l border-neutral-200/70 px-5 py-4 text-right dark:border-neutral-800">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-800 dark:bg-neutral-900">
                            @forelse($iars as $iar)
                                <tr class="align-top transition-colors hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40">
                                    <td class="px-5 py-4">
                                        <div class="font-bold leading-5 text-neutral-950 dark:text-white">{{ $iar->iar_number }}</div>
                                        <div class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ $iar->created_at->format('M d, Y') }}</div>
                                        <span class="mt-2 inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold
                                            @if($iar->status === 'accepted') bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300
                                            @elseif($iar->status === 'inspected_passed') bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300
                                            @elseif($iar->status === 'inspected_failed' || $iar->status === 'rejected') bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300
                                            @else bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 @endif">
                                            <x-ui.icon name="{{ $iar->status === 'accepted' ? 'check-circle' : ($iar->status === 'pending_inspection' ? 'clock' : 'information-circle') }}" class="h-3.5 w-3.5" />
                                            {{ ucwords(str_replace('_', ' ', $iar->status)) }}
                                        </span>
                                    </td>

                                    <td class="border-l border-neutral-200/70 px-5 py-4 text-xs dark:border-neutral-800">
                                        <div class="font-bold text-neutral-900 dark:text-neutral-100">{{ $iar->purchaseOrder->po_number ?? 'Direct Receipt' }}</div>
                                        <div class="mt-1 leading-4 text-neutral-500 dark:text-neutral-400">{{ $iar->supplier->name ?? 'N/A' }}</div>
                                    </td>

                                    <td class="border-l border-neutral-200/70 px-5 py-4 text-xs text-neutral-600 dark:border-neutral-800 dark:text-neutral-300">
                                        <div>DR: <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $iar->goodsReceiptNote->dr_number ?? 'None' }}</span></div>
                                        <div class="mt-1">SI: <span class="font-semibold text-neutral-900 dark:text-neutral-100">{{ $iar->invoice_number ?? 'Pending' }}</span></div>
                                    </td>

                                    <td class="border-l border-neutral-200/70 px-5 py-4 text-xs dark:border-neutral-800">
                                        @if($iar->inspection_date)
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"><x-ui.icon name="check-circle" class="h-3.5 w-3.5" /> Completed</span>
                                            <div class="mt-1.5 text-neutral-500 dark:text-neutral-400">{{ $iar->inspectedBy->name ?? 'Inspector' }}</div>
                                            <div class="mt-0.5 text-[11px] text-neutral-400">{{ $iar->inspection_date->format('M d, Y') }}</div>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                <x-ui.icon name="clock" class="h-3.5 w-3.5" />
                                                Awaiting Inspection
                                            </span>
                                        @endif
                                    </td>

                                    <td class="border-l border-neutral-200/70 px-5 py-4 text-xs dark:border-neutral-800">
                                        @if($iar->acceptance_date)
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"><x-ui.icon name="check-circle" class="h-3.5 w-3.5" /> Accepted</span>
                                            <div class="mt-1.5 text-neutral-500 dark:text-neutral-400">{{ $iar->acceptedBy->name ?? 'Custodian' }}</div>
                                            <div class="mt-0.5 text-[11px] text-neutral-400">{{ $iar->acceptance_date->format('M d, Y') }}</div>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary-50 px-2.5 py-1 text-[11px] font-semibold text-primary-700 dark:bg-primary-950/60 dark:text-primary-300"><x-ui.icon name="clock" class="h-3.5 w-3.5" /> Pending</span>
                                        @endif
                                    </td>

                                    <td class="border-l border-neutral-200/70 px-5 py-4 text-xs dark:border-neutral-800">
                                        @if($iar->coa_transmitted_at)
                                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-purple-100 px-2.5 py-1 text-[11px] font-semibold text-purple-800 dark:bg-purple-950/60 dark:text-purple-300"><x-ui.icon name="check-circle" class="h-3.5 w-3.5" /> Transmitted</span>
                                            <div class="mt-1.5 text-neutral-500 dark:text-neutral-400">{{ $iar->coa_transmitted_at->format('M d, Y') }}</div>
                                            <div class="mt-0.5 text-[10px] text-neutral-400">Rec: {{ $iar->coa_received_by }}</div>
                                        @elseif($iar->isAccepted())
                                            @if(!$iar->coa_transmittal_deadline_at)
                                                <span class="text-neutral-400">Deadline not recorded</span>
                                            @elseif($iar->isCoaDeadlineOverdue())
                                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-red-100 px-2.5 py-1 text-[11px] font-bold text-red-800 dark:bg-red-950/60 dark:text-red-300">
                                                    <x-ui.icon name="exclamation-triangle" class="h-3.5 w-3.5" /> Overdue
                                                </span>
                                            @elseif($iar->isCoaDeadlineDueWithin())
                                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-amber-100 px-2.5 py-1 text-[11px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                    <x-ui.icon name="clock" class="h-3.5 w-3.5" /> Due within 5 days
                                                </span>
                                            @else
                                                <span class="text-neutral-500 dark:text-neutral-400">Due {{ $iar->coa_transmittal_deadline_at->format('M d, Y') }}</span>
                                            @endif
                                        @else
                                            <span class="text-neutral-300">N/A</span>
                                        @endif
                                        @if($iar->liquidated_damages_amount > 0)
                                            <div class="mt-2 font-bold text-red-600 dark:text-red-400">
                                                Penalty: ₱{{ number_format($iar->liquidated_damages_amount, 2) }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="border-l border-neutral-200/70 px-5 py-4 text-right dark:border-neutral-800">
                                        <a href="{{ route('inventory.logistics.iar.show', $iar) }}"
                                           class="inline-flex min-h-10 items-center justify-center gap-2 whitespace-nowrap rounded-xl border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-900 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800">
                                            <x-ui.icon name="document-text" class="h-4 w-4" />
                                            View Report
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.table.empty colspan="7" artwork="receiving" title="No matching IAR records" :message="request()->hasAny(['search', 'status']) ? 'Try another keyword or clear the active filters.' : 'Inspection and Acceptance Reports will appear here after receiving.'" />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-3 border-t border-neutral-200 px-4 py-3 text-xs text-neutral-500 dark:border-neutral-800 dark:text-neutral-400 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <p>
                        Showing <span class="font-semibold tabular-nums text-neutral-700 dark:text-neutral-300">{{ $iars->firstItem() ?? 0 }}–{{ $iars->lastItem() ?? 0 }}</span>
                        of <span class="font-semibold tabular-nums text-neutral-700 dark:text-neutral-300">{{ $iars->total() }}</span> records
                    </p>
                    @if($iars->hasPages())
                        <div>{{ $iars->links() }}</div>
                    @endif
                </div>
            </div>

            {{-- Generate IAR Modal --}}
            @can(\App\Enums\Permission::ManageLogisticsRecords->value)
            <div x-show="genModalOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="genModalOpen" @click="genModalOpen = false" class="fixed inset-0 bg-neutral-900/60 transition-opacity"></div>
                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="genModalOpen" class="inline-block w-full max-w-md transform overflow-hidden rounded-2xl bg-white p-6 text-left align-bottom shadow-2xl transition-all sm:my-8 sm:align-middle">
                        <div class="flex items-center justify-between border-b border-neutral-200 pb-3">
                            <h3 class="text-base font-bold text-neutral-900">Generate COA GAM Appendix 50 IAR</h3>
                            <button @click="genModalOpen = false" class="text-neutral-400 hover:text-neutral-600">&times;</button>
                        </div>

                        <form :action="'/inventory/logistics/receipts/' + selectedGrnId + '/iar'" method="POST" class="mt-4 space-y-4"
                              data-confirm-title="Generate IAR document"
                              data-confirm-message="Are you sure you want to generate an official Inspection and Acceptance Report for this delivery?"
                              data-confirm-label="Generate IAR">
                            @csrf
                            <p class="text-xs text-neutral-600">Generating formal statutory report from Goods Receipt <span class="font-bold text-neutral-900" x-text="selectedGrnNumber"></span>.</p>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">Supplier Delivery Receipt (DR) #</label>
                                <input type="text" name="dr_number" :value="defaultDr" placeholder="e.g. DR-889922"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold uppercase text-neutral-600">BIR Sales Invoice (SI) # (RA 11976 EOPT)</label>
                                <input type="text" name="invoice_number" :value="defaultSi" placeholder="e.g. SI-2026-0988"
                                       class="mt-1 block w-full rounded-lg border-neutral-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                            </div>

                            <div class="mt-6 flex justify-end gap-3 border-t border-neutral-200 pt-4">
                                <button type="button" @click="genModalOpen = false" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Cancel</button>
                                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">Generate IAR</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endcan
        </div>
</x-app-layout>
