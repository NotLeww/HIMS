<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStorageLocationRequest;
use App\Http\Requests\UpdateStorageLocationRequest;
use App\Http\Resources\StorageLocationResource;
use App\Models\StorageLocation;
use Illuminate\Http\Request;

class StorageLocationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);
        $items = StorageLocation::query()->paginate($perPage);

        return StorageLocationResource::collection($items);
    }

    public function show(StorageLocation $storage_location)
    {
        return new StorageLocationResource($storage_location);
    }

    public function store(StoreStorageLocationRequest $request)
    {
        $data = $request->validated();
        $loc = StorageLocation::create($data);

        return (new StorageLocationResource($loc))->response()->setStatusCode(201);
    }

    public function update(UpdateStorageLocationRequest $request, StorageLocation $storage_location)
    {
        $storage_location->update($request->validated());

        return new StorageLocationResource($storage_location);
    }

    public function destroy(StorageLocation $storage_location)
    {
        $storage_location->delete();

        return response()->json(null, 204);
    }
}
