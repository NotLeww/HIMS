<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
            Inventory Items
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
                <h3 class="text-lg font-semibold text-[var(--text)]">Create Inventory Item</h3>
                <form method="POST" action="{{ route('inventory.items.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Name</label>
                        <input type="text" name="name" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">SKU</label>
                        <input type="text" name="sku" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Category</label>
                        <input type="text" name="category" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Unit</label>
                        <input type="text" name="unit" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Quantity On Hand</label>
                        <input type="number" name="quantity_on_hand" value="0" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Reorder Level</label>
                        <input type="number" name="reorder_level" value="0" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Supplier</label>
                        <select name="supplier_id" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="">Select supplier</option>
                            @foreach (App\Models\Models\Supplier::all() as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Warehouse</label>
                        <input type="text" name="warehouse_name" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--primary-dark)]">Save Item</button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Inventory List</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                        <thead class="bg-[var(--background)]">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Item</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">SKU</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Qty</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Reorder</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Supplier</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border)]">
                            @forelse ($items as $item)
                                <tr>
                                    <td class="px-3 py-2">{{ $item->name }}</td>
                                    <td class="px-3 py-2">{{ $item->sku }}</td>
                                    <td class="px-3 py-2">{{ $item->quantity_on_hand }}</td>
                                    <td class="px-3 py-2">{{ $item->reorder_level }}</td>
                                    <td class="px-3 py-2">{{ $item->supplier?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-[var(--muted)]">No inventory items yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
