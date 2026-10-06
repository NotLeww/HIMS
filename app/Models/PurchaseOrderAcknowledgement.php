<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderAcknowledgement extends Model
{
    protected $fillable = ['purchase_order_id', 'supplier_id', 'responded_by', 'response', 'exception_type', 'message', 'responded_at'];

    protected function casts(): array { return ['responded_at' => 'datetime']; }

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function responder(): BelongsTo { return $this->belongsTo(User::class, 'responded_by'); }
}
