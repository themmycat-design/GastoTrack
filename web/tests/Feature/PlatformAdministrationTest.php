<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
    }

    private function business(string $name = 'Test Shop', string $status = 'active'): Business
    {
        $business = Business::create(['name' => $name, 'status' => $status, 'active' => $status === 'active']);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);

        return $business;
    }

    public function test_global_directory_is_super_admin_only_and_hides_unverified_owner_identity(): void
    {
        $business = $this->business('Directory Cafe');
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'name' => 'Directory Staff']);
        $pending = User::factory()->owner()->unverified()->create([
            'business_id' => $business->id, 'name' => 'Private Pending Owner', 'email' => 'pending@example.test',
        ]);

        $this->actingAs($business->owner_account)->get(route('super-admin.users.index'))->assertForbidden();
        $this->actingAs($this->admin())
            ->get(route('super-admin.users.index', ['search' => 'Directory Cafe', 'role' => 'staff']))
            ->assertOk()->assertSee($staff->name);
        $this->get(route('super-admin.users.show', $pending))
            ->assertOk()->assertSee('Email verification pending')
            ->assertDontSee('Private Pending Owner')->assertDontSee('pending@example.test');
    }

    public function test_lock_revokes_mobile_tokens_and_cannot_lock_self(): void
    {
        $admin = $this->admin();
        $business = $this->business();
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $staff->createToken('test-token');

        $this->actingAs($admin)->post(route('super-admin.users.lock', $staff))->assertRedirect();
        $this->assertSame('inactive', $staff->fresh()->status);
        $this->assertCount(0, $staff->tokens);
        $this->assertDatabaseHas('admin_activity_logs', [
            'admin_id' => $admin->id, 'action' => 'user.locked', 'target_id' => $staff->id,
        ]);
        $this->post(route('super-admin.users.lock', $admin))->assertStatus(422);
        $this->post(route('super-admin.users.unlock', $staff))->assertRedirect();
        $this->assertSame('active', $staff->fresh()->status);
    }

    public function test_forced_reset_blocks_existing_access_until_password_is_changed(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $business = $this->business();
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);
        $staff->createToken('test-token');

        $this->actingAs($admin)->post(route('super-admin.users.password-reset', $staff))->assertRedirect();
        Notification::assertSentTo($staff, ResetPassword::class);
        $this->assertTrue($staff->fresh()->must_reset_password);
        $this->assertCount(0, $staff->tokens);
        $this->actingAs($staff)->getJson('/api/user')->assertForbidden();

        auth()->logout();
        $token = Password::createToken($staff);
        $this->post(route('password.store'), [
            'email' => $staff->email, 'token' => $token,
            'password' => 'NewStrongPassword1!', 'password_confirmation' => 'NewStrongPassword1!',
        ])->assertRedirect(route('login'));
        $this->assertFalse($staff->fresh()->must_reset_password);
    }

    public function test_subscription_expiry_keeps_owner_reads_but_blocks_changes_and_staff_api(): void
    {
        $business = $this->business();
        $business->update(['plan_tier' => 'pro', 'subscription_ends_at' => now()->subDay()]);
        $owner = $business->owner_account;
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);

        $this->actingAs($owner)->get(route('staff.index'))->assertOk()->assertSee('subscription has expired');
        $this->post(route('staff.store'), [])->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, $business->users()->where('role', 'staff')->count());
        $this->actingAs($staff)->getJson('/api/user')->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('super-admin.businesses.subscription.extend', $business))->assertRedirect();
        $this->assertTrue($business->fresh()->subscriptionIsCurrent());
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'business.subscription_extended', 'target_id' => $business->id]);
    }

    public function test_free_plan_limits_staff_but_pro_allows_another(): void
    {
        $business = $this->business();
        $owner = $business->owner_account;
        User::factory()->count(2)->staff()->create(['business_id' => $business->id]);
        $payload = [
            'name' => 'Additional Staff', 'email' => 'additional@example.test',
            'password' => 'StrongPassword1!', 'password_confirmation' => 'StrongPassword1!', 'role' => 'staff',
        ];

        $this->actingAs($owner)->post(route('staff.store'), $payload)->assertSessionHasErrors('role');
        $business->update(['plan_tier' => 'pro', 'subscription_ends_at' => now()->addMonth()]);
        $this->post(route('staff.store'), $payload)->assertRedirect();
        $this->assertSame(3, $business->users()->where('role', 'staff')->count());
    }

    public function test_bulk_approval_is_all_or_nothing_and_records_each_change(): void
    {
        $admin = $this->admin();
        $ready = $this->business('Ready Shop', 'pending');
        $unverified = $this->business('Unverified Shop', 'pending');
        $unverified->owner_account->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($admin)->post(route('super-admin.businesses.bulk'), [
            'action' => 'approve', 'business_ids' => [$ready->id, $unverified->id],
        ])->assertSessionHasErrors('business_ids');
        $this->assertSame('pending', $ready->fresh()->status);
        $this->assertDatabaseCount('admin_activity_logs', 0);

        $this->post(route('super-admin.businesses.bulk'), [
            'action' => 'approve', 'business_ids' => [$ready->id],
        ])->assertRedirect();
        $this->assertSame('active', $ready->fresh()->status);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'business.bulk_approve', 'target_id' => $ready->id]);
    }

    public function test_bulk_export_escapes_formula_cells_and_only_exports_selected_businesses(): void
    {
        $admin = $this->admin();
        $selected = $this->business('=SUM(1,2)');
        $this->business('Not Selected Shop');

        $csv = $this->actingAs($admin)->post(route('super-admin.businesses.bulk'), [
            'action' => 'export', 'business_ids' => [$selected->id],
        ])->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringNotContainsString('Not Selected Shop', $csv);
    }

    public function test_only_super_admin_can_view_audit_logs_or_update_subscriptions(): void
    {
        $business = $this->business();
        $owner = $business->owner_account;
        $this->actingAs($owner)->get(route('super-admin.activity-logs.index'))->assertForbidden();
        $this->put(route('super-admin.businesses.subscription.update', $business), [
            'plan_tier' => 'pro', 'subscription_status' => 'active', 'subscription_ends_at' => now()->addMonth()->toDateString(),
        ])->assertForbidden();

        $this->actingAs($this->admin())->put(route('super-admin.businesses.subscription.update', $business), [
            'plan_tier' => 'pro', 'subscription_status' => 'active', 'subscription_ends_at' => now()->addMonth()->toDateString(),
        ])->assertRedirect();
        $this->assertSame('pro', $business->fresh()->plan_tier);
    }
}
