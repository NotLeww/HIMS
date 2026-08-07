<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-[var(--text)]">Logistics & Records</h2>
                <p class="mt-1 text-sm text-[var(--muted)]">Manage shipments, transfers and logistics records.</p>
            </div>
            <div>
                <a href="{{ route('inventory') }}" class="rounded-md border border-[var(--border)] bg-[var(--background)] px-3 py-2 text-sm text-[var(--muted)]">Back to Inventory</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
                <h3 class="text-sm font-medium text-[var(--text)]">Logistics</h3>
                <p class="mt-2 text-sm text-[var(--muted)]">This module will show transfers, deliveries, and carrier tracking. For now, use the Stock Movement or Storage Locations pages to view logistics activity.</p>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <a href="{{ route('inventory.stock-movements') }}" class="rounded-md border border-[var(--border)] bg-[var(--background)] p-4">
                        <p class="font-semibold text-[var(--text)]">Stock Movements</p>
                        <p class="mt-1 text-sm text-[var(--muted)]">View inbound/outbound and transfer history.</p>
                    </a>
                    <a href="{{ route('inventory.storage-locations') }}" class="rounded-md border border-[var(--border)] bg-[var(--background)] p-4">
                        <p class="font-semibold text-[var(--text)]">Storage Locations</p>
                        <p class="mt-1 text-sm text-[var(--muted)]">Manage physical locations and bins.</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
