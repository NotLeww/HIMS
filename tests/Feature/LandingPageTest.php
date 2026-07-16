<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_homepage_displays_supply_chain_and_inventory_management_heading(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Supply Chain');
        $response->assertSee('Inventory Management');
    }
}
