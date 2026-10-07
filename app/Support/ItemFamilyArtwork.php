<?php

namespace App\Support;

use App\Models\InventoryItem;
use Illuminate\Support\Str;

final class ItemFamilyArtwork
{
    public static function filename(iterable $items): string
    {
        $families = collect($items)
            ->filter()
            ->map(fn (InventoryItem $item): string => self::family($item))
            ->unique();

        return $families->count() === 1
            ? 'picklist-'.$families->first().'.png'
            : 'picklist-supplies.png';
    }

    private static function family(InventoryItem $item): string
    {
        $searchable = Str::lower(collect([
            $item->name,
            $item->sku,
            $item->generic_name,
            $item->brand_name,
            $item->dosage_form_strength,
            $item->regulatory_category,
            $item->relationLoaded('category') ? $item->category?->name : null,
            $item->relationLoaded('category') ? $item->category?->code : null,
        ])->filter()->implode(' '));

        return match (true) {
            Str::contains($searchable, ['ppe', 'protective', 'glove', 'respirator', 'face mask', 'gown']) => 'ppe',
            Str::contains($searchable, ['diagnostic', 'laboratory', 'test kit', 'test strip', 'reagent']) => 'diagnostics',
            Str::contains($searchable, ['device', 'equipment', 'implant', 'surgical', 'instrument', 'monitor', 'pump']) => 'devices',
            Str::contains($searchable, ['pharma', 'drug', 'medicine', 'medication', 'tablet', 'capsule', 'caplet', 'pill', 'vial', 'ampoule', 'injectable']) => 'pharmaceuticals',
            default => 'supplies',
        };
    }
}
