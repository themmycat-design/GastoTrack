<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\StockItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $business = Business::first();

        if (!$business) {
            $this->command->error('No business found. Run BusinessSeeder first.');
            return;
        }

        // Create stock items
        $milkTea = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Black Tea Leaves',
            'unit' => 'kg',
            'current_quantity' => 50,
            'minimum_quantity' => 10,
            'unit_cost' => 500,
            'active' => true,
        ]);

        $milk = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Fresh Milk',
            'unit' => 'liters',
            'current_quantity' => 30,
            'minimum_quantity' => 5,
            'unit_cost' => 80,
            'active' => true,
        ]);

        $brownSugar = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Brown Sugar',
            'unit' => 'kg',
            'current_quantity' => 25,
            'minimum_quantity' => 5,
            'unit_cost' => 60,
            'active' => true,
        ]);

        $matchaPowder = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Matcha Powder',
            'unit' => 'kg',
            'current_quantity' => 15,
            'minimum_quantity' => 3,
            'unit_cost' => 800,
            'active' => true,
        ]);

        $tapioca = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Tapioca Pearls',
            'unit' => 'kg',
            'current_quantity' => 20,
            'minimum_quantity' => 5,
            'unit_cost' => 150,
            'active' => true,
        ]);

        $cups = StockItem::create([
            'business_id' => $business->id,
            'name' => 'Plastic Cups (Large)',
            'unit' => 'pieces',
            'current_quantity' => 500,
            'minimum_quantity' => 100,
            'unit_cost' => 3,
            'active' => true,
        ]);

        // Create products
        $brownSugarMilkTea = Product::create([
            'business_id' => $business->id,
            'name' => 'Brown Sugar Milk Tea',
            'description' => 'Classic brown sugar milk tea with chewy tapioca pearls',
            'category' => 'Milk Tea',
            'price' => 85,
            'active' => true,
            'prep_time' => 5,
        ]);

        // Link ingredients to Brown Sugar Milk Tea
        $brownSugarMilkTea->ingredients()->attach([
            $milkTea->id => ['quantity' => 0.015], // 15g
            $milk->id => ['quantity' => 0.15], // 150ml
            $brownSugar->id => ['quantity' => 0.03], // 30g
            $tapioca->id => ['quantity' => 0.05], // 50g
            $cups->id => ['quantity' => 1],
        ]);

        $matchaLatte = Product::create([
            'business_id' => $business->id,
            'name' => 'Matcha Latte',
            'description' => 'Smooth and creamy matcha latte made with premium matcha powder',
            'category' => 'Coffee & Tea',
            'price' => 95,
            'active' => true,
            'prep_time' => 4,
        ]);

        $matchaLatte->ingredients()->attach([
            $matchaPowder->id => ['quantity' => 0.008], // 8g
            $milk->id => ['quantity' => 0.2], // 200ml
            $cups->id => ['quantity' => 1],
        ]);

        $classicMilkTea = Product::create([
            'business_id' => $business->id,
            'name' => 'Classic Milk Tea',
            'description' => 'Traditional milk tea with pearls',
            'category' => 'Milk Tea',
            'price' => 75,
            'active' => true,
            'prep_time' => 5,
        ]);

        $classicMilkTea->ingredients()->attach([
            $milkTea->id => ['quantity' => 0.015], // 15g
            $milk->id => ['quantity' => 0.15], // 150ml
            $tapioca->id => ['quantity' => 0.05], // 50g
            $cups->id => ['quantity' => 1],
        ]);

        $wintermelon = Product::create([
            'business_id' => $business->id,
            'name' => 'Wintermelon Milk Tea',
            'description' => 'Refreshing wintermelon flavored milk tea',
            'category' => 'Milk Tea',
            'price' => 80,
            'active' => true,
            'prep_time' => 5,
        ]);

        $wintermelon->ingredients()->attach([
            $milkTea->id => ['quantity' => 0.015], // 15g
            $milk->id => ['quantity' => 0.15], // 150ml
            $tapioca->id => ['quantity' => 0.05], // 50g
            $cups->id => ['quantity' => 1],
        ]);

        $thaimilktea = Product::create([
            'business_id' => $business->id,
            'name' => 'Thai Milk Tea',
            'description' => 'Authentic Thai-style milk tea',
            'category' => 'Milk Tea',
            'price' => 85,
            'active' => true,
            'prep_time' => 5,
        ]);

        $thaimilktea->ingredients()->attach([
            $milkTea->id => ['quantity' => 0.015], // 15g
            $milk->id => ['quantity' => 0.15], // 150ml
            $tapioca->id => ['quantity' => 0.05], // 50g
            $cups->id => ['quantity' => 1],
        ]);

        $this->command->info('Stock items and products created successfully!');
        $this->command->info('- 6 stock items created');
        $this->command->info('- 5 products created with ingredients linked');
    }
}
