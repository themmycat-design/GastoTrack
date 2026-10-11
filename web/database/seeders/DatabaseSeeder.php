<?php

namespace Database\Seeders;

use App\Models\User;
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
        $this->call(SuperAdminSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo business, staff, products, orders, and financial records were skipped outside local/testing.');
            return;
        }

        $this->call([
            BusinessSeeder::class,
            ProductDataSeeder::class,
        ]);
    }
}
