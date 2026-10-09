<x-layouts.supplier title="Performance">
    <div data-performance-header>
        <style>
            [data-performance-header] .hims-page-header {
                --hims-header-image: url('{{ asset('img/hims-supplier-performance-hero-day.png') }}');
            }

            .dark [data-performance-header] .hims-page-header {
                --hims-header-image: url('{{ asset('img/hims-supplier-performance-hero-night.png') }}');
            }
        </style>

        <x-ui.page-header
            title="Supplier Performance"
            subtitle="Latest hospital-approved scorecard calculated from operational records."
        />
    </div>

    <div class="mt-6">
        @if($supplier->latestApprovedScorecard)
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Overall score" :value="number_format($supplier->latestApprovedScorecard->total_score,1).'%'" icon="chart-bar" tone="primary" />
                <x-ui.stat label="On-time delivery" :value="number_format($supplier->latestApprovedScorecard->delivery_score,1).'%'" icon="truck" tone="success" />
                <x-ui.stat label="Fill rate" :value="number_format($supplier->latestApprovedScorecard->fill_rate_score,1).'%'" icon="archive-box" tone="primary" />
                <x-ui.stat label="Quality" :value="number_format($supplier->latestApprovedScorecard->quality_score,1).'%'" icon="shield-check" tone="success" />
            </div>

            <x-ui.card title="Assessment" class="mt-6">
                <p>{{ str($supplier->latestApprovedScorecard->recommendation)->headline() }}</p>
                <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">{{ $supplier->latestApprovedScorecard->notes ?: 'No additional assessment notes.' }}</p>
            </x-ui.card>
        @else
            <x-ui.empty-state
                artwork="reports"
                title="No approved scorecard"
                message="Your latest hospital-approved delivery, fill-rate, and quality assessment will appear here."
            />
        @endif
    </div>
</x-layouts.supplier>
