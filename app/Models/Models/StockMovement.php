<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'item_id',
        'movement_type',
        'quantity',
        'from_location_id',
        'to_location_id',
        'remarks',
        'moved_at',
        'user_id',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function fromLocation()
    {
        return $this->belongsTo(StorageLocation::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(StorageLocation::class, 'to_location_id');
    }
}
