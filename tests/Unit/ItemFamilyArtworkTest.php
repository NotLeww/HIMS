<?php

namespace Tests\Unit;

use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Support\ItemFamilyArtwork;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ItemFamilyArtworkTest extends TestCase
{
    #[DataProvider('itemFamilies')]
    public function test_it_selects_artwork_for_each_item_family(string $name, string $category, string $expected): void
    {
        $item = new InventoryItem(['name' => $name]);
        $item->setRelation('category', new ItemCategory(['name' => $category]));

        $this->assertSame($expected, ItemFamilyArtwork::filename([$item]));
    }

    public function test_mixed_item_families_use_the_neutral_supplies_artwork(): void
    {
        $this->assertSame('picklist-supplies.png', ItemFamilyArtwork::filename([
            new InventoryItem(['name' => 'Paracetamol 500 mg Tablet']),
            new InventoryItem(['name' => 'ECG Patient Monitor']),
        ]));
    }

    public static function itemFamilies(): array
    {
        return [
            'pharmaceuticals' => ['Dobutamine vial', 'Clinical Pharmaceuticals', 'picklist-pharmaceuticals.png'],
            'ppe' => ['Examination gloves', 'Personal Protective Equipment', 'picklist-ppe.png'],
            'diagnostics' => ['Glucose test strip', 'Laboratory Diagnostics', 'picklist-diagnostics.png'],
            'devices' => ['Infusion pump', 'Medical Equipment', 'picklist-devices.png'],
            'supplies' => ['Sterile gauze pads', 'Medical Consumables', 'picklist-supplies.png'],
        ];
    }
}
