<?php

namespace App\Models;

use App\Enums\DemandTrend;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved demand forecast for one item.
 *
 * The numbers are a snapshot taken when the plan was generated, not a live
 * view — the movement history they came from keeps changing, so re-deriving
 * them later would not reproduce what an order was actually based on.
 * DemandForecastService is what fills them in.
 */
class DemandPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_number',
        'item_id',
        'analysis_days',
        'forecast_days',
        'lead_time_days',
        'current_stock',
        'historical_usage',
        'average_daily_usage',
        'upcoming_need',
        'reorder_point',
        'safety_stock',
        'suggested_order_quantity',
        'days_of_cover',
        'trend',
        'trigger_reason',
        'generated_by',
        'generated_at',
        'notes',
        'status',
    ];

    protected $casts = [
        'analysis_days' => 'integer',
        'forecast_days' => 'integer',
        'lead_time_days' => 'integer',
        'current_stock' => 'integer',
        'historical_usage' => 'integer',
        'average_daily_usage' => 'decimal:3',
        'upcoming_need' => 'integer',
        'reorder_point' => 'integer',
        'safety_stock' => 'integer',
        'suggested_order_quantity' => 'integer',
        'days_of_cover' => 'integer',
        'trend' => DemandTrend::class,
        'generated_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Whether the plan called for an order at the time it was generated.
     */
    public function recommendsOrder(): bool
    {
        return (int) $this->suggested_order_quantity > 0;
    }

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeRecommendingOrder($query)
    {
        return $query->where('suggested_order_quantity', '>', 0);
    }
}
