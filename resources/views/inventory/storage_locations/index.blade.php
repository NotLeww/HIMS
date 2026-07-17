<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-[var(--text)]">
            Storage Locations
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
                        <h3 class="text-lg font-semibold text-[var(--text)]">Storage Locations</h3>
                        <p class="text-sm text-[var(--muted)]">Manage warehouse zones, bins, racks, and storage points.</p>
                    </div>
                    <button type="button" onclick="document.getElementById('add-location-form').classList.toggle('hidden')" class="rounded-xl bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--primary-dark)]">
                        Add Location
                    </button>
                </div>

                <div id="add-location-form" class="mt-5 hidden rounded-2xl border border-[var(--border)] bg-[var(--background)]/50 p-5">
                    <form method="POST" action="{{ route('inventory.storage-locations.store') }}" class="grid gap-4 md:grid-cols-2">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Name</label>
                            <input type="text" name="name" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Code</label>
                            <input type="text" name="code" required class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Zone</label>
                            <input type="text" name="zone" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Capacity</label>
                            <input type="number" name="capacity" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Status</label>
                            <select name="status" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[var(--muted)]">Description</label>
                            <input type="text" name="description" class="mt-1 w-full rounded-xl border border-[var(--border)] bg-[var(--background)] px-3 py-2" />
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="rounded-xl bg-[var(--primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--primary-dark)]">Save Location</button>
                        </div>
                    </form>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-[var(--border)] text-sm">
                        <thead class="bg-[var(--background)]">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Name</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Code</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Zone</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Capacity</th>
                                <th class="px-3 py-2 text-left font-semibold text-[var(--muted)]">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[var(--border)]">
                            @forelse ($locations as $location)
                                <tr>
                                    <td class="px-3 py-2">{{ $location->name }}</td>
                                    <td class="px-3 py-2">{{ $location->code }}</td>
                                    <td class="px-3 py-2">{{ $location->zone }}</td>
                                    <td class="px-3 py-2">{{ $location->capacity }}</td>
                                    <td class="px-3 py-2">{{ $location->status }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-4 text-[var(--muted)]">No storage locations yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
