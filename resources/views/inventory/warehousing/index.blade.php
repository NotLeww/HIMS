<x-app-layout>
    <style>
        [data-warehousing-header] .hims-page-header {
            --hims-header-image: url('{{ asset('img/hims-warehousing-hero-day.png') }}');
            --hims-header-position: right 58%;
        }

        .dark [data-warehousing-header] .hims-page-header {
            --hims-header-image: url('{{ asset('img/hims-warehousing-hero-night.png') }}');
        }
    </style>

    <div data-warehousing-header>
        <div class="hims-page-header flex h-36 min-h-36 w-full items-center" style="height: 144px; min-height: 144px; width: 100%;">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-primary-700 dark:text-primary-300">Hospital Supply Chain Execution</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100">Smart Warehousing System (SWS)</h1>
            </div>
        </div>
    </div>

            {{-- SWS Consolidated Workflow Navigation --}}
            @include('inventory.warehousing.partials.workflow_nav')

            {{-- High-Level KPI Matrix --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div data-metric-title="Active warehouse tasks" data-metric-details="{{ json_encode($metricDetails['tasks']) }}" class="flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs transition-all duration-150 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/95 dark:hover:border-neutral-700">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300 sm:text-sm">Active Tasks</span>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/80 dark:text-primary-300 dark:ring-primary-800/50"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-3xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white sm:text-4xl lg:text-5xl">{{ $metrics['open_tasks'] }}</span>
                        <span class="text-sm font-bold text-neutral-500 dark:text-neutral-400 sm:text-base">tasks</span>
                    </div>
                    <p class="mt-3.5 truncate border-t border-neutral-100 pt-2.5 text-xs font-medium text-neutral-600 dark:border-neutral-800/80 dark:text-neutral-300 sm:text-sm">Put-away, pick, and replenishment work · {{ $metrics['overdue_tasks'] > 0 ? $metrics['overdue_tasks'].' overdue' : 'On track' }}</p>
                </div>

                <div data-metric-title="Active storage locations" data-metric-details="{{ json_encode($metricDetails['locations']) }}" class="flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs transition-all duration-150 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/95 dark:hover:border-neutral-700">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-cyan-700 dark:text-cyan-300 sm:text-sm">Storage Locations</span>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-100 text-cyan-700 ring-1 ring-cyan-200 dark:bg-cyan-950/80 dark:text-cyan-300 dark:ring-cyan-800/50"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg></span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-3xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white sm:text-4xl lg:text-5xl">{{ $metrics['active_locations'] }}</span>
                        <span class="text-sm font-bold text-neutral-500 dark:text-neutral-400 sm:text-base">active</span>
                    </div>
                    <p class="mt-3.5 truncate border-t border-neutral-100 pt-2.5 text-xs font-medium text-neutral-600 dark:border-neutral-800/80 dark:text-neutral-300 sm:text-sm">Active bins, racks, shelves, and storage zones · {{ $metrics['total_locations'] }} total</p>
                </div>

                <div data-metric-title="Controlled items" data-metric-details="{{ json_encode($metricDetails['narcotics']) }}" class="flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs transition-all duration-150 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/95 dark:hover:border-neutral-700">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-violet-700 dark:text-violet-300 sm:text-sm">Narcotics Vault (PDEA)</span>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700 ring-1 ring-violet-200 dark:bg-violet-950/80 dark:text-violet-300 dark:ring-violet-800/50"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-3xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white sm:text-4xl lg:text-5xl">{{ $metrics['narcotics_items'] }}</span>
                        <span class="text-sm font-bold text-neutral-500 dark:text-neutral-400 sm:text-base">items</span>
                    </div>
                    <p class="mt-3.5 truncate border-t border-neutral-100 pt-2.5 text-xs font-medium text-neutral-600 dark:border-neutral-800/80 dark:text-neutral-300 sm:text-sm">RA 9165 / DDB Reg 1 s. 2014 DDRB Ledger · Dual-Custody</p>
                </div>

                <div data-metric-title="Pending consignment requests" data-metric-details="{{ json_encode($metricDetails['consignments']) }}" class="flex flex-col justify-between rounded-xl border border-neutral-200/90 bg-white p-5 shadow-xs transition-all duration-150 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-900/95 dark:hover:border-neutral-700">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300 sm:text-sm">Consignment Implants</span>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-950/80 dark:text-amber-300 dark:ring-amber-800/50"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></span>
                    </div>
                    <div class="mt-3 flex items-baseline gap-1.5">
                        <span class="text-3xl font-black tracking-tight tabular-nums text-neutral-950 dark:text-white sm:text-4xl lg:text-5xl">{{ $metrics['pending_bill_onlys'] }}</span>
                        <span class="text-sm font-bold text-neutral-500 dark:text-neutral-400 sm:text-base">requests</span>
                    </div>
                    <p class="mt-3.5 truncate border-t border-neutral-100 pt-2.5 text-xs font-medium text-neutral-600 dark:border-neutral-800/80 dark:text-neutral-300 sm:text-sm">Operating room serial consumption tracking · Bill-Only PRs</p>
                </div>
            </div>

            {{-- Recent Warehouse Operational Tasks & Scans --}}
            <div class="grid gap-6 lg:grid-cols-2">
                {{-- Active Tasks Queue --}}
                <div class="rounded-xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4">
                        <h3 class="text-base font-semibold text-neutral-900">Recent Warehouse Tasks</h3>
                        @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                            @if($metrics['open_tasks'] > 5 && $metrics['open_tasks'] > $recentTasks->count())
                                <a href="{{ route('inventory.warehouse-tasks.index') }}" class="text-xs font-semibold text-primary-600 hover:text-primary-800">
                                    View All ({{ $metrics['open_tasks'] }}) &rarr;
                                </a>
                            @endif
                        @endcan
                    </div>
                    <div class="divide-y divide-neutral-100 overflow-hidden">
                        @forelse($recentTasks as $task)
                            <div class="flex items-center justify-between px-6 py-3.5 hover:bg-neutral-50">
                                <div>
                                    <div class="flex items-center gap-2">
                                        @can(\App\Enums\Permission::ViewWarehouseTasks->value)
                                        <a href="{{ route('inventory.warehouse-tasks.show', $task) }}" class="font-mono text-xs font-bold text-primary-700 hover:underline">
                                            {{ $task->task_number }}
                                        </a>
                                        @else
                                        <span class="font-mono text-xs font-bold text-neutral-700">
                                            {{ $task->task_number }}
                                        </span>
                                        @endcan
                                        <span class="rounded bg-neutral-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-neutral-700">
                                            {{ $task->task_type->label() }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-neutral-600">
                                        {{ $task->item?->name ?? 'General' }} &bull; {{ $task->requested_quantity }} units
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold
                                        @if($task->status->value === 'completed') bg-emerald-100 text-emerald-800
                                        @elseif($task->status->value === 'in_progress') bg-blue-100 text-blue-800
                                        @elseif($task->status->value === 'assigned') bg-amber-100 text-amber-800
                                        @else bg-neutral-100 text-neutral-700 @endif">
                                        {{ $task->status->label() }}
                                    </span>
                                    <p class="mt-0.5 text-[11px] text-neutral-500">
                                        {{ $task->assignedTo?->name ?? 'Unassigned' }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-sm text-neutral-500">
                                No warehouse tasks generated.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Real-Time Scan Event Stream --}}
                <div class="rounded-xl border border-neutral-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-6 py-4">
                        <div>
                            <h3 class="text-base font-semibold text-neutral-900">Real-Time Scan Log</h3>
                            <p class="text-xs text-neutral-500">GS1 DataMatrix and location barcode verification events.</p>
                        </div>
                    </div>
                    <div class="divide-y divide-neutral-100 overflow-hidden">
                        @forelse($recentScans as $scan)
                            <div class="flex items-center justify-between px-6 py-3 hover:bg-neutral-50">
                                <div class="truncate pr-4">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-2 w-2 rounded-full @if($scan->outcome === 'accepted') bg-emerald-500 @elseif($scan->outcome === 'identified') bg-amber-500 @else bg-red-500 @endif"></span>
                                        <span class="font-mono text-xs font-semibold text-neutral-800 truncate max-w-xs">{{ $scan->raw_value }}</span>
                                    </div>
                                    <p class="text-[11px] text-neutral-500 truncate mt-0.5">
                                        {{ $scan->message ?? 'Scan processed' }} &bull; {{ $scan->scannedBy?->name ?? 'Operator' }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="font-mono text-[10px] text-neutral-400">
                                        {{ $scan->created_at?->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <div class="py-8 text-center text-sm text-neutral-500">
                                No barcode scan events recorded yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
</x-app-layout>
