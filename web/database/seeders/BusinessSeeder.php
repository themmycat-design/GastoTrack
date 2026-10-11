<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Demo business seeding is available only in local/testing environments.');
            return;
        }

        $business = Business::updateOrCreate(
            ['email' => 'info@gastotrack-milktea.com'],
            [
                'name' => 'GastoTrack Milk Tea Shop',
                'address' => '123 Main St, Manila, Philippines',
                'phone' => '+63 917 123 4567',
                'business_type' => 'cafe',
                'active' => true,
                'status' => 'active',
            ]
        );

        $owner = User::updateOrCreate(
            ['email' => 'owner@gastotrack.com'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => 'password',
                'role' => 'owner',
                'business_id' => $business->id,
                'phone' => '+63 917 111 1111',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        $business->update(['owner_id' => $owner->id]);

        User::updateOrCreate(['email' => 'staff1@gastotrack.com'], [
            'name' => 'Maria Santos', 'password' => 'password', 'role' => 'staff',
            'business_id' => $business->id, 'phone' => '+63 917 222 2222',
            'status' => 'active', 'email_verified_at' => now(),
        ]);
        User::updateOrCreate(['email' => 'staff2@gastotrack.com'], [
            'name' => 'Pedro Reyes', 'password' => 'password', 'role' => 'staff',
            'business_id' => $business->id, 'phone' => '+63 917 333 3333',
            'status' => 'active', 'email_verified_at' => now(),
        ]);

        $this->command?->info('Repeatable local demo business and accounts are ready. Credentials are listed in docs/ALPHA_SETUP.md.');
    }
}
