<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcurementRequest extends Model
{
    protected $fillable = [
        'request_number',
        'title',
        'description',
        'item_id',
        'requested_quantity',
        'priority',
        'status',
        'requested_at',
        'supplier_id',
        'approved_by',
        'approval_notes',
        'evaluation_score',
        'evaluation_status',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
