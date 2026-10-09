<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class EmptyStateArtworkTest extends TestCase
{
    public function test_every_empty_state_category_renders_its_generated_artwork(): void
    {
        $categories = [
            'compliance' => 'empty-compliance.png',
            'finance' => 'empty-finance.png',
            'governance' => 'empty-governance.png',
            'inventory' => 'empty-inventory.png',
            'logistics' => 'empty-logistics.png',
            'narcotics' => 'empty-narcotics.png',
            'procurement' => 'empty-procurement.png',
            'receiving-discrepancies' => 'empty-receiving-discrepancies.png',
            'receiving' => 'empty-receiving.png',
            'reports' => 'empty-reports.png',
            'suppliers' => 'empty-suppliers.png',
            'users' => 'empty-users.png',
            'warehouse' => 'empty-warehouse.png',
        ];

        foreach ($categories as $category => $filename) {
            $html = Blade::render('<x-ui.empty-artwork category="'.$category.'" />');

            $this->assertFileExists(public_path('img/'.$filename));
            $this->assertStringContainsString('img/'.$filename, $html);
            $this->assertStringContainsString('data-empty-artwork="'.$category.'"', $html);
            $this->assertStringContainsString('data-empty-surface-artwork', $html);
        }
    }

    public function test_table_and_full_width_empty_states_share_the_curved_surface(): void
    {
        $table = Blade::render('<table><tbody><x-ui.table.empty artwork="compliance" title="No documents" /></tbody></table>');
        $fullWidth = Blade::render('<x-ui.empty-state artwork="receiving" title="No receipts" message="Receipts will appear here." />');
        $compactArtwork = Blade::render('<x-ui.empty-artwork category="warehouse" size="sm" />');

        $this->assertStringContainsString('hims-empty-surface', $table);
        $this->assertStringContainsString('hims-empty-surface', $fullWidth);
        $this->assertStringContainsString('h-32 w-32 sm:h-36 sm:w-36', $table);
        $this->assertStringContainsString('h-32 w-32 sm:h-36 sm:w-36', $compactArtwork);
        $this->assertStringContainsString('text-xl font-bold', $table);
    }
}
