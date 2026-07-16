<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
            Supplier & Vendor Management
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
                <h3 class="text-lg font-semibold text-[var(--text)]">Create Supplier</h3>
                <form method="POST" action="{{ route('inventory.suppliers.store') }}" class="mt-4 grid gap-4 md:grid-cols-2">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Name</label>
                        <input type="text" name="name" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Contact Person</label>
                        <input type="text" name="contact_person" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Email</label>
                        <input type="email" name="email" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Phone</label>
                        <input type="text" name="phone" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Address</label>
                        <input type="text" name="address" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Tax Number</label>
                        <input type="text" name="tax_number" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[var(--muted)]">Status</label>
                        <select name="status" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-[var(--muted)]">Notes</label>
                        <textarea name="notes" rows="3" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--primary-dark)]">Save Supplier</button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-[var(--text)]">Supplier List</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                        <thead class="bg-[var(--background)]">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Name</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Contact</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Email</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border)]">
                            @forelse ($suppliers as $supplier)
                                <tr>
                                    <td class="px-3 py-2">{{ $supplier->name }}</td>
                                    <td class="px-3 py-2">{{ $supplier->contact_person }}</td>
                                    <td class="px-3 py-2">{{ $supplier->email }}</td>
                                    <td class="px-3 py-2">{{ $supplier->status }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-[var(--muted)]">No suppliers yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
