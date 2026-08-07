<aside
    class="fixed inset-y-0 left-0 z-40 flex flex-col w-64 bg-white border-r border-neutral-200
           transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    {{-- Brand --}}
    <div class="flex items-center gap-2.5 h-16 px-5 border-b border-neutral-200 shrink-0">
        <span class="flex items-center justify-center w-9 h-9 rounded-md bg-primary-600 text-white
                     text-sm font-bold tracking-tight shrink-0">DJ</span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-neutral-900 leading-tight truncate">DJNRMHS</p>
            <p class="text-[11px] text-neutral-500 leading-tight truncate">Supply Chain &amp; Inventory</p>
        </div>
    </div>

    {{-- Navigation --}}
    <nav class="flex-1 px-3 py-4 space-y-6 overflow-y-auto" aria-label="Main navigation">
        <div class="space-y-0.5">
            <x-ui.nav-item :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">
                Dashboard
            </x-ui.nav-item>
        </div>

        <div>
            <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                Inventory
            </p>
            <div class="space-y-0.5">
                <x-ui.nav-item :href="route('inventory.items')" icon="cube"
                               :active="request()->routeIs('inventory.items*')">
                    Items
                </x-ui.nav-item>
                <x-ui.nav-item :href="route('inventory.stock')" icon="chart-bar"
                               :active="request()->routeIs('inventory.stock')">
                    Stock Levels
                </x-ui.nav-item>
                <x-ui.nav-item :href="route('inventory.stock-movements')" icon="arrows-right-left"
                               :active="request()->routeIs('inventory.stock-movements*')">
                    Stock Movements
                </x-ui.nav-item>
                <x-ui.nav-item :href="route('inventory.adjustments')" icon="clipboard-document-list"
                               :active="request()->routeIs('inventory.adjustments*')">
                    Adjustments
                </x-ui.nav-item>
            </div>
        </div>

        <div>
            <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                Warehousing
            </p>
            <div class="space-y-0.5">
                <x-ui.nav-item :href="route('inventory.storage-locations')" icon="building-storefront"
                               :active="request()->routeIs('inventory.storage-locations*')">
                    Storage Locations
                </x-ui.nav-item>
                <x-ui.nav-item :href="route('inventory.alerts')" icon="bell-alert"
                               :active="request()->routeIs('inventory.alerts')"
                               :badge="$openAlertCount ?? null">
                    Stock Alerts
                </x-ui.nav-item>
            </div>
        </div>

        <div>
            <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                Procurement
            </p>
            <div class="space-y-0.5">
                <x-ui.nav-item :href="route('inventory.purchases')" icon="clipboard-document-list"
                               :active="request()->routeIs('inventory.purchases*')">
                    Requisitions &amp; POs
                </x-ui.nav-item>
                <x-ui.nav-item :href="route('inventory.suppliers')" icon="truck"
                               :active="request()->routeIs('inventory.suppliers*')">
                    Suppliers
                </x-ui.nav-item>
            </div>
        </div>

        <div>
            <p class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                Records &amp; Analysis
            </p>
            <div class="space-y-0.5">
                <x-ui.nav-item :href="route('inventory.logistics')" icon="document-text"
                               :active="request()->routeIs('inventory.logistics')">
                    Documents &amp; Logistics
                </x-ui.nav-item>
                <x-ui.nav-item :href="route('inventory.reports')" icon="chart-bar"
                               :active="request()->routeIs('inventory.reports')">
                    Reports
                </x-ui.nav-item>
                {{-- Forecasting is served by the external Node service; no route yet. --}}
                <x-ui.nav-item icon="chart-bar" disabled>
                    Demand Forecast
                </x-ui.nav-item>
                {{-- User management is not built yet; roles still to be confirmed. --}}
                <x-ui.nav-item icon="users" disabled>
                    User Management
                </x-ui.nav-item>
            </div>
        </div>
    </nav>

    {{-- Footer --}}
    <div class="px-3 py-3 border-t border-neutral-200 shrink-0">
        <p class="px-3 text-[11px] text-neutral-400">
            {{ config('app.name', 'HIMS') }} &middot; v1.0
        </p>
    </div>
</aside>
