<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        return view('inventory.index');
    }

    public function suppliers(): View
    {
        return view('inventory.suppliers.index');
    }

    public function purchases(): View
    {
        return view('inventory.purchases.index');
    }

    public function stock(): View
    {
        return view('inventory.stock.index');
    }

    public function alerts(): View
    {
        return view('inventory.alerts.index');
    }

    public function reports(): View
    {
        return view('inventory.reports.index');
    }
}
