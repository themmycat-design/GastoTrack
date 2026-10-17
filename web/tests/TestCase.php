<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(UserContract $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        // Unit/feature fixtures actingAs() represent a completed authenticated
        // session. Login-flow tests use real POST requests and exercise MFA.
        if (($guard === null || $guard === 'web')
            && method_exists($user, 'isOwner')
            && $user->isOwner()
            && $user->hasEnabledTwoFactorAuthentication()) {
            $this->withSession(['owner.two_factor_user_id' => $user->getAuthIdentifier()]);
        }

        return $this;
    }
}
