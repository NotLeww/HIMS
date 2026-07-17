<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Model;

class DemandPlan extends Model
{
    protected $fillable = [
        'plan_number',
        'item_id',
        'current_stock',
        'historical_usage',
        'upcoming_need',
        'reorder_point',
        'trigger_reason',
        'status',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }
}
