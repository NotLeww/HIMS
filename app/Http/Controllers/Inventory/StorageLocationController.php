<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StorageLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorageLocationController extends Controller
{
    public function index(): View
    {
        $locations = StorageLocation::latest()->get();

        return view('inventory.storage_locations.index', compact('locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:storage_locations'],
            'description' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        StorageLocation::create($validated);

        return redirect()->route('inventory.storage-locations')->with('success', 'Storage location created successfully.');
    }
}
