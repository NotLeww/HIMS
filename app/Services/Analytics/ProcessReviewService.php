<?php

namespace App\Services\Analytics;

use App\Enums\AuditAction;
use App\Models\CycleCountDoc;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryShrinkageReport;
use App\Models\KpiProcessReview;
use App\Models\ProcessRecommendation;
use App\Models\ProcurementSavingsLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierScorecard;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessReviewService
{
    /** Tables whose contents can change generated process-review analytics. */
    private const SOURCE_TABLES = [
        'purchase_requests',
        'sourcing_rfqs',
        'sourcing_evaluations',
        'purchase_orders',
        'po_line_items',
        'inspection_acceptance_reports',
        'suppliers',
        'supplier_documents',
        'dpri_reference_prices',
        'cycle_count_docs',
        'cycle_count_lines',
        'inventory_items',
        'storage_locations',
    ];

    public function __construct(
        protected SupplierScoringService $supplierScorer,
        protected ProcurementSavingsService $procurementSavings,
        protected BottleneckAnalysisService $bottleneckAnalyzer,
        protected StockAccuracyService $stockAccuracy,
        protected RecommendationEngine $recommendationEngine,
        protected AuditLogger $auditLogger,
    ) {}

    /**
     * Check if enough operational records exist in the period to synthesize an evidence-based review.
     *
     * @return array{has_sufficient_data: bool, pos_count: int, iars_count: int, cycle_counts_count: int, message: string}
     */
    public function checkDataAvailability(Carbon $startDate, Carbon $endDate): array
    {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        $posCount = PurchaseOrder::query()->whereBetween('created_at', [$start, $end])->count();
        $iarsCount = InspectionAcceptanceReport::query()->whereBetween('created_at', [$start, $end])->count();
        $cycleCountsCount = CycleCountDoc::query()->whereBetween('scheduled_date', [$start, $end])->count();

        $totalTransactions = $posCount + $iarsCount + $cycleCountsCount;
        $hasSufficientData = $totalTransactions > 0;

        $message = $hasSufficientData
            ? "Operational data available: {$posCount} PO(s), {$iarsCount} IAR(s), and {$cycleCountsCount} physical audit(s)."
            : 'Insufficient operational data for this date range. At least 1 purchase order, IAR, or cycle count document is required.';

        return [
            'has_sufficient_data' => $hasSufficientData,
            'pos_count' => $posCount,
            'iars_count' => $iarsCount,
            'cycle_counts_count' => $cycleCountsCount,
            'message' => $message,
        ];
    }

    /**
     * Return a stable token that changes when any analytics source changes.
     */
    public function sourceDataVersion(): string
    {
        $queries = collect(self::SOURCE_TABLES)
            ->map(fn (string $table) => DB::table($table)->selectRaw(
                '? as source, COUNT(*) as row_count, MAX(updated_at) as last_updated_at',
                [$table],
            ));

        $combined = $queries->shift();
        foreach ($queries as $query) {
            $combined->unionAll($query);
        }

        $versions = DB::query()
            ->fromSub($combined, 'process_review_source_versions')
            ->orderBy('source')
            ->get();

        return hash('sha256', json_encode($versions, JSON_THROW_ON_ERROR));
    }

    /**
     * Synthesize and persist a comprehensive evidence-based process review.
     */
    public function createReview(array $data, User $evaluator): KpiProcessReview
    {
        $startDate = Carbon::parse($data['period_start']);
        $endDate = Carbon::parse($data['period_end']);

        $availability = $this->checkDataAvailability($startDate, $endDate);
        if (! $availability['has_sufficient_data']) {
            throw ValidationException::withMessages([
                'period_start' => 'Cannot create an evidence-based review: No operational transactions found in the specified date range.',
            ]);
        }

        return DB::transaction(function () use ($data, $evaluator, $startDate, $endDate) {
            // Generate review number
            $yearMonth = now()->format('Ym');
            $countThisMonth = KpiProcessReview::where('review_number', 'like', "REV-{$yearMonth}-%")->count() + 1;
            $reviewNumber = sprintf('REV-%s-%04d', $yearMonth, $countThisMonth);

            $analysis = $this->analyze($startDate, $endDate);

            $review = KpiProcessReview::create([
                'review_number' => $reviewNumber,
                'title' => $data['title'] ?? "Operational Process Review ({$startDate->toDateString()} - {$endDate->toDateString()})",
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
                'evaluator_id' => $evaluator->id,
                'status' => 'draft',
                'qualitative_context' => $data['qualitative_context'] ?? null,
                'executive_summary' => $data['executive_summary'] ?? null,
                'metrics_summary' => $analysis['metrics_summary'],
            ]);

            $this->persistAnalysis($review, $analysis);

            $this->auditLogger->record(
                action: AuditAction::CreatedProcessReview,
                actor: $evaluator,
                target: $review,
                description: "Synthesized process review {$review->review_number}",
                newValues: [
                    'review_number' => $review->review_number,
                    'period' => "{$review->period_start->toDateString()} to {$review->period_end->toDateString()}",
                ],
            );

            return $review;
        });
    }

    /**
     * Recompute generated analytics for a draft while preserving its narrative.
     */
    public function refreshDraftAnalysis(KpiProcessReview $review, User $actor): bool
    {
        return DB::transaction(function () use ($review, $actor) {
            $lockedReview = KpiProcessReview::query()->lockForUpdate()->findOrFail($review->getKey());

            if (! $lockedReview->isDraft()) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft process reviews can refresh their generated analysis.',
                ]);
            }

            $oldMetrics = $lockedReview->metrics_summary ?? [];
            $currentVersion = $this->sourceDataVersion();
            if (($oldMetrics['source_data_version'] ?? null) === $currentVersion) {
                return false;
            }

            $oldCounts = [
                'supplier_scorecards' => $lockedReview->supplierScorecards()->count(),
                'procurement_savings_logs' => $lockedReview->procurementSavingsLogs()->count(),
                'inventory_shrinkage_reports' => $lockedReview->inventoryShrinkageReports()->count(),
                'process_recommendations' => $lockedReview->processRecommendations()->count(),
            ];

            $analysis = $this->analyze($lockedReview->period_start, $lockedReview->period_end);

            $lockedReview->processRecommendations()->delete();
            $lockedReview->inventoryShrinkageReports()->delete();
            $lockedReview->procurementSavingsLogs()->delete();
            $lockedReview->supplierScorecards()->delete();

            $lockedReview->update(['metrics_summary' => $analysis['metrics_summary']]);
            $this->persistAnalysis($lockedReview, $analysis);

            $newCounts = [
                'supplier_scorecards' => $lockedReview->supplierScorecards()->count(),
                'procurement_savings_logs' => $lockedReview->procurementSavingsLogs()->count(),
                'inventory_shrinkage_reports' => $lockedReview->inventoryShrinkageReports()->count(),
                'process_recommendations' => $lockedReview->processRecommendations()->count(),
            ];

            $this->auditLogger->record(
                action: AuditAction::RefreshedProcessReview,
                actor: $actor,
                target: $lockedReview,
                description: "Refreshed generated analysis for {$lockedReview->review_number}",
                oldValues: [
                    'source_data_version' => $oldMetrics['source_data_version'] ?? null,
                    'generated_record_counts' => $oldCounts,
                ],
                newValues: [
                    'source_data_version' => $analysis['metrics_summary']['source_data_version'],
                    'analysis_refreshed_at' => $analysis['metrics_summary']['analysis_refreshed_at'],
                    'generated_record_counts' => $newCounts,
                ],
            );

            return true;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function analyze(Carbon $startDate, Carbon $endDate): array
    {
        $supplierScores = $this->supplierScorer->evaluate($startDate, $endDate);
        $savingsData = $this->procurementSavings->evaluate($startDate, $endDate);
        $bottleneckData = $this->bottleneckAnalyzer->evaluate($startDate, $endDate);
        $stockAccuracyData = $this->stockAccuracy->evaluate($startDate, $endDate);
        $recommendations = $this->recommendationEngine->generate(
            $supplierScores,
            $savingsData,
            $bottleneckData,
            $stockAccuracyData,
        );

        $avgSupplierScore = $supplierScores->isNotEmpty()
            ? round($supplierScores->avg('total_score'), 2)
            : null;

        return [
            'supplier_scores' => $supplierScores,
            'savings_data' => $savingsData,
            'stock_accuracy_data' => $stockAccuracyData,
            'recommendations' => $recommendations,
            'metrics_summary' => [
                'total_spend' => $savingsData['summary']['total_spend'],
                'net_savings_amount' => $savingsData['summary']['net_savings_amount'],
                'aggregate_savings_pct' => $savingsData['summary']['aggregate_savings_pct'],
                'ceiling_breaches_count' => $savingsData['summary']['ceiling_breaches_count'],
                'suppliers_evaluated' => $supplierScores->count(),
                'avg_supplier_score' => $avgSupplierScore,
                'critical_bottleneck' => $bottleneckData['critical_bottleneck'],
                'bottleneck_stages' => $bottleneckData['stages'],
                'total_audits_count' => $stockAccuracyData['summary']['total_audits_count'],
                'total_shrinkage_value' => $stockAccuracyData['summary']['total_loss_value'],
                'overall_stock_accuracy' => $stockAccuracyData['summary']['overall_accuracy_rate'],
                'shrinkage_escalations_count' => $stockAccuracyData['summary']['escalation_count'],
                'source_data_version' => $this->sourceDataVersion(),
                'analysis_refreshed_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function persistAnalysis(KpiProcessReview $review, array $analysis): void
    {
        foreach ($analysis['supplier_scores'] as $score) {
            SupplierScorecard::create([
                'kpi_process_review_id' => $review->id,
                'supplier_id' => $score['supplier_id'],
                'delivery_score' => $score['delivery_score'],
                'quality_score' => $score['quality_score'],
                'fill_rate_score' => $score['fill_rate_score'],
                'total_score' => $score['total_score'],
                'total_pos_count' => $score['total_pos_count'],
                'completed_pos_count' => $score['completed_pos_count'],
                'late_deliveries_count' => $score['late_deliveries_count'],
                'avg_lead_time_days' => $score['avg_lead_time_days'],
                'promised_lead_time_days' => $score['promised_lead_time_days'],
                'non_conformance_count' => $score['non_conformance_count'],
                'temperature_excursions_count' => $score['temperature_excursions_count'],
                'has_valid_lto' => $score['has_valid_lto'],
                'has_valid_cpr' => $score['has_valid_cpr'],
                'recommendation' => $score['recommendation'],
                'notes' => $score['notes'],
            ]);
        }

        foreach ($analysis['savings_data']['logs'] as $log) {
            ProcurementSavingsLog::create([
                'kpi_process_review_id' => $review->id,
                'purchase_order_id' => $log['purchase_order_id'],
                'purchase_order_line_id' => $log['purchase_order_line_id'],
                'inventory_item_id' => $log['inventory_item_id'],
                'pndf_code' => $log['pndf_code'],
                'item_name' => $log['item_name'],
                'uom' => $log['uom'],
                'quantity_procured' => $log['quantity_procured'],
                'actual_unit_price' => $log['actual_unit_price'],
                'dpri_ceiling_price' => $log['dpri_ceiling_price'],
                'variance_amount' => $log['variance_amount'],
                'savings_percentage' => $log['savings_percentage'],
                'is_above_ceiling' => $log['is_above_ceiling'],
                'justification' => $log['justification'],
            ]);
        }

        foreach ($analysis['stock_accuracy_data']['reports'] as $report) {
            InventoryShrinkageReport::create([
                'kpi_process_review_id' => $review->id,
                'cycle_count_doc_id' => $report['cycle_count_doc_id'],
                'storage_location_id' => $report['storage_location_id'],
                'inventory_item_id' => $report['inventory_item_id'],
                'ledger_book_quantity' => $report['ledger_book_quantity'],
                'physical_counted_quantity' => $report['physical_counted_quantity'],
                'shrinkage_quantity' => $report['shrinkage_quantity'],
                'shrinkage_rate_pct' => $report['shrinkage_rate_pct'],
                'unit_cost' => $report['unit_cost'],
                'total_loss_value' => $report['total_loss_value'],
                'shrinkage_reason' => $report['shrinkage_reason'],
                'requires_admin_escalation' => $report['requires_admin_escalation'],
                'notes' => $report['notes'],
            ]);
        }

        foreach ($analysis['recommendations'] as $recommendation) {
            ProcessRecommendation::create([
                'kpi_process_review_id' => $review->id,
                'category' => $recommendation['category'],
                'target_type' => $recommendation['target_type'],
                'target_id' => $recommendation['target_id'],
                'target_name' => $recommendation['target_name'],
                'problem_detected' => $recommendation['problem_detected'],
                'evidence_metrics' => $recommendation['evidence_metrics'],
                'root_cause_analysis' => $recommendation['root_cause_analysis'],
                'recommended_action' => $recommendation['recommended_action'],
                'expected_operational_benefit' => $recommendation['expected_operational_benefit'],
                'priority' => $recommendation['priority'],
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Update qualitative notes and executive narrative on a draft review.
     */
    public function updateContext(KpiProcessReview $review, array $data, User $actor): KpiProcessReview
    {
        if (! $review->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft process reviews may be edited.',
            ]);
        }

        $before = [
            'title' => $review->title,
            'qualitative_context' => $review->qualitative_context,
            'executive_summary' => $review->executive_summary,
        ];

        $review->update([
            'title' => $data['title'] ?? $review->title,
            'qualitative_context' => $data['qualitative_context'] ?? $review->qualitative_context,
            'executive_summary' => $data['executive_summary'] ?? $review->executive_summary,
        ]);

        $this->auditLogger->record(
            action: AuditAction::UpdatedProcessReview,
            actor: $actor,
            target: $review,
            description: "Updated narrative for {$review->review_number}",
            oldValues: $before,
            newValues: [
                'title' => $review->title,
                'qualitative_context' => $review->qualitative_context,
                'executive_summary' => $review->executive_summary,
            ],
        );

        return $review;
    }

    /**
     * Submit a draft review for Maker-Checker approval.
     */
    public function submitForApproval(KpiProcessReview $review, User $actor): KpiProcessReview
    {
        if (! $review->isDraft()) {
            throw ValidationException::withMessages([
                'status' => 'Only draft reviews can be submitted for approval.',
            ]);
        }

        $review->update(['status' => 'submitted']);

        $this->auditLogger->record(
            action: AuditAction::SubmittedProcessReview,
            actor: $actor,
            target: $review,
            description: "Submitted process review {$review->review_number} for approval",
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'submitted'],
        );

        return $review;
    }

    /**
     * Formally approve a review (Maker-Checker Segregation of Duties).
     */
    public function approve(KpiProcessReview $review, User $approver): KpiProcessReview
    {
        if (! $review->isSubmitted()) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted reviews can be approved.',
            ]);
        }

        if (! $review->canBeApprovedBy($approver)) {
            throw ValidationException::withMessages([
                'approver' => 'Maker-Checker Violation: The author of the review cannot approve their own review, or you lack the required authority.',
            ]);
        }

        $review->update([
            'status' => 'approved',
            'approved_by_id' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->auditLogger->record(
            action: AuditAction::ApprovedProcessReview,
            actor: $approver,
            target: $review,
            description: "Approved process review {$review->review_number}",
            oldValues: ['status' => 'submitted'],
            newValues: [
                'status' => 'approved',
                'approved_by_id' => $approver->id,
                'approved_at' => $review->approved_at->toDateTimeString(),
            ],
        );

        return $review;
    }

    /**
     * Reject a review back to draft with formal reason.
     */
    public function reject(KpiProcessReview $review, User $approver, string $reason): KpiProcessReview
    {
        if (! $review->isSubmitted()) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted reviews can be rejected.',
            ]);
        }

        if (! $review->canBeApprovedBy($approver)) {
            throw ValidationException::withMessages([
                'approver' => 'Maker-Checker Violation: You lack the required authority to reject this review.',
            ]);
        }

        $review->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $this->auditLogger->record(
            action: AuditAction::RejectedProcessReview,
            actor: $approver,
            target: $review,
            description: "Rejected process review {$review->review_number}: {$reason}",
            oldValues: ['status' => 'submitted'],
            newValues: [
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ],
        );

        return $review;
    }

    /**
     * Execute/implement a process recommendation.
     */
    public function implementRecommendation(
        ProcessRecommendation $recommendation,
        User $actor,
        ?string $notes = null
    ): ProcessRecommendation {
        return DB::transaction(function () use ($recommendation, $actor, $notes) {
            $recommendation->update([
                'status' => 'implemented',
                'implemented_by_id' => $actor->id,
                'implemented_at' => now(),
                'implementation_notes' => $notes,
            ]);

            // If recommendation was lead time drift for a supplier, automatically apply update
            if ($recommendation->category === 'reorder_parameters' && $recommendation->target_type === Supplier::class && $recommendation->target_id) {
                $supplier = Supplier::find($recommendation->target_id);
                if ($supplier && isset($recommendation->evidence_metrics['realized_lead_days'])) {
                    $supplier->update([
                        'standard_lead_time_days' => round($recommendation->evidence_metrics['realized_lead_days']),
                    ]);
                }
            }

            // Check if all recommendations for the review are implemented
            $review = $recommendation->processReview;
            $remainingPending = $review->processRecommendations()->where('status', '!=', 'implemented')->count();
            if ($remainingPending === 0 && $review->isApproved()) {
                $review->update(['status' => 'implemented']);
            }

            $this->auditLogger->record(
                action: AuditAction::ImplementedProcessRecommendation,
                actor: $actor,
                target: $recommendation,
                description: "Implemented recommendation for {$recommendation->target_name}",
                newValues: [
                    'recommendation_id' => $recommendation->id,
                    'status' => 'implemented',
                    'implemented_by_id' => $actor->id,
                    'notes' => $notes,
                ],
            );

            return $recommendation;
        });
    }
}
