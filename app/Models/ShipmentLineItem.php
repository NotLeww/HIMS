<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentLineItem extends Model
{
    protected $fillable = ['shipment_id', 'po_line_id', 'quantity', 'lot_number', 'serial_number', 'expiry_date'];
    protected function casts(): array { return ['quantity' => 'integer', 'expiry_date' => 'date']; }
    public function shipment(): BelongsTo { return $this->belongsTo(Shipment::class); }
    public function purchaseOrderLine(): BelongsTo { return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id'); }
}
