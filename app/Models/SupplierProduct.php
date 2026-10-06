<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProduct extends Model
{
    protected $fillable = ['supplier_id', 'item_id', 'supplier_sku', 'gtin', 'supplier_product_name', 'manufacturer', 'brand', 'pack_size', 'unit', 'minimum_order_quantity', 'lead_time_days', 'is_preferred', 'is_active', 'approval_status', 'vmi_enabled', 'vmi_min', 'vmi_max'];

    protected function casts(): array
    {
        return ['minimum_order_quantity' => 'integer', 'lead_time_days' => 'integer', 'is_preferred' => 'boolean', 'is_active' => 'boolean', 'vmi_enabled' => 'boolean', 'vmi_min' => 'integer', 'vmi_max' => 'integer'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(SupplierPrice::class);
    }
}
