<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Tests\TestCase;

class DemoSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_demo_seeders_are_repeatable_and_create_consistent_sample_sales(): void
    {
        config(['app.env' => 'testing']);
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('businesses', 1);
        $this->assertSame(3, User::whereIn('email', [
            'owner@gastotrack.com', 'staff1@gastotrack.com', 'staff2@gastotrack.com',
        ])->count());
        $this->assertDatabaseCount('stock_items', 6);
        $this->assertDatabaseCount('products', 5);
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('transactions', 3);
        $this->assertDatabaseCount('order_payment_events', 2);
        $this->assertDatabaseHas('orders', ['order_number' => '1001', 'status' => 'completed', 'payment_status' => 'confirmed']);
        $this->assertDatabaseHas('orders', ['order_number' => '1002', 'status' => 'completed', 'payment_status' => 'confirmed']);
        $this->assertDatabaseHas('transactions', ['category' => 'Utilities', 'type' => 'expense', 'amount' => 1250]);
        $this->assertDatabaseCount('stock_movements', 7);
    }

    public function test_demo_business_and_financial_data_are_skipped_in_production(): void
    {
        config(['app.env' => 'production']);
        $this->seed();

        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseMissing('users', ['email' => 'owner@gastotrack.com']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('transactions', 0);
    }
}
