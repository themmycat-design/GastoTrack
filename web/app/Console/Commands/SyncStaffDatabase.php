<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Business;
use App\Models\Product;
use App\Models\StockItem;
use App\Models\Order;
use App\Models\Transaction;

class SyncStaffDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staff:sync {direction=pull : Direction of sync (pull|push)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync data between MySQL (online) and SQLite (offline) for staff users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $direction = $this->argument('direction');

        if ($direction === 'pull') {
            $this->pullFromMySQL();
        } elseif ($direction === 'push') {
            $this->pushToMySQL();
        } else {
            $this->error('Invalid direction. Use "pull" or "push".');
            return 1;
        }

        return 0;
    }

    /**
     * Pull data from MySQL to SQLite (for staff offline mode)
     */
    private function pullFromMySQL()
    {
        $this->info('Pulling data from MySQL to SQLite...');

        // Use MySQL connection to fetch data
        $businesses = DB::connection('mysql')->table('businesses')->get();
        $products = DB::connection('mysql')->table('products')->get();
        $stockItems = DB::connection('mysql')->table('stock_items')->get();
        $productIngredients = DB::connection('mysql')->table('product_ingredients')->get();

        // Switch to SQLite and insert
        DB::connection('staff_offline')->table('businesses')->truncate();
        DB::connection('staff_offline')->table('products')->truncate();
        DB::connection('staff_offline')->table('stock_items')->truncate();
        DB::connection('staff_offline')->table('product_ingredients')->truncate();

        foreach ($businesses as $business) {
            DB::connection('staff_offline')->table('businesses')->insert((array) $business);
        }

        foreach ($products as $product) {
            DB::connection('staff_offline')->table('products')->insert((array) $product);
        }

        foreach ($stockItems as $item) {
            DB::connection('staff_offline')->table('stock_items')->insert((array) $item);
        }

        foreach ($productIngredients as $ingredient) {
            DB::connection('staff_offline')->table('product_ingredients')->insert((array) $ingredient);
        }

        $this->info('✓ Data pulled successfully!');
        $this->info("  - {$businesses->count()} businesses");
        $this->info("  - {$products->count()} products");
        $this->info("  - {$stockItems->count()} stock items");
        $this->info("  - {$productIngredients->count()} product ingredients");
    }

    /**
     * Push data from SQLite to MySQL (staff sync online)
     */
    private function pushToMySQL()
    {
        $this->info('Pushing data from SQLite to MySQL...');

        // Fetch offline orders and transactions
        $orders = DB::connection('staff_offline')->table('orders')->where('synced', false)->get();
        $transactions = DB::connection('staff_offline')->table('transactions')->where('synced', false)->get();

        // Push to MySQL
        foreach ($orders as $order) {
            $orderData = (array) $order;
            unset($orderData['synced']);
            
            DB::connection('mysql')->table('orders')->insert($orderData);
            
            // Mark as synced in SQLite
            DB::connection('staff_offline')->table('orders')
                ->where('id', $order->id)
                ->update(['synced' => true]);
        }

        foreach ($transactions as $transaction) {
            $transactionData = (array) $transaction;
            unset($transactionData['synced']);
            
            DB::connection('mysql')->table('transactions')->insert($transactionData);
            
            // Mark as synced in SQLite
            DB::connection('staff_offline')->table('transactions')
                ->where('id', $transaction->id)
                ->update(['synced' => true]);
        }

        $this->info('✓ Data pushed successfully!');
        $this->info("  - {$orders->count()} orders synced");
        $this->info("  - {$transactions->count()} transactions synced");
    }
}
