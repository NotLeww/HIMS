<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // The default seeder provisions only explicitly configured owner accounts.
        // Synthetic operational records must never be added to a normal database seed.
        $this->call(SuperAdminSeeder::class);
        $this->call(OwnerAdminSeeder::class);
    }
}
