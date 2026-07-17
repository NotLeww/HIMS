<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
            Stock Movement & Transfer
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                    {{ session('success') }}
                </div>
            @endif

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-[var(--text)]">Record Movement</h3>
                        <p class="text-sm text-[var(--muted)]">Track stock-in, stock-out, and transfers between storage locations.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('inventory.stock-movements.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Item</label>
                        <select name="item_id" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Movement Type</label>
                        <select name="movement_type" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="stock_in">Stock In</option>
                            <option value="stock_out">Stock Out</option>
                            <option value="transfer">Transfer</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Quantity</label>
                        <input type="number" name="quantity" required min="1" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">From Location</label>
                        <select name="from_location_id" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="">None</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">To Location</label>
                        <select name="to_location_id" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="">None</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Remarks</label>
                        <input type="text" name="remarks" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--primary-dark)]">Save Movement</button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Movement History</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                        <thead class="bg-[var(--background)]">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Item</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Type</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Qty</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">From</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">To</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border)]">
                            @forelse ($movements as $movement)
                                <tr>
                                    <td class="px-3 py-2">{{ $movement->item?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ str_replace('_', ' ', $movement->movement_type) }}</td>
                                    <td class="px-3 py-2">{{ $movement->quantity }}</td>
                                    <td class="px-3 py-2">{{ $movement->fromLocation?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $movement->toLocation?->name ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $movement->moved_at?->format('M d, Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-4 text-[var(--muted)]">No movements yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
