<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a sample business
        $business = Business::create([
            'name' => 'GastoTrack Milk Tea Shop',
            'address' => '123 Main St, Manila, Philippines',
            'phone' => '+63 917 123 4567',
            'email' => 'info@gastotrack-milktea.com',
            'business_type' => 'cafe',
            'active' => true,
        ]);

        // Create owner user
        $owner = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'owner@gastotrack.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'business_id' => $business->id,
            'phone' => '+63 917 111 1111',
            'email_verified_at' => now(),
        ]);

        // Create staff users
        $staff1 = User::create([
            'name' => 'Maria Santos',
            'email' => 'staff1@gastotrack.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'business_id' => $business->id,
            'phone' => '+63 917 222 2222',
            'email_verified_at' => now(),
        ]);

        $staff2 = User::create([
            'name' => 'Pedro Reyes',
            'email' => 'staff2@gastotrack.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'business_id' => $business->id,
            'phone' => '+63 917 333 3333',
            'email_verified_at' => now(),
        ]);

        $this->command->info('Business and users created successfully!');
        $this->command->info('Owner: owner@gastotrack.com / password');
        $this->command->info('Staff1: staff1@gastotrack.com / password');
        $this->command->info('Staff2: staff2@gastotrack.com / password');
    }
}
