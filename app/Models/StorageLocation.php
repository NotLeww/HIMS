<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'parent_id',
        'type',
        'description',
        'zone',
        'capacity',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(ItemStockLevel::class, 'storage_location_id');
    }

    /**
     * Full location path, e.g. "Zone A / Aisle 3 / Rack 2 / Bin 04".
     */
    public function fullPath(): string
    {
        $segments = [$this->name];
        $node = $this->parent;

        while ($node !== null) {
            array_unshift($segments, $node->name);
            $node = $node->parent;
        }

        return implode(' / ', $segments);
    }

    /**
     * Total units held directly in this location.
     */
    public function totalQuantity(): int
    {
        return (int) $this->stockLevels()->sum('quantity');
    }

    /**
     * Percentage of capacity used, or null when no capacity is configured.
     */
    public function utilisation(): ?float
    {
        if (! $this->capacity) {
            return null;
        }

        return round(($this->totalQuantity() / $this->capacity) * 100, 1);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }
}
