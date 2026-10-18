<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierQuoteRequest;
use App\Http\Requests\UpdateSupplierQuoteRequest;
use App\Http\Resources\SupplierQuoteResource;
use App\Models\ProcurementRequest;
use App\Models\SupplierQuote;
use App\Services\Procurement\ProcurementAuditService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class SupplierQuoteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.Permission::ViewProcurementSensitiveData->value, only: ['index', 'show']),
            new Middleware('can:'.Permission::ManageSourcing->value, only: ['store', 'update']),
        ];
    }

    public function __construct(private readonly ProcurementAuditService $auditService) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = SupplierQuote::with(['supplier', 'procurementRequest'])->paginate(min(max($perPage, 1), 100));

        return SupplierQuoteResource::collection($items);
    }

    public function show(SupplierQuote $supplier_quote)
    {
        return new SupplierQuoteResource($supplier_quote->load(['supplier', 'procurementRequest']));
    }

    public function store(StoreSupplierQuoteRequest $request)
    {
        $data = $request->validated();
        $sq = DB::transaction(function () use ($data, $request): SupplierQuote {
            ProcurementRequest::query()->lockForUpdate()->findOrFail($data['procurement_request_id']);
            $quote = SupplierQuote::firstOrNew([
                'procurement_request_id' => $data['procurement_request_id'],
                'supplier_id' => $data['supplier_id'],
            ]);
            $wasRevision = $quote->exists;
            $oldValues = $wasRevision ? $quote->only(['supplier_id', 'quoted_price', 'status']) : null;
            $quote->fill(collect($data)->except(['procurement_request_id', 'supplier_id'])->all())->save();

            $this->auditService->record(
                $request->user(),
                'SupplierQuote',
                $quote->id,
                ($wasRevision ? AuditAction::RevisedSupplierQuote : AuditAction::SubmittedSupplierQuote)->value,
                $oldValues,
                ['supplier_id' => $quote->supplier_id, 'quoted_price' => $quote->quoted_price]
            );

            return $quote;
        });

        return (new SupplierQuoteResource($sq))->response()->setStatusCode(201);
    }

    public function update(UpdateSupplierQuoteRequest $request, SupplierQuote $supplier_quote)
    {
        $oldValues = $supplier_quote->only(['supplier_id', 'quoted_price', 'status']);
        $supplier_quote->update($request->validated());

        if ($supplier_quote->wasChanged()) {
            $this->auditService->record(
                $request->user(),
                'SupplierQuote',
                $supplier_quote->id,
                AuditAction::RevisedSupplierQuote->value,
                $oldValues,
                $supplier_quote->only(['supplier_id', 'quoted_price', 'status']),
            );
        }

        return new SupplierQuoteResource($supplier_quote);
    }
}
