<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
            Stock Adjustments
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
                <h3 class="text-lg font-semibold text-[var(--text)]">Apply Adjustment</h3>
                <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Item</label>
                        <select name="item_id" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            @foreach (App\Models\Models\InventoryItem::all() as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Adjustment Type</label>
                        <select name="adjustment_type" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="increase">Increase</option>
                            <option value="decrease">Decrease</option>
                            <option value="correction">Correction / Set Value</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Quantity</label>
                        <input type="number" name="quantity" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Reason</label>
                        <input type="text" name="reason" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--primary-dark)]">Apply Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
