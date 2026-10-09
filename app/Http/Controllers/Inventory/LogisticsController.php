<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AuditAction;
use App\Enums\DocumentType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ChainOfCustodyLog;
use App\Models\GoodsReceiptNote;
use App\Models\InspectionAcceptanceReport;
use App\Models\LogisticsDocument;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Services\AuditLogger;
use App\Services\Logistics\ChainOfCustodyService;
use App\Services\Logistics\DocumentTrackingService;
use App\Services\Logistics\InspectionAcceptanceService;
use App\Services\Logistics\ShipmentTrackingService;
use App\Support\DemoPdfBuilder;
use App\Support\MetricDetails;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LogisticsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewLogisticsRecords->value, only: [
                'dashboard',
            ]),
            new Middleware('can:'.Permission::ViewLogisticsSensitiveData->value, only: [
                'documents',
                'downloadDocument',
                'shipments',
                'iarIndex',
                'iarShow',
                'iarPrint',
                'iarDownload',
                'chainOfCustody',
                'risShow',
            ]),
            new Middleware('can:'.Permission::ManageLogisticsRecords->value, only: [
                'uploadDocument',
                'supersedeDocument',
                'recordDockArrival',
                'generateIarFromReceipt',
            ]),
            new Middleware('can:'.Permission::VerifyLogisticsDocuments->value, only: [
                'verifyDocument',
            ]),
            new Middleware('can:'.Permission::PerformTechnicalInspection->value, only: [
                'performTechnicalInspection',
            ]),
            new Middleware('can:'.Permission::ApproveIarAcceptance->value, only: [
                'approveCustodialAcceptance',
                'transmitToCoa',
            ]),
        ];
    }

    public function __construct(
        protected DocumentTrackingService $documentService,
        protected InspectionAcceptanceService $iarService,
        protected ShipmentTrackingService $shipmentService,
        protected ChainOfCustodyService $custodyService,
        protected AuditLogger $audit,
    ) {}

    /**
     * Logistics & Records Central Executive Dashboard
     */
    public function dashboard(): View
    {
        $documentMetrics = LogisticsDocument::query()
            ->selectRaw("SUM(CASE WHEN status != 'archived' THEN 1 ELSE 0 END) AS total_documents")
            ->selectRaw("SUM(CASE WHEN status IN ('submitted', 'pending_verification') THEN 1 ELSE 0 END) AS pending_verification")
            ->first();
        $iarMetrics = InspectionAcceptanceReport::query()
            ->selectRaw("SUM(CASE WHEN status = 'pending_inspection' THEN 1 ELSE 0 END) AS pending_inspection")
            ->selectRaw("SUM(CASE WHEN status = 'inspected_passed' THEN 1 ELSE 0 END) AS pending_acceptance")
            ->selectRaw("SUM(CASE WHEN status = 'accepted' AND coa_transmitted_at IS NULL AND acceptance_date <= ? THEN 1 ELSE 0 END) AS coa_due", [now()->subDays(3)->toDateString()])
            ->first();
        $shipmentMetrics = Shipment::query()
            ->selectRaw("SUM(CASE WHEN status IN ('dispatched', 'in_transit', 'customs_hold') THEN 1 ELSE 0 END) AS active_shipments")
            ->selectRaw('SUM(CASE WHEN temp_excursion = ? THEN 1 ELSE 0 END) AS dock_excursions', [true])
            ->selectRaw("SUM(CASE WHEN status NOT IN ('arrived_at_dock', 'received') AND estimated_delivery_date IS NOT NULL AND estimated_delivery_date < ? THEN 1 ELSE 0 END) AS overdue_shipments", [now()->toDateString()])
            ->first();

        $metrics = [
            'total_documents' => (int) $documentMetrics->total_documents,
            'pending_verification' => (int) $documentMetrics->pending_verification,
            'iar_pending_inspection' => (int) $iarMetrics->pending_inspection,
            'iar_pending_acceptance' => (int) $iarMetrics->pending_acceptance,
            'iar_coa_due' => (int) $iarMetrics->coa_due,
            'active_shipments' => (int) $shipmentMetrics->active_shipments,
            'dock_excursions' => (int) $shipmentMetrics->dock_excursions,
            'overdue_shipments' => (int) $shipmentMetrics->overdue_shipments,
        ];

        $recentShipments = Shipment::with(['purchaseOrder', 'supplier'])
            ->latest()
            ->take(6)
            ->get();

        $recentIars = InspectionAcceptanceReport::with(['purchaseOrder', 'goodsReceiptNote', 'supplier', 'inspectedBy', 'acceptedBy'])
            ->latest()
            ->take(6)
            ->get();

        $recentCustodyLogs = ChainOfCustodyLog::with(['releasingUser', 'receivingUser', 'trackable'])
            ->latest('transferred_at')
            ->take(8)
            ->get();

        $recentDocuments = LogisticsDocument::with(['uploadedBy', 'verifiedBy', 'supplier', 'purchaseOrder.supplier'])
            ->where('status', '!=', 'archived')
            ->latest()
            ->take(6)
            ->get();
        $pendingIars = InspectionAcceptanceReport::with('supplier')->whereIn('status', ['pending_inspection', 'inspected_passed'])->latest()->take(5)->get();
        $activeShipments = Shipment::with('supplier')->whereIn('status', ['dispatched', 'in_transit', 'customs_hold'])->latest()->take(5)->get();
        $excursionShipments = Shipment::with('supplier')->where('temp_excursion', true)->latest()->take(5)->get();
        $metricDetails = [
            'documents' => MetricDetails::from($recentDocuments, $metrics['total_documents'], fn (LogisticsDocument $document): string => $document->tracking_number.' · '.$document->title, 'No logistics documents registered'),
            'iars' => MetricDetails::from($pendingIars, $metrics['iar_pending_inspection'] + $metrics['iar_pending_acceptance'], fn (InspectionAcceptanceReport $iar): string => $iar->iar_number.' · '.($iar->supplier?->name ?? 'Supplier not recorded'), 'No IAR records awaiting review'),
            'shipments' => MetricDetails::from($activeShipments, $metrics['active_shipments'], fn (Shipment $shipment): string => $shipment->shipment_number.' · '.($shipment->supplier?->name ?? $shipment->carrier_name), 'No inbound shipments in transit'),
            'excursions' => MetricDetails::from($excursionShipments, $metrics['dock_excursions'], fn (Shipment $shipment): string => $shipment->shipment_number.' · '.($shipment->temp_min ?? '—').'°C to '.($shipment->temp_max ?? '—').'°C', 'No cold-chain excursions recorded'),
        ];

        return view('inventory.logistics.index', compact(
            'metrics',
            'metricDetails',
            'recentShipments',
            'recentIars',
            'recentCustodyLogs',
            'recentDocuments'
        ));
    }

    /**
     * Document Tracking Registry
     */
    public function documents(Request $request): View
    {
        $query = LogisticsDocument::with(['uploadedBy', 'verifiedBy', 'purchaseOrder', 'goodsReceiptNote', 'supplier']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('original_name', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('document_type')) {
            $query->where('document_type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $documents = $query->latest()->paginate(15)->withQueryString();

        $purchaseOrders = PurchaseOrder::latest()->take(50)->get(['id', 'po_number', 'supplier_id']);
        $goodsReceiptNotes = GoodsReceiptNote::latest()->take(50)->get(['id', 'grn_number', 'dr_number', 'sales_invoice_number']);
        $documentTypes = DocumentType::cases();

        return view('inventory.logistics.documents', compact(
            'documents',
            'purchaseOrders',
            'goodsReceiptNotes',
            'documentTypes'
        ));
    }

    /**
     * Upload Logistics Document with SHA-256 Checksum & Retention Rules
     */
    public function uploadDocument(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'document_type' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'goods_receipt_note_id' => ['nullable', 'exists:goods_receipt_notes,id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $doc = $this->documentService->uploadDocument(
                data: [
                    'document_type' => $validated['document_type'],
                    'title' => $validated['title'],
                    'reference_number' => $validated['document_number'] ?? null,
                    'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                    'goods_receipt_note_id' => $validated['goods_receipt_note_id'] ?? null,
                    'notes' => $validated['remarks'] ?? null,
                ],
                file: $request->file('file'),
                uploader: $request->user()
            );

            return redirect()->route('inventory.logistics.documents')
                ->with('success', "Document [{$doc->tracking_number}] uploaded successfully. SHA-256: ".substr($doc->sha256_checksum, 0, 12).'...');
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'The document could not be validated.';

            return redirect()->back()->withInput()->with('error', 'Document upload failed: '.$message);
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', 'Document upload failed: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', 'Document upload failed. Please verify the file and try again.');
        }
    }

    /**
     * Verify Logistics Document (Technical/Audit Validation)
     */
    public function verifyDocument(LogisticsDocument $document, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:verified,rejected'],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->documentService->verifyDocument(
                document: $document,
                decision: $validated['status'],
                notes: $validated['verification_notes'] ?? null,
                verifier: $request->user()
            );

            return redirect()->back()
                ->with('success', "Document [{$document->tracking_number}] status marked as {$validated['status']}.");
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'The document could not be verified.';

            return redirect()->back()->withInput()->with('error', 'Verification failed: '.$message);
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', 'Verification failed: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', 'Document verification could not be completed. Please try again.');
        }
    }

    /**
     * Supersede / Upload New Version of Document
     */
    public function supersedeDocument(LogisticsDocument $document, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $newDoc = $this->documentService->supersedeDocument(
                original: $document,
                data: [
                    'revision_reason' => $validated['reason'],
                ],
                newFile: $request->file('file'),
                actor: $request->user(),
                reason: $validated['reason']
            );

            return redirect()->route('inventory.logistics.documents')
                ->with('success', "Document [{$document->tracking_number}] superseded by new version [{$newDoc->tracking_number}].");
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'The replacement document could not be validated.';

            return redirect()->back()->withInput()->with('error', 'Failed to supersede document: '.$message);
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', 'Failed to supersede document: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', 'The document could not be superseded. Please verify the file and try again.');
        }
    }

    /**
     * Download Document File (Secure Local Storage Stream)
     */
    public function downloadDocument(Request $request, LogisticsDocument $document): StreamedResponse
    {
        $response = $this->documentService->downloadDocument($document);

        $this->audit->log(
            AuditAction::DownloadedLogisticsDocument,
            $request->user(),
            'Downloaded a protected logistics document.',
            $document,
            $document->tracking_number ?? $document->original_name,
            newValues: [
                'document_type' => $document->document_type?->value ?? $document->document_type,
            ],
        );

        return $response;
    }

    /**
     * Shipment & Carrier Tracking Dashboard
     */
    public function shipments(Request $request): View
    {
        $query = Shipment::with(['purchaseOrder', 'supplier', 'pickupStorageLocation', 'destinationStorageLocation', 'custodyLogs']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('shipment_number', 'like', "%{$search}%")
                    ->orWhere('carrier_name', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhere('sscc', 'like', "%{$search}%")
                    ->orWhere('pickup_location_name', 'like', "%{$search}%")
                    ->orWhere('origin_address', 'like', "%{$search}%")
                    ->orWhere('destination_facility', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->filled('cold_chain')) {
            $query->where('is_cold_chain', (bool) $request->input('cold_chain'));
        }

        $shipments = $query->latest()->paginate(15)->withQueryString();

        return view('inventory.logistics.shipments', compact('shipments'));
    }

    /**
     * Record Dock Arrival and Telemetry
     */
    public function recordDockArrival(Shipment $shipment, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'actual_delivery_date' => ['required', 'date', 'before_or_equal:today'],
            'temp_min' => ['nullable', 'numeric'],
            'temp_max' => ['nullable', 'numeric'],
            'temp_logger_serial' => ['nullable', 'string', 'max:100'],
            'package_condition' => ['required', 'in:good_order,damaged_packaging,tampered_seal,seal_intact'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'actual_delivery_date.before_or_equal' => 'The actual delivery date cannot be in the future.',
        ]);

        if ($shipment->dispatch_date
            && Carbon::parse($validated['actual_delivery_date'])->startOfDay()->lt($shipment->dispatch_date->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'actual_delivery_date' => ['The actual delivery date must be on or after the shipment date.'],
            ]);
        }

        try {
            $this->shipmentService->recordDockArrival($shipment, $validated, $request->user());

            $msg = "Shipment [{$shipment->shipment_number}] marked as arrived at dock.";
            if ($shipment->fresh()->temp_excursion) {
                return redirect()->back()->with('warning', "{$msg} WARNING: Cold-chain temperature excursion detected! Lot placed in quarantine.");
            }

            return redirect()->back()->with('success', $msg);
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', 'Dock arrival recording failed: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', 'The dock arrival could not be recorded. Please review the information and try again.');
        }
    }

    /**
     * COA Inspection and Acceptance Reports (IAR - Appendix 50) Index
     */
    public function iarIndex(Request $request): View
    {
        $query = InspectionAcceptanceReport::with([
            'goodsReceiptNote',
            'purchaseOrder',
            'supplier',
            'inspectedBy',
            'acceptedBy',
        ]);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('iar_number', 'like', "%{$search}%")
                    ->orWhere('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('purchaseOrder', fn ($po) => $po
                        ->where('po_number', 'like', "%{$search}%")
                        ->orWhere('entity_name', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($item) => $item
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('barcode_value', 'like', "%{$search}%")
                            ->orWhere('generic_name', 'like', "%{$search}%"))
                        ->orWhereHas('lines.item', fn ($item) => $item
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('barcode_value', 'like', "%{$search}%")
                            ->orWhere('generic_name', 'like', "%{$search}%")))
                    ->orWhereHas('goodsReceiptNote', fn ($grn) => $grn
                        ->where('dr_number', 'like', "%{$search}%")
                        ->orWhere('sales_invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('lines.item', fn ($item) => $item
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")
                            ->orWhere('barcode_value', 'like', "%{$search}%")
                            ->orWhere('generic_name', 'like', "%{$search}%")));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $iars = $query->latest()->paginate(15)->withQueryString();

        // Receipts without an IAR
        $unreportedReceipts = GoodsReceiptNote::whereDoesntHave('inspectionAcceptanceReport')
            ->with(['purchaseOrder', 'supplier'])
            ->latest('received_at')
            ->take(30)
            ->get();

        return view('inventory.logistics.iar_index', compact('iars', 'unreportedReceipts'));
    }

    /**
     * Generate IAR from Goods Receipt Note
     */
    public function generateIarFromReceipt(GoodsReceiptNote $goodsReceiptNote, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'dr_number' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            if (! empty($validated['dr_number'])) {
                $goodsReceiptNote->update(['dr_number' => $validated['dr_number']]);
            }

            $iar = $this->iarService->createFromReceipt(
                grn: $goodsReceiptNote,
                data: $validated,
                actor: $request->user()
            );

            return redirect()->route('inventory.logistics.iar.show', $iar)
                ->with('success', "COA GAM Appendix 50 IAR [{$iar->iar_number}] successfully generated.");
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', 'Failed to generate IAR: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', 'The IAR could not be generated. Please review the information and try again.');
        }
    }

    /**
     * View COA GAM Appendix 50 Inspection & Acceptance Report
     */
    public function iarShow(InspectionAcceptanceReport $iar): View
    {
        $this->loadIarDocumentRelations($iar);

        return view('inventory.logistics.iar_show', compact('iar'));
    }

    /**
     * Render the IAR as a standalone, print-ready institutional document.
     */
    public function iarPrint(InspectionAcceptanceReport $iar, Request $request): View
    {
        $this->loadIarDocumentRelations($iar);

        return view('inventory.logistics.iar_print', [
            'iar' => $iar,
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    /**
     * Download a data-driven PDF copy of the IAR.
     */
    public function iarDownload(InspectionAcceptanceReport $iar): Response
    {
        $this->loadIarDocumentRelations($iar);
        $pdf = DemoPdfBuilder::createInspectionAcceptanceReport($iar);
        $filename = Str::slug($iar->iar_number ?: 'inspection-acceptance-report').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($pdf),
        ]);
    }

    private function loadIarDocumentRelations(InspectionAcceptanceReport $iar): InspectionAcceptanceReport
    {
        return $iar->load([
            'goodsReceiptNote.lines.item',
            'purchaseOrder.lines.item',
            'purchaseOrder.costCenter',
            'purchaseOrder.purchaseRequest.costCenter',
            'purchaseOrder.purchaseRequest.requester',
            'supplier',
            'inspectedBy',
            'acceptedBy',
            'documents',
            'custodyLogs.releasingUser',
            'custodyLogs.receivingUser',
        ]);
    }

    /**
     * Perform Technical Inspection
     */
    public function performTechnicalInspection(InspectionAcceptanceReport $iar, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:inspected,rejected'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->iarService->performTechnicalInspection(
                iar: $iar,
                data: [
                    'inspection_status' => $validated['status'] === 'inspected' ? 'in_order' : 'rejected',
                    'inspection_findings' => $validated['remarks'],
                ],
                inspector: $request->user()
            );

            return redirect()->back()->with('success', "Technical inspection completed for IAR [{$iar->iar_number}]. Status: {$validated['status']}.");
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'The inspection could not be validated.';

            return redirect()->back()->withInput()->with('error', 'Inspection execution failed: '.$message);
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', 'Inspection execution failed: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', 'The inspection could not be completed. Please review the information and try again.');
        }
    }

    /**
     * Approve Custodial Acceptance
     */
    public function approveCustodialAcceptance(InspectionAcceptanceReport $iar, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'acceptance_type' => ['required', 'in:complete,partial'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->iarService->performCustodialAcceptance(
                iar: $iar,
                data: [
                    'delivery_status' => $validated['acceptance_type'],
                    'notes' => $validated['remarks'] ?? null,
                ],
                custodian: $request->user()
            );

            $msg = "Custodial acceptance approved for IAR [{$iar->iar_number}].";
            if ($iar->fresh()->liquidated_damages_amount > 0) {
                $msg .= ' Liquidated damages assessed: ₱'.number_format($iar->fresh()->liquidated_damages_amount, 2);
            }

            return redirect()->back()->with('success', $msg);
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', 'Acceptance approval failed: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', 'Custodial acceptance could not be approved. Please review the information and try again.');
        }
    }

    /**
     * Transmit IAR to Resident COA Auditor within 5 days
     */
    public function transmitToCoa(InspectionAcceptanceReport $iar, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'transmittal_reference' => ['required', 'string', 'max:100'],
        ]);

        try {
            $this->iarService->transmitToCoa(
                iar: $iar,
                data: [
                    'coa_received_by' => $validated['transmittal_reference'],
                ],
                officer: $request->user()
            );

            return redirect()->back()->with('success', "IAR [{$iar->iar_number}] officially transmitted to Resident COA Auditor. Ref: {$validated['transmittal_reference']}.");
        } catch (DomainException|InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', 'COA transmittal failed: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withInput()->with('error', 'The COA transmittal could not be completed. Please review the information and try again.');
        }
    }

    /**
     * Chain of Custody Audit Ledger
     */
    public function chainOfCustody(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:today'],
        ]);
        $dateFrom = filled($filters['date_from'] ?? null) ? Carbon::parse($filters['date_from'], config('app.timezone'))->startOfDay() : null;
        $dateTo = filled($filters['date_to'] ?? null) ? Carbon::parse($filters['date_to'], config('app.timezone'))->endOfDay() : null;
        $query = ChainOfCustodyLog::with(['releasingUser', 'receivingUser', 'trackable']);

        if ($action = ($filters['action'] ?? null)) {
            $query->where('event_type', $action);
        }

        if ($search = ($filters['search'] ?? null)) {
            $query->where(function ($q) use ($search) {
                $q->where('releasing_party_name', 'like', "%{$search}%")
                    ->orWhere('receiving_party_name', 'like', "%{$search}%")
                    ->orWhere('origin_location', 'like', "%{$search}%")
                    ->orWhere('destination_location', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $query->when($dateFrom, fn ($builder) => $builder->where('transferred_at', '>=', $dateFrom))
            ->when($dateTo, fn ($builder) => $builder->where('transferred_at', '<=', $dateTo));

        $logs = $query->latest('transferred_at')->paginate(25)->withQueryString();

        return view('inventory.logistics.chain_of_custody', compact('logs'));
    }

    /**
     * COA GAM Appendix 63 Requisition and Issue Slip (RIS) Print View
     */
    public function risShow(MaterialRequisition $requisition): View
    {
        $requisition->load([
            'lines.item',
            'requestingUser',
            'approvedBy',
            'issuedBy',
            'acknowledgedBy',
            'costCenter',
        ]);

        return view('inventory.logistics.ris_show', compact('requisition'));
    }
}
