<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierDiscrepancy extends Model
{
    protected $fillable = ['supplier_id', 'grn_line_item_id', 'status', 'supplier_response_type', 'supplier_response', 'responded_by', 'responded_at', 'resolution', 'resolved_by', 'resolved_at'];
    protected function casts(): array { return ['responded_at' => 'datetime', 'resolved_at' => 'datetime']; }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function receiptLine(): BelongsTo { return $this->belongsTo(GoodsReceiptNoteLine::class, 'grn_line_item_id'); }
}
