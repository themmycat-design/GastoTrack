<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Models\ViolationReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_open_platform_management(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->get(route('super-admin.dashboard'))
            ->assertForbidden();
    }

    public function test_business_details_only_include_platform_safe_staff_count(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $business = Business::create([
            'name' => 'Privacy Coffee',
            'status' => 'active',
            'active' => true,
        ]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);
        User::factory()->count(2)->staff()->create(['business_id' => $business->id]);

        $this->actingAs($admin)
            ->get(route('super-admin.businesses.show', $business))
            ->assertOk()
            ->assertViewIs('super-admin.show')
            ->assertViewHas('staffCount', 2)
            ->assertDontSee('Recent Transactions')
            ->assertDontSee('Stock Items');

        $this->assertFalse($admin->canAccessBusiness($business->id));
    }

    public function test_legacy_business_owner_is_resolved_from_the_users_business_link(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $business = Business::create([
            'name' => 'Legacy Coffee Shop',
            'status' => 'active',
            'active' => true,
            'owner_id' => null,
        ]);
        $owner = User::factory()->owner()->create([
            'name' => 'Legacy Owner',
            'business_id' => $business->id,
        ]);

        $this->assertSame($owner->id, $business->fresh()->owner_account->id);

        $this->actingAs($admin)
            ->get(route('super-admin.businesses.show', $business))
            ->assertOk()
            ->assertSee('Legacy Owner')
            ->assertDontSee('No owner account is assigned.');
    }

    public function test_approving_a_pending_business_enables_it(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $business = Business::create([
            'name' => 'Pending Bakery',
            'status' => 'pending',
            'active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('super-admin.businesses.approve', $business))
            ->assertRedirect();

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'status' => 'active',
            'active' => true,
        ]);
    }

    public function test_super_admin_can_create_update_archive_and_restore_a_business(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);

        $this->actingAs($admin)->post(route('super-admin.businesses.store'), [
            'name' => 'Admin Created Cafe',
            'business_type' => 'cafe',
            'status' => 'pending',
            'owner_name' => 'Cafe Owner',
            'owner_email' => 'owner@sunrisecafe.example',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $business = Business::where('name', 'Admin Created Cafe')->firstOrFail();
        $this->assertDatabaseHas('users', [
            'email' => 'owner@sunrisecafe.example',
            'role' => 'owner',
            'business_id' => $business->id,
        ]);

        $this->actingAs($admin)->put(route('super-admin.businesses.update', $business), [
            'name' => 'Updated Cafe',
            'business_type' => 'cafe',
            'status' => 'active',
            'owner_name' => 'Updated Owner',
            'owner_email' => 'owner@sunrisecafe.example',
        ])->assertRedirect();

        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'name' => 'Updated Cafe', 'active' => true]);

        $this->actingAs($admin)->delete(route('super-admin.businesses.destroy', $business))->assertRedirect();
        $this->assertSoftDeleted('businesses', ['id' => $business->id]);

        $this->actingAs($admin)->post(route('super-admin.businesses.restore', $business->id))->assertRedirect();
        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'status' => 'inactive', 'deleted_at' => null]);
    }

    public function test_super_admin_can_manage_violation_reports_and_export_platform_report(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $business = Business::create(['name' => 'Reported Cafe', 'status' => 'active', 'active' => true]);

        $this->actingAs($admin)->post(route('super-admin.violations.store'), [
            'business_id' => $business->id,
            'subject' => 'Policy complaint',
            'category' => 'policy_violation',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'A platform policy needs administrator review.',
        ])->assertRedirect();

        $report = ViolationReport::firstOrFail();
        $this->actingAs($admin)->put(route('super-admin.violations.update', $report), [
            'business_id' => $business->id,
            'subject' => 'Policy complaint',
            'category' => 'policy_violation',
            'priority' => 'high',
            'status' => 'resolved',
            'description' => 'A platform policy needs administrator review.',
            'resolution_notes' => 'Reviewed and resolved.',
        ])->assertRedirect();

        $this->assertNotNull($report->fresh()->resolved_at);
        $this->actingAs($admin)->get(route('super-admin.reports.export'))
            ->assertOk()
            ->assertDownload();
    }
}
