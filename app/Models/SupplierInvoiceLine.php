<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceLine extends Model
{
    protected $fillable = ['supplier_invoice_id', 'po_line_id', 'quantity', 'unit_price', 'line_total', 'match_status'];
    protected function casts(): array { return ['quantity' => 'integer', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2']; }
    public function invoice(): BelongsTo { return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id'); }
    public function purchaseOrderLine(): BelongsTo { return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id'); }
}
