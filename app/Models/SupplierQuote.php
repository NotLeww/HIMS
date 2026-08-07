<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierQuote extends Model
{
    protected $fillable = [
        'procurement_request_id',
        'supplier_id',
        'quoted_price',
        'status',
        'notes',
    ];

    public function procurementRequest()
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
