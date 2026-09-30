<x-app-layout>
    @php
        $filters = $editing?->filters ?? [];
        $formAction = $editing
            ? route('inventory.reports.schedules.update', $editing)
            : route('inventory.reports.schedules.store');
        $selectedReportType = old('report_type', $editing?->report_type ?? 'stock_status');
        $selectedFrequency = old('frequency', $editing?->frequency ?? 'daily');
    @endphp

    <x-ui.page-header
        title="Scheduled Reports"
        subtitle="Generate current report data automatically and email it to an authorized HIMS user."
        :breadcrumbs="[
            'Reports & Analytics' => route('inventory.reports'),
            'Scheduled Reports' => null,
        ]"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" icon="arrow-left" :href="route('inventory.reports')">
                Back to Reports
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.alert variant="danger" title="Review the schedule details" :message="$errors->first()" />
    @endif

    <div class="grid min-w-0 items-start gap-4 xl:grid-cols-[minmax(20rem,0.75fr)_minmax(0,2fr)]">
        <x-ui.card
            :title="$editing ? 'Edit scheduled report' : 'Create scheduled report'"
            subtitle="Times use the HIMS application timezone (Asia/Manila)."
        >
            <form method="POST" action="{{ $formAction }}"
                  data-loading-text="{{ $editing ? 'Saving...' : 'Creating...' }}"
                  x-data="{
                      frequency: @js($selectedFrequency),
                      reportType: @js($selectedReportType),
                      inventoryFilters() { return ['all', 'stock_status', 'valuation', 'stock_by_location', 'expiry_exposure', 'movement_history', 'most_consumed', 'movements_by_type'].includes(this.reportType) },
                      supplierFilter() { return ['all', 'procurement_expense', 'spend_by_supplier'].includes(this.reportType) },
                      movementFilter() { return ['all', 'movement_history', 'movements_by_type'].includes(this.reportType) },
                      statusFilter() { return ['all', 'stock_status', 'valuation', 'stock_by_location'].includes(this.reportType) },
                  }"
                  class="space-y-4">
                @csrf
                @if ($editing)
                    @method('PATCH')
                @endif

                <x-ui.field name="report_type" label="Report" type="select" :options="$reportTypes"
                            :value="$selectedReportType" required x-model="reportType" />

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-ui.field name="report_format" label="Format" type="select" :options="$formats"
                                :value="$editing?->report_format ?? 'pdf'" required />
                    <x-ui.field name="recipient_user_id" label="Recipient" type="select" required
                                :value="$editing?->recipient_user_id" placeholder="Select an authorized user">
                        @foreach ($recipients as $recipient)
                            <option value="{{ $recipient->id }}" @selected((string) old('recipient_user_id', $editing?->recipient_user_id) === (string) $recipient->id)>
                                {{ $recipient->name }} — {{ $recipient->email }}
                            </option>
                        @endforeach
                    </x-ui.field>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <x-ui.field name="frequency" label="Frequency" type="select" :options="$frequencies"
                                :value="$selectedFrequency" required x-model="frequency" />
                    <x-ui.field name="run_at" label="Run time" type="time"
                                :value="substr((string) ($editing?->run_at ?? '08:00'), 0, 5)" required />
                </div>

                <div x-show="frequency === 'weekly'" x-cloak>
                    <x-ui.field name="day_of_week" label="Day of week" type="select" :options="$daysOfWeek"
                                :value="$editing?->day_of_week ?? 1" x-bind:disabled="frequency !== 'weekly'" />
                </div>

                <div x-show="frequency === 'monthly'" x-cloak>
                    <x-ui.field name="day_of_month" label="Day of month" type="number"
                                :value="$editing?->day_of_month ?? 1" min="1" max="31"
                                hint="For shorter months, HIMS runs the report on the last day."
                                x-bind:disabled="frequency !== 'monthly'" />
                </div>

                <div class="border-t border-neutral-200 pt-4 dark:border-neutral-800">
                    <h3 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Report filters</h3>
                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">Relative periods are recalculated whenever the report runs.</p>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <x-ui.field name="period" label="Reporting period" type="select" :options="$periods"
                                    :value="$filters['period'] ?? '30'" required />

                        <div x-show="inventoryFilters()" x-cloak>
                            <x-ui.field name="category_id" label="Category" type="select"
                                        :value="$filters['category_id'] ?? null" placeholder="All categories"
                                        x-bind:disabled="!inventoryFilters()">
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id', $filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </x-ui.field>
                        </div>

                        <div x-show="inventoryFilters()" x-cloak>
                            <x-ui.field name="storage_location_id" label="Location" type="select"
                                        :value="$filters['storage_location_id'] ?? null" placeholder="All locations"
                                        x-bind:disabled="!inventoryFilters()">
                                @foreach ($locations as $location)
                                    <option value="{{ $location->id }}" @selected((string) old('storage_location_id', $filters['storage_location_id'] ?? '') === (string) $location->id)>{{ $location->name }} ({{ $location->code }})</option>
                                @endforeach
                            </x-ui.field>
                        </div>

                        <div x-show="supplierFilter()" x-cloak>
                            <x-ui.field name="supplier_id" label="Supplier" type="select"
                                        :value="$filters['supplier_id'] ?? null" placeholder="All suppliers"
                                        x-bind:disabled="!supplierFilter()">
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $filters['supplier_id'] ?? '') === (string) $supplier->id)>{{ $supplier->name }}</option>
                                @endforeach
                            </x-ui.field>
                        </div>

                        <div x-show="movementFilter()" x-cloak>
                            <x-ui.field name="movement_type" label="Movement type" type="select"
                                        :value="$filters['movement_type'] ?? null" placeholder="All movement types"
                                        x-bind:disabled="!movementFilter()">
                                @foreach ($movementTypes as $movementType)
                                    <option value="{{ $movementType->value }}" @selected(old('movement_type', $filters['movement_type'] ?? '') === $movementType->value)>{{ $movementType->label() }}</option>
                                @endforeach
                            </x-ui.field>
                        </div>

                        <div x-show="statusFilter()" x-cloak>
                            <x-ui.field name="status" label="Stock status" type="select"
                                        :value="$filters['status'] ?? null" placeholder="All stock statuses"
                                        x-bind:disabled="!statusFilter()"
                                        :options="['in_stock' => 'In Stock', 'low_stock' => 'Low Stock', 'out_of_stock' => 'Out of Stock']" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                    <x-ui.button type="submit" icon="calendar" data-loading-text="{{ $editing ? 'Saving...' : 'Creating...' }}">
                        {{ $editing ? 'Save Schedule' : 'Create Schedule' }}
                    </x-ui.button>
                    @if ($editing)
                        <x-ui.button variant="secondary" :href="route('inventory.reports.schedules')">Cancel</x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card>

        <div class="min-w-0 space-y-4">
            <x-ui.card title="Schedules" subtitle="Only active schedules with a future next run are picked up by the server scheduler." :padding="false">
                <x-ui.table :stickyHeader="false">
                    <x-ui.table.head>
                        <x-ui.table.th>Report</x-ui.table.th>
                        <x-ui.table.th>Frequency</x-ui.table.th>
                        <x-ui.table.th class="hidden md:table-cell">Recipient</x-ui.table.th>
                        <x-ui.table.th class="hidden lg:table-cell">Next / Last Run</x-ui.table.th>
                        <x-ui.table.th>Status</x-ui.table.th>
                        <x-ui.table.th align="right">Actions</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($schedules as $schedule)
                            <x-ui.table.row>
                                <x-ui.table.td>
                                    <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $reportTypes[$schedule->report_type] ?? $schedule->report_type }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ strtoupper($schedule->report_format) }}</p>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <p>{{ $schedule->frequencyLabel() }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ substr($schedule->run_at, 0, 5) }} PHT</p>
                                </x-ui.table.td>
                                <x-ui.table.td class="hidden md:table-cell">
                                    <p class="max-w-48 truncate" title="{{ $schedule->recipient?->name }}">{{ $schedule->recipient?->name ?? 'Unavailable' }}</p>
                                    <p class="max-w-48 truncate text-xs text-neutral-500 dark:text-neutral-400" title="{{ $schedule->recipient?->email }}">{{ $schedule->recipient?->email }}</p>
                                </x-ui.table.td>
                                <x-ui.table.td class="hidden lg:table-cell">
                                    <p>{{ $schedule->next_run_at?->format('M d, Y h:i A') ?? 'Disabled' }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">Last: {{ $schedule->last_run_at?->format('M d, Y h:i A') ?? 'Never' }}</p>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <x-ui.badge :status="$schedule->is_active ? ($schedule->last_status ?? 'active') : 'inactive'" />
                                </x-ui.table.td>
                                <x-ui.table.td align="right">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <x-ui.button size="sm" variant="secondary" icon="pencil-square" :href="route('inventory.reports.schedules', ['edit' => $schedule->id])">Edit</x-ui.button>
                                        <form method="POST" action="{{ route('inventory.reports.schedules.toggle', $schedule) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-ui.button type="submit" size="sm" variant="secondary" data-loading-text="Updating...">
                                                {{ $schedule->is_active ? 'Disable' : 'Enable' }}
                                            </x-ui.button>
                                        </form>
                                        <form method="POST" action="{{ route('inventory.reports.schedules.destroy', $schedule) }}"
                                              data-confirm-title="Delete scheduled report"
                                              data-confirm-message="Delete this schedule? Its execution history will be preserved."
                                              data-confirm-label="Delete Schedule">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" size="sm" variant="danger" icon="trash" data-loading-text="Deleting...">Delete</x-ui.button>
                                        </form>
                                    </div>
                                </x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty colspan="6" icon="calendar" title="No scheduled reports" message="Create the first schedule using the form." />
                        @endforelse
                    </tbody>
                </x-ui.table>
                @if ($schedules->hasPages())
                    <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-800">
                        {{ $schedules->onEachSide(1)->links() }}
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card title="Execution history and email log" subtitle="Accepted means the configured mailer accepted the message; it does not prove final inbox delivery." :padding="false">
                <x-ui.table :stickyHeader="false">
                    <x-ui.table.head>
                        <x-ui.table.th>Scheduled Run</x-ui.table.th>
                        <x-ui.table.th>Report</x-ui.table.th>
                        <x-ui.table.th class="hidden md:table-cell">Recipient</x-ui.table.th>
                        <x-ui.table.th>Result</x-ui.table.th>
                        <x-ui.table.th class="hidden lg:table-cell">Completed</x-ui.table.th>
                    </x-ui.table.head>
                    <tbody>
                        @forelse ($executions as $execution)
                            <x-ui.table.row>
                                <x-ui.table.td>{{ $execution->scheduled_for->format('M d, Y h:i A') }}</x-ui.table.td>
                                <x-ui.table.td>
                                    <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $reportTypes[$execution->report_type] ?? $execution->report_type }}</p>
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ strtoupper($execution->report_format) }} · {{ $execution->record_count ?? 0 }} records</p>
                                </x-ui.table.td>
                                <x-ui.table.td class="hidden md:table-cell">
                                    <p class="max-w-56 truncate" title="{{ $execution->recipient_email }}">{{ $execution->recipient_email }}</p>
                                </x-ui.table.td>
                                <x-ui.table.td>
                                    <x-ui.badge :status="$execution->status" />
                                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">Email: {{ ucfirst(str_replace('_', ' ', $execution->mail_status)) }}</p>
                                    @if ($execution->error_summary)
                                        <p class="mt-1 max-w-72 text-xs text-danger-700 dark:text-danger-300">{{ $execution->error_summary }}</p>
                                    @endif
                                </x-ui.table.td>
                                <x-ui.table.td class="hidden lg:table-cell">{{ $execution->completed_at?->format('M d, Y h:i A') ?? 'In progress' }}</x-ui.table.td>
                            </x-ui.table.row>
                        @empty
                            <x-ui.table.empty colspan="5" icon="envelope" title="No executions yet" message="History appears after the server scheduler reaches a configured run time." />
                        @endforelse
                    </tbody>
                </x-ui.table>
                @if ($executions->hasPages())
                    <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-800">
                        {{ $executions->onEachSide(1)->links() }}
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
