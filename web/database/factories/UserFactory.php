<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'staff',
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'owner',
            // Owner fixtures represent an already-enrolled production account so
            // existing portal tests exercise business features beyond the setup gate.
            'two_factor_secret' => app('encrypter')->encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => app('encrypter')->encrypt(json_encode([
                'TEST-RECOVERY-CODE-1', 'TEST-RECOVERY-CODE-2',
            ])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'staff',
        ]);
    }
    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
