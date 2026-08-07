<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = PurchaseOrder::with(['supplier', 'item'])->paginate($perPage);

        return PurchaseOrderResource::collection($items);
    }

    public function show(PurchaseOrder $purchase_order)
    {
        return new PurchaseOrderResource($purchase_order);
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $data = $request->validated();
        $po = PurchaseOrder::create($data);

        return (new PurchaseOrderResource($po))->response()->setStatusCode(201);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        $purchase_order->update($request->validated());

        return new PurchaseOrderResource($purchase_order);
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        $purchase_order->delete();

        return response()->json(null, 204);
    }
}
