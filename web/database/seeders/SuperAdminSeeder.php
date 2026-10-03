<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (!$email || !$password) {
            $this->command?->warn('Super Admin was not seeded. Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD first.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'GastoTrack Administrator'),
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'business_id' => null,
                'status' => 'active',
            ]
        );
    }
}
