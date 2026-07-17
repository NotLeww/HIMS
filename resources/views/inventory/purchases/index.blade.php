<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">Procurement & Sourcing Management</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Manage sourcing requests, supplier quotes, and purchase orders from a single procurement workspace.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Stage 1 • Planning & Demand Forecasting</h3>
                <form method="POST" action="{{ route('inventory.purchases.plans.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Item</label>
                        <select name="item_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required>
                            <option value="">Select item</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Current stock</label>
                        <input type="number" name="current_stock" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Historical usage</label>
                        <input type="number" name="historical_usage" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Upcoming need</label>
                        <input type="number" name="upcoming_need" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Reorder point</label>
                        <input type="number" name="reorder_point" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Trigger reason</label>
                        <input type="text" name="trigger_reason" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 font-semibold text-white">Create demand plan</button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Demand plans</h3>
                <div class="mt-4 space-y-2">
                    @php $plans = App\Models\Models\DemandPlan::with('item')->latest()->get(); @endphp
                    @forelse($plans as $plan)
                        <div class="rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-semibold text-[var(--text)]">{{ $plan->item?->name ?? '-' }}</span>
                                <span class="rounded-full bg-[var(--primary-light)] px-2 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-[var(--primary)]">{{ $plan->status }}</span>
                            </div>
                            <p class="mt-1 text-[var(--muted)]">Current stock: {{ $plan->current_stock }} • Historical usage: {{ $plan->historical_usage }} • Upcoming need: {{ $plan->upcoming_need }} • Reorder point: {{ $plan->reorder_point }}</p>
                            @if($plan->trigger_reason)
                                <p class="mt-1 text-[var(--muted)]">Trigger: {{ $plan->trigger_reason }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">No demand plans have been created yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Stage 2 • Procurement request</h3>
                <form method="POST" action="{{ route('inventory.purchases.requests.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Title</label>
                        <input type="text" name="title" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Priority</label>
                        <select name="priority" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Item</label>
                        <select name="item_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required>
                            <option value="">Select item</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Requested quantity</label>
                        <input type="number" name="requested_quantity" min="1" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Description</label>
                        <textarea name="description" rows="3" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Preferred supplier</label>
                        <select name="supplier_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="">Select supplier</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Evaluation status</label>
                        <select name="evaluation_status" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Evaluation score</label>
                        <input type="number" step="0.01" name="evaluation_score" min="0" max="100" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Approved by</label>
                        <input type="text" name="approved_by" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Approval notes</label>
                        <textarea name="approval_notes" rows="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 font-semibold text-white">Create request</button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Procurement requests</h3>
                <div class="mt-4 space-y-3">
                    @forelse($requests as $request)
                        <div class="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-[var(--text)]">{{ $request->title }}</p>
                                    <p class="text-sm text-[var(--muted)]">{{ $request->item?->name ?? '-' }} • Qty: {{ $request->requested_quantity }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-[var(--primary-light)] px-2 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-[var(--primary)]">{{ $request->priority }}</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-600">{{ $request->status }}</span>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('inventory.purchases.requests.approve', $request) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="text" name="approved_by" placeholder="Approver" class="rounded-lg border border-[var(--border)] px-2 py-1 text-sm" />
                                    <input type="text" name="approval_notes" placeholder="Approval notes" class="rounded-lg border border-[var(--border)] px-2 py-1 text-sm" />
                                    <select name="evaluation_status" class="rounded-lg border border-[var(--border)] bg-white px-2 py-1 text-sm">
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                    <input type="number" step="0.01" name="evaluation_score" placeholder="Score" class="w-24 rounded-lg border border-[var(--border)] px-2 py-1 text-sm" />
                                    <button type="submit" class="rounded-lg border border-emerald-500 px-3 py-1 text-sm font-medium text-emerald-600">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('inventory.purchases.quotes.store') }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="procurement_request_id" value="{{ $request->id }}" />
                                    <select name="supplier_id" class="rounded-lg border border-[var(--border)] bg-white px-2 py-1 text-sm" required>
                                        <option value="">Select supplier</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="number" step="0.01" name="quoted_price" placeholder="Quoted price" class="w-32 rounded-lg border border-[var(--border)] px-2 py-1 text-sm" required />
                                    <button type="submit" class="rounded-lg border border-[var(--border)] px-3 py-1 text-sm font-medium text-[var(--text)]">Submit quote</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">No procurement requests yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Supplier quotations</h3>
                <div class="mt-4 space-y-2">
                    @forelse($quotes as $quote)
                        <div class="rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-3 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-semibold text-[var(--text)]">{{ $quote->supplier?->name ?? '-' }}</span>
                                <span class="text-[var(--muted)]">₱{{ number_format($quote->quoted_price, 2) }}</span>
                            </div>
                            <p class="mt-1 text-[var(--muted)]">Request: {{ $quote->procurementRequest?->title ?? '-' }} • Status: {{ $quote->status }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">No quotes submitted yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Create purchase order</h3>
                <form method="POST" action="{{ route('inventory.purchases.orders.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Supplier</label>
                        <select name="supplier_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required>
                            <option value="">Select supplier</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Item</label>
                        <select name="item_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required>
                            <option value="">Select item</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Quantity</label>
                        <input type="number" name="quantity" min="1" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Unit cost</label>
                        <input type="number" step="0.01" name="unit_cost" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" required />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-[var(--text)]">Notes</label>
                        <textarea name="notes" rows="3" class="w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 font-semibold text-white">Create purchase order</button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Purchase Orders</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                        <thead>
                            <tr class="text-left text-[var(--muted)]">
                                <th class="px-3 py-2">PO Number</th>
                                <th class="px-3 py-2">Supplier</th>
                                <th class="px-3 py-2">Item</th>
                                <th class="px-3 py-2">Qty</th>
                                <th class="px-3 py-2">Unit Cost</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border)]">
                            @php $purchaseOrders = App\Models\Models\PurchaseOrder::with(['supplier', 'item'])->latest('requested_at')->get(); @endphp
                            @forelse($purchaseOrders as $order)
                                <tr>
                                    <td class="px-3 py-2 font-medium text-[var(--text)]">{{ $order->po_number }}</td>
                                    <td class="px-3 py-2">{{ $order->supplier?->name ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $order->item?->name ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $order->quantity }}</td>
                                    <td class="px-3 py-2">₱{{ number_format($order->unit_cost, 2) }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full bg-[var(--primary-light)] px-2 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-[var(--primary)]">{{ $order->status }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        @if($order->status !== 'received')
                                            <form method="POST" action="{{ route('inventory.purchases.receive', $order) }}">
                                                @csrf
                                                <button type="submit" class="rounded-lg border border-emerald-500 px-3 py-1 text-sm font-medium text-emerald-600">Receive goods</button>
                                            </form>
                                        @else
                                            <span class="text-sm text-[var(--muted)]">Received</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-3 py-4 text-center text-[var(--muted)]">No purchase orders yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
