<?php

namespace Tests\Unit;

use App\Rules\PasswordStrength;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordStrengthTest extends TestCase
{
    public function test_only_a_strong_password_meets_the_rule(): void
    {
        $this->assertPasswordIsRejected('password1');
        $this->assertPasswordIsRejected('password1!');
        $this->assertPasswordIsRejected('Gasto9!');

        $validator = Validator::make(
            ['password' => 'GastoTrack9!'],
            ['password' => [new PasswordStrength()]],
        );

        $this->assertTrue($validator->passes());
    }

    private function assertPasswordIsRejected(string $password): void
    {
        $validator = Validator::make(
            ['password' => $password],
            ['password' => [new PasswordStrength()]],
        );

        $this->assertTrue($validator->fails(), "Expected [{$password}] to be rejected.");
    }
}
