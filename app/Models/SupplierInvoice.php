<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends Model
{
    protected $fillable = ['supplier_id', 'purchase_order_id', 'invoice_number', 'invoice_date', 'total_amount', 'status', 'match_notes', 'submitted_by', 'submitted_at'];
    protected function casts(): array { return ['invoice_date' => 'date', 'total_amount' => 'decimal:2', 'submitted_at' => 'datetime']; }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function lines(): HasMany { return $this->hasMany(SupplierInvoiceLine::class); }
}
