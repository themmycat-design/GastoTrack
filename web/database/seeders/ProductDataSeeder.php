<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderPaymentEvent;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo products and financial records are available only in local/testing environments.');
            return;
        }

        $business = Business::where('email', 'info@gastotrack-milktea.com')->first();
        if (! $business) {
            $this->command?->error('The local demo business is missing. Run BusinessSeeder first.');
            return;
        }

        $stockData = [
            'Black Tea Leaves' => ['unit' => 'kg', 'current_quantity' => 50, 'minimum_quantity' => 10, 'unit_cost' => 500],
            'Fresh Milk' => ['unit' => 'liters', 'current_quantity' => 30, 'minimum_quantity' => 5, 'unit_cost' => 80],
            'Brown Sugar' => ['unit' => 'kg', 'current_quantity' => 25, 'minimum_quantity' => 5, 'unit_cost' => 60],
            'Matcha Powder' => ['unit' => 'kg', 'current_quantity' => 15, 'minimum_quantity' => 3, 'unit_cost' => 800],
            'Tapioca Pearls' => ['unit' => 'kg', 'current_quantity' => 20, 'minimum_quantity' => 5, 'unit_cost' => 150],
            'Plastic Cups (Large)' => ['unit' => 'pieces', 'current_quantity' => 500, 'minimum_quantity' => 100, 'unit_cost' => 3],
        ];
        $stock = [];
        foreach ($stockData as $name => $attributes) {
            // firstOrCreate preserves live stock counts on repeated db:seed runs.
            $stock[$name] = StockItem::firstOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                $attributes + ['active' => true]
            );
        }

        $productData = [
            'Brown Sugar Milk Tea' => [
                'description' => 'Classic brown sugar milk tea with chewy tapioca pearls',
                'category' => 'Milk Tea', 'price' => 85, 'prep_time' => 5,
                'ingredients' => ['Black Tea Leaves' => 0.015, 'Fresh Milk' => 0.15, 'Brown Sugar' => 0.03, 'Tapioca Pearls' => 0.05, 'Plastic Cups (Large)' => 1],
            ],
            'Matcha Latte' => [
                'description' => 'Smooth and creamy matcha latte made with premium matcha powder',
                'category' => 'Coffee & Tea', 'price' => 95, 'prep_time' => 4,
                'ingredients' => ['Matcha Powder' => 0.008, 'Fresh Milk' => 0.2, 'Plastic Cups (Large)' => 1],
            ],
            'Classic Milk Tea' => [
                'description' => 'Traditional milk tea with pearls',
                'category' => 'Milk Tea', 'price' => 75, 'prep_time' => 5,
                'ingredients' => ['Black Tea Leaves' => 0.015, 'Fresh Milk' => 0.15, 'Tapioca Pearls' => 0.05, 'Plastic Cups (Large)' => 1],
            ],
            'Wintermelon Milk Tea' => [
                'description' => 'Refreshing wintermelon flavored milk tea',
                'category' => 'Milk Tea', 'price' => 80, 'prep_time' => 5,
                'ingredients' => ['Black Tea Leaves' => 0.015, 'Fresh Milk' => 0.15, 'Tapioca Pearls' => 0.05, 'Plastic Cups (Large)' => 1],
            ],
            'Thai Milk Tea' => [
                'description' => 'Authentic Thai-style milk tea',
                'category' => 'Milk Tea', 'price' => 85, 'prep_time' => 5,
                'ingredients' => ['Black Tea Leaves' => 0.015, 'Fresh Milk' => 0.15, 'Tapioca Pearls' => 0.05, 'Plastic Cups (Large)' => 1],
            ],
        ];

        $products = [];
        foreach ($productData as $name => $attributes) {
            $ingredients = $attributes['ingredients'];
            unset($attributes['ingredients']);
            $product = Product::updateOrCreate(
                ['business_id' => $business->id, 'name' => $name],
                $attributes + ['active' => true, 'is_available' => true]
            );
            $product->ingredients()->sync(collect($ingredients)->mapWithKeys(
                fn (float $quantity, string $stockName): array => [$stock[$stockName]->id => ['quantity' => $quantity]]
            )->all());
            $products[$name] = $product->load('productIngredients.stockItem');
        }

        $staff = [
            'staff1@gastotrack.com' => User::where('email', 'staff1@gastotrack.com')->first(),
            'staff2@gastotrack.com' => User::where('email', 'staff2@gastotrack.com')->first(),
        ];
        $this->seedCompletedOrder($business, $staff['staff1@gastotrack.com'], $products['Classic Milk Tea'], 2,
            'a7a690a3-9af3-4680-a573-ec058d190001', 'DEMO-ORDER-001', now()->subHours(2));
        $this->seedCompletedOrder($business, $staff['staff2@gastotrack.com'], $products['Matcha Latte'], 1,
            'a7a690a3-9af3-4680-a573-ec058d190002', 'DEMO-ORDER-002', now()->subDay());

        Transaction::updateOrCreate(
            ['client_id' => 'a7a690a3-9af3-4680-a573-ec058d190003'],
            [
                'business_id' => $business->id,
                'user_id' => $staff['staff1@gastotrack.com']?->id,
                'amount' => 1250,
                'type' => 'expense',
                'source' => 'Cash',
                'category' => 'Utilities',
                'transaction_date' => now()->subDay()->toDateString(),
                'description' => 'Local demo electricity bill',
                'entry_method' => 'manual',
                'metadata' => ['seeded_demo_record' => true],
                'synced' => true,
            ]
        );

        $this->command?->info('Repeatable local products, stock, completed orders, sales, and an expense are ready.');
    }

    private function seedCompletedOrder(
        Business $business,
        ?User $staff,
        Product $product,
        int $quantity,
        string $clientId,
        string $orderNumber,
        $soldAt
    ): void {
        if (! $staff) {
            $this->command?->warn('A demo staff account is missing; skipped sample order '.$orderNumber.'.');
            return;
        }

        DB::transaction(function () use ($business, $staff, $product, $quantity, $clientId, $orderNumber, $soldAt): void {
            $order = Order::where('client_id', $clientId)->lockForUpdate()->first();
            $isNew = ! $order;
            $total = number_format((float) $product->price * $quantity, 2, '.', '');

            if ($isNew) {
                $order = Order::create([
                    'business_id' => $business->id,
                    'user_id' => $staff->id,
                    'order_number' => $orderNumber,
                    'customer_name' => 'Walk-in Customer',
                    'subtotal' => $total,
                    'discount' => 0,
                    'total' => $total,
                    'payment_type' => 'cash',
                    'payment_method' => 'cash',
                    'payment_status' => 'confirmed',
                    'payment_currency' => $business->currency ?: 'PHP',
                    'payment_confirmed_by' => $staff->id,
                    'payment_confirmed_at' => $soldAt,
                    'status' => 'completed',
                    'completed_at' => $soldAt,
                    'client_id' => $clientId,
                    'synced' => true,
                ]);
                DB::table('orders')->where('id', $order->id)->update([
                    'created_at' => $soldAt,
                    'updated_at' => $soldAt,
                ]);
            }

            $ingredientsSnapshot = $product->productIngredients->map(fn ($ingredient): array => [
                'stock_item_id' => (int) $ingredient->stock_item_id,
                'quantity' => (float) $ingredient->quantity,
                'stock_name' => $ingredient->stockItem?->name,
                'unit' => $ingredient->stockItem?->unit,
            ])->values()->all();

            $order->items()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $quantity,
                    'subtotal' => $total,
                    'ingredients_snapshot' => $ingredientsSnapshot,
                ]
            );

            if (! $order->financials_committed_at) {
                $required = [];
                foreach ($ingredientsSnapshot as $ingredient) {
                    $stockId = $ingredient['stock_item_id'];
                    $required[$stockId] = ($required[$stockId] ?? 0) + ((float) $ingredient['quantity'] * $quantity);
                }
                ksort($required);
                $stockItems = StockItem::where('business_id', $business->id)
                    ->whereIn('id', array_keys($required))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($required as $stockId => $needed) {
                    $item = $stockItems[$stockId];
                    if ((float) $item->current_quantity < $needed) {
                        throw new \RuntimeException('Insufficient demo stock to seed '.$orderNumber.'.');
                    }
                    $before = (float) $item->current_quantity;
                    $after = round($before - $needed, 4);
                    $item->update(['current_quantity' => $after]);
                    StockMovement::create([
                        'stock_item_id' => $item->id,
                        'user_id' => $staff->id,
                        'order_id' => $order->id,
                        'type' => 'out',
                        'quantity' => $needed,
                        'previous_quantity' => $before,
                        'new_quantity' => $after,
                        'reason' => 'Seeded demo order '.$orderNumber,
                    ]);
                }
                $order->update(['financials_committed_at' => $soldAt]);
            }

            Transaction::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'business_id' => $business->id,
                    'user_id' => $staff->id,
                    'amount' => $total,
                    'type' => 'income',
                    'source' => 'cash',
                    'category' => 'Sales',
                    'transaction_date' => $soldAt->toDateString(),
                    'description' => $quantity.'× '.$product->name,
                    'entry_method' => 'order_system',
                    'metadata' => ['order_id' => $order->id, 'seeded_demo_record' => true],
                    'synced' => true,
                ]
            );
            DB::table('transactions')->where('order_id', $order->id)->update([
                'created_at' => $soldAt,
                'updated_at' => $soldAt,
            ]);

            OrderPaymentEvent::firstOrCreate(
                ['order_id' => $order->id, 'event_type' => 'demo_cash_payment_seeded'],
                [
                    'business_id' => $business->id,
                    'user_id' => $staff->id,
                    'from_status' => null,
                    'to_status' => 'completed',
                    'from_payment_status' => null,
                    'to_payment_status' => 'confirmed',
                    'amount' => $total,
                    'currency' => $business->currency ?: 'PHP',
                    'reason' => 'Local-only sample order for alpha walkthroughs.',
                    'details' => ['seeded_demo_record' => true, 'confirmation_method' => 'staff_cash_attestation'],
                ]
            );
            DB::table('orders')->where('id', $order->id)->update([
                'created_at' => $soldAt,
                'updated_at' => $soldAt,
            ]);
        });
    }
}
