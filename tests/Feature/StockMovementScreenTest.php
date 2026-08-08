<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The movement screen used to swallow every rejection: recordMovement() throws a
 * ValidationException for a missing location or short stock, and the view had no
 * error block, so a failed submission looked identical to nothing happening.
 * These tests pin the feedback, not just the happy path.
 */
class StockMovementScreenTest extends TestCase
{
    use RefreshDatabase;

    private function stockedItem(int $quantity = 50): array
    {
        $location = StorageLocation::create(['name' => 'Zone A', 'code' => 'WH-A', 'status' => 'active']);
        $item = InventoryItem::create([
            'name' => 'Sterile Gauze',
            'sku' => 'GAUZE-01',
            'quantity_on_hand' => $quantity,
            'reorder_level' => 5,
            'unit_cost' => 3,
            'total_value' => $quantity * 3,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'storage_location_id' => $location->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);

        return [$item, $location];
    }

    public function test_stock_in_without_a_destination_shows_an_error_instead_of_failing_silently(): void
    {
        [$item] = $this->stockedItem();
        $user = User::factory()->warehouseStaff()->create();

        $response = $this->actingAs($user)->from('/inventory/stock-movements')->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_in',
            'quantity' => 10,
            'from_location_id' => '',
            'to_location_id' => '',
        ]);

        $response->assertSessionHasErrors('to_location_id');
        $this->assertSame(0, StockMovement::count());

        // The screen has to actually render it, which is the part that was missing.
        $this->actingAs($user)->get('/inventory/stock-movements')
            ->assertSee('This movement was not recorded')
            ->assertSee('A destination location is required for a Stock In.');
    }

    public function test_stock_out_beyond_available_quantity_shows_the_shortfall(): void
    {
        [$item, $location] = $this->stockedItem(8);
        $user = User::factory()->warehouseStaff()->create();

        $this->actingAs($user)->from('/inventory/stock-movements')->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 20,
            'from_location_id' => $location->id,
        ])->assertSessionHasErrors('quantity');

        $this->actingAs($user)->get('/inventory/stock-movements')
            ->assertSee('Short by 12.');

        $this->assertSame(0, StockMovement::count());
        $this->assertSame(8, $item->fresh()->quantity_on_hand);
    }

    public function test_successful_movement_reports_what_was_recorded_and_lists_it(): void
    {
        [$item, $location] = $this->stockedItem();
        $user = User::factory()->warehouseStaff()->create();

        $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 15,
            'from_location_id' => $location->id,
            'remarks' => 'Issued to Ward 3',
        ])->assertRedirect('/inventory/stock-movements')
            ->assertSessionHas('success', 'Stock Out of 15 recorded.');

        $response = $this->actingAs($user)->get('/inventory/stock-movements');

        $response->assertSee('Sterile Gauze');
        $response->assertSee('Stock Out');
        $response->assertSee('Zone A');
        $this->assertSame(35, $item->fresh()->quantity_on_hand);
    }

    public function test_api_movement_updates_balances_rather_than_only_logging_a_row(): void
    {
        [$item, $location] = $this->stockedItem(40);
        $user = User::factory()->warehouseStaff()->create();

        $this->actingAs($user)->postJson('/api/v1/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 10,
            'from_location_id' => $location->id,
        ])->assertStatus(201);

        // The old implementation wrote the movement row and stopped there.
        $this->assertSame(30, $item->fresh()->quantity_on_hand);
        $this->assertSame(30, (int) ItemStockLevel::where('item_id', $item->id)->sum('quantity'));
    }

    public function test_api_rejects_a_movement_type_that_is_not_a_real_enum_case(): void
    {
        [$item, $location] = $this->stockedItem();
        $user = User::factory()->warehouseStaff()->create();

        // 'in' and 'out' were accepted before; the resulting rows threw a
        // ValueError on the enum cast and took the whole history table down.
        foreach (['in', 'out'] as $bogus) {
            $this->actingAs($user)->postJson('/api/v1/stock-movements', [
                'item_id' => $item->id,
                'movement_type' => $bogus,
                'quantity' => 5,
                'from_location_id' => $location->id,
            ])->assertStatus(422)->assertJsonValidationErrors('movement_type');
        }

        $this->assertSame(0, StockMovement::count());
    }

    public function test_history_lists_the_newest_movement_first(): void
    {
        [$item, $location] = $this->stockedItem(100);
        $user = User::factory()->warehouseStaff()->create();

        foreach ([1, 2, 3] as $quantity) {
            $this->actingAs($user)->post('/inventory/stock-movements', [
                'item_id' => $item->id,
                'movement_type' => 'stock_out',
                'quantity' => $quantity,
                'from_location_id' => $location->id,
                'remarks' => "movement-{$quantity}",
            ]);
        }

        $ids = StockMovement::latest('moved_at')->latest('id')->pluck('id')->all();
        $this->assertSame([3, 2, 1], $ids, 'Newest movement should sort first.');
    }
}
