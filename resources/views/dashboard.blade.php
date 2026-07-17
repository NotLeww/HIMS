<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">Operations Dashboard</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Monitor inventory health, stock movement activity, and warehouse readiness from one place.</p>
            </div>
            <div class="inline-flex items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--muted)]">
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                System live
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Total items</p>
                    <p class="mt-2 text-3xl font-semibold text-[var(--text)]">{{ $totalItems ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Low stock / out of stock</p>
                    <p class="mt-2 text-3xl font-semibold text-amber-600">{{ $lowStockItems ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Total on hand</p>
                    <p class="mt-2 text-3xl font-semibold text-emerald-600">{{ $totalOnHand ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                    <p class="text-sm text-[var(--muted)]">Inventory value</p>
                    <p class="mt-2 text-3xl font-semibold text-[var(--text)]">₱{{ number_format($totalInventoryValue ?? 0, 2) }}</p>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[2fr,1fr]">
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-[var(--text)]">Quick actions</h3>
                            <p class="mt-1 text-sm text-[var(--muted)]">Jump straight into the core warehouse operations.</p>
                        </div>
                    </div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <a href="{{ route('inventory.items') }}" class="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                            <p class="font-semibold text-[var(--text)]">Create inventory item</p>
                            <p class="mt-1 text-sm text-[var(--muted)]">Register new stock and attach suppliers.</p>
                        </a>
                        <a href="{{ route('inventory.stock-movements') }}" class="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                            <p class="font-semibold text-[var(--text)]">Record movement</p>
                            <p class="mt-1 text-sm text-[var(--muted)]">Log stock in, stock out, and transfer activity.</p>
                        </a>
                        <a href="{{ route('inventory.storage-locations') }}" class="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                            <p class="font-semibold text-[var(--text)]">Manage storage</p>
                            <p class="mt-1 text-sm text-[var(--muted)]">Keep warehouse zones, bins, and locations organized.</p>
                        </a>
                        <a href="{{ route('inventory.reports') }}" class="rounded-xl border border-[var(--border)] bg-[var(--background)] p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                            <p class="font-semibold text-[var(--text)]">Review reports</p>
                            <p class="mt-1 text-sm text-[var(--muted)]">Open inventory summaries and performance reporting.</p>
                        </a>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-[var(--text)]">Operational snapshot</h3>
                        <div class="mt-4 space-y-3 text-sm text-[var(--muted)]">
                            <div class="flex items-center justify-between rounded-lg bg-[var(--background)] px-3 py-2">
                                <span>Suppliers</span>
                                <span class="font-semibold text-[var(--text)]">{{ $totalSuppliers ?? 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-[var(--background)] px-3 py-2">
                                <span>Active suppliers</span>
                                <span class="font-semibold text-emerald-600">{{ $activeSuppliers ?? 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-[var(--background)] px-3 py-2">
                                <span>Storage locations</span>
                                <span class="font-semibold text-[var(--text)]">{{ $storageLocations ?? 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-[var(--background)] px-3 py-2">
                                <span>Out of stock</span>
                                <span class="font-semibold text-rose-600">{{ $outOfStockItems ?? 0 }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-[var(--text)]">Recent activity</h3>
                            <a href="{{ route('inventory.stock-movements') }}" class="text-sm font-medium text-[var(--primary)]">View all</a>
                        </div>
                        @if($recentMovements->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($recentMovements as $movement)
                                    <div class="rounded-lg border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-semibold text-[var(--text)]">{{ $movement->item?->name ?? 'Item' }}</span>
                                            <span class="rounded-full bg-[var(--primary-light)] px-2 py-1 text-xs font-medium uppercase tracking-[0.2em] text-[var(--primary)]">{{ $movement->movement_type }}</span>
                                        </div>
                                        <p class="mt-1 text-[var(--muted)]">{{ $movement->quantity }} unit(s) • {{ $movement->moved_at?->diffForHumans() }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--background)] px-3 py-4 text-sm text-[var(--muted)]">No recent stock movements yet.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
