<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'category',
        'unit',
        'quantity_on_hand',
        'reserved_quantity',
        'reorder_level',
        'unit_cost',
        'total_value',
        'supplier_id',
        'warehouse_name',
        'batch_number',
        'expiry_date',
        'status',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
