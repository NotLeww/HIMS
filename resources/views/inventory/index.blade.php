<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
                    Supply Chain & Inventory
                </h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Core modules for purchasing, receiving, stock movement, alerts, and reporting.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Total suppliers</p>
                    <p class="mt-2 text-3xl font-semibold text-[var(--text)]">{{ $totalSuppliers }}</p>
                </div>
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Active suppliers</p>
                    <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ $activeSuppliers }}</p>
                </div>
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Inactive suppliers</p>
                    <p class="mt-2 text-3xl font-semibold text-amber-600">{{ $inactiveSuppliers }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                <a href="{{ route('inventory.suppliers') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Supplier & Vendor Management</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Maintain vendor profiles, rebates, and supplier records.</p>
                </a>

                <a href="{{ route('inventory.purchases') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Purchase Orders & Receiving</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Track purchase requisitions, purchase orders, and goods receiving.</p>
                </a>

                <a href="{{ route('inventory.stock') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Stock Movement & Transfers</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Monitor stock in, stock out, internal transfers, and warehouse movement.</p>
                </a>

                <a href="{{ route('inventory.alerts') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Low Stock & Alerts</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Flag critical items, reorder points, and expiry risk.</p>
                </a>

                <a href="{{ route('inventory.reports') }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                    <h3 class="text-lg font-semibold text-[var(--text)]">Reports & Dashboard</h3>
                    <p class="mt-2 text-sm text-[var(--muted)]">Review inventory summaries, movement history, and usage reporting.</p>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
