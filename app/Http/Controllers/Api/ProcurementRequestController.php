<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcurementRequestRequest;
use App\Http\Requests\UpdateProcurementRequestRequest;
use App\Http\Resources\ProcurementRequestResource;
use App\Models\ProcurementRequest;
use Illuminate\Http\Request;

class ProcurementRequestController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = ProcurementRequest::with(['item', 'supplier'])->paginate($perPage);

        return ProcurementRequestResource::collection($items);
    }

    public function show(ProcurementRequest $procurement_request)
    {
        return new ProcurementRequestResource($procurement_request->load(['item', 'supplier']));
    }

    public function store(StoreProcurementRequestRequest $request)
    {
        $data = $request->validated();
        $pr = ProcurementRequest::create($data);

        return (new ProcurementRequestResource($pr))->response()->setStatusCode(201);
    }

    public function update(UpdateProcurementRequestRequest $request, ProcurementRequest $procurement_request)
    {
        $procurement_request->update($request->validated());

        return new ProcurementRequestResource($procurement_request);
    }

    public function destroy(ProcurementRequest $procurement_request)
    {
        $procurement_request->delete();

        return response()->json(null, 204);
    }
}
