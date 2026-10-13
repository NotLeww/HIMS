<?php

namespace App\Http\Controllers\Api;

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
            $quote = SupplierQuote::updateOrCreate(
                [
                    'procurement_request_id' => $data['procurement_request_id'],
                    'supplier_id' => $data['supplier_id'],
                ],
                collect($data)->except(['procurement_request_id', 'supplier_id'])->all(),
            );

            $this->auditService->record(
                $request->user(),
                'SupplierQuote',
                $quote->id,
                'submitted_supplier_quote',
                null,
                ['supplier_id' => $quote->supplier_id, 'quoted_price' => $quote->quoted_price]
            );

            return $quote;
        });

        return (new SupplierQuoteResource($sq))->response()->setStatusCode(201);
    }

    public function update(UpdateSupplierQuoteRequest $request, SupplierQuote $supplier_quote)
    {
        $supplier_quote->update($request->validated());

        return new SupplierQuoteResource($supplier_quote);
    }
}
