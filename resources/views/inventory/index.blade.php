<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Supply Chain & Inventory
                </h2>
                <p class="text-sm text-gray-600 mt-1">Core modules for purchasing, receiving, stock movement, alerts, and reporting.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <a href="{{ route('inventory.suppliers') }}" class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-gray-900">Supplier & Vendor Management</h3>
                    <p class="mt-2 text-sm text-gray-600">Maintain vendor profiles, rebates, and supplier records.</p>
                </a>

                <a href="{{ route('inventory.purchases') }}" class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-gray-900">Purchase Orders & Receiving</h3>
                    <p class="mt-2 text-sm text-gray-600">Track purchase requisitions, purchase orders, and goods receiving.</p>
                </a>

                <a href="{{ route('inventory.stock') }}" class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-gray-900">Stock Movement & Transfers</h3>
                    <p class="mt-2 text-sm text-gray-600">Monitor stock in, stock out, internal transfers, and warehouse movement.</p>
                </a>

                <a href="{{ route('inventory.alerts') }}" class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-gray-900">Low Stock & Alerts</h3>
                    <p class="mt-2 text-sm text-gray-600">Flag critical items, reorder points, and expiry risk.</p>
                </a>

                <a href="{{ route('inventory.reports') }}" class="block rounded-xl border border-gray-200 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-gray-900">Reports & Dashboard</h3>
                    <p class="mt-2 text-sm text-gray-600">Review inventory summaries, movement history, and usage reporting.</p>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
