<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use App\Models\BugReport;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

    public function test_unverified_owner_details_are_hidden_until_email_verification(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $business = Business::create([
            'name' => 'Verification Pending Cafe',
            'email' => 'owner-private@example.test',
            'status' => 'pending',
            'active' => false,
        ]);
        $owner = User::factory()->owner()->unverified()->create([
            'name' => 'Private Owner Name',
            'email' => 'owner-private@example.test',
            'phone' => '555-0101',
            'business_id' => $business->id,
        ]);
        $business->update(['owner_id' => $owner->id]);

        $this->actingAs($admin)
            ->get(route('super-admin.businesses'))
            ->assertOk()
            ->assertSee('Email verification pending')
            ->assertDontSee('Private Owner Name')
            ->assertDontSee('owner-private@example.test');

        $this->actingAs($admin)
            ->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertSee('Email verification pending')
            ->assertDontSee('Private Owner Name')
            ->assertDontSee('owner-private@example.test');

        $this->actingAs($admin)
            ->get(route('super-admin.businesses.show', $business))
            ->assertOk()
            ->assertSee('Owner details are hidden until the registered owner verifies their email.')
            ->assertSee('Owner email verification is required before approval.')
            ->assertDontSee('Private Owner Name')
            ->assertDontSee('owner-private@example.test')
            ->assertDontSee('555-0101')
            ->assertDontSee('Approve business');

        $this->actingAs($admin)
            ->get(route('super-admin.businesses.edit', $business))
            ->assertOk()
            ->assertSee('Owner details are hidden and cannot be changed until the registered owner verifies their email.')
            ->assertSee('Hidden until the owner verifies their email.')
            ->assertDontSee('Private Owner Name')
            ->assertDontSee('owner-private@example.test');

        $this->actingAs($admin)
            ->post(route('super-admin.businesses.approve', $business))
            ->assertRedirect()
            ->assertSessionHas('error', 'The business owner must verify their email before this business can be approved.');

        $this->actingAs($admin)
            ->put(route('super-admin.businesses.update', $business), [
                'name' => $business->name,
                'business_type' => 'cafe',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'The business owner must verify their email before this business can be activated.');

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'status' => 'pending',
            'active' => false,
        ]);
    }

    public function test_approving_a_pending_business_enables_it(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $business = Business::create([
            'name' => 'Pending Bakery',
            'status' => 'pending',
            'active' => false,
        ]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);

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
        Notification::fake();

        $this->actingAs($admin)->post(route('super-admin.businesses.store'), [
            'name' => 'Admin Created Cafe',
            'business_type' => 'cafe',
            'status' => 'active',
            'owner_name' => 'Cafe Owner',
            'owner_email' => 'owner@sunrisecafe.example',
            'password' => 'GastoTrack9!',
            'password_confirmation' => 'GastoTrack9!',
        ])->assertRedirect();

        $business = Business::where('name', 'Admin Created Cafe')->firstOrFail();
        $owner = User::where('email', 'owner@sunrisecafe.example')->firstOrFail();
        $this->assertNull($owner->email_verified_at);
        Notification::assertSentTo($owner, VerifyEmail::class);
        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'status' => 'pending', 'active' => false]);

        $this->actingAs($admin)
            ->get(route('super-admin.businesses'))
            ->assertOk()
            ->assertSee('Delete');

        $this->assertDatabaseHas('users', [
            'email' => 'owner@sunrisecafe.example',
            'role' => 'owner',
            'business_id' => $business->id,
        ]);

        $owner->markEmailAsVerified();

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

        $this->actingAs($admin)->delete(route('super-admin.businesses.destroy', $business))->assertRedirect();
        $this->actingAs($admin)
            ->get(route('super-admin.businesses', ['archived' => 1]))
            ->assertOk()
            ->assertSee('Delete permanently');

        $this->actingAs($admin)
            ->delete(route('super-admin.businesses.force-destroy', $business->id))
            ->assertRedirect(route('super-admin.businesses', ['archived' => 1]));

        $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
    }

    public function test_permanent_delete_is_limited_to_archived_businesses_and_removes_tenant_records(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $business = Business::create(['name' => 'Permanent Delete Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);
        $timestamp = now();

        $productId = DB::table('products')->insertGetId([
            'business_id' => $business->id,
            'name' => 'Milk Tea',
            'category' => 'Milk Tea',
            'price' => 85,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $stockItemId = DB::table('stock_items')->insertGetId([
            'business_id' => $business->id,
            'name' => 'Milk',
            'unit' => 'liter',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $orderId = DB::table('orders')->insertGetId([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'subtotal' => 85,
            'total' => 85,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'product_name' => 'Milk Tea',
            'quantity' => 1,
            'price' => 85,
            'subtotal' => 85,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('transactions')->insert([
            'business_id' => $business->id,
            'user_id' => $owner->id,
            'order_id' => $orderId,
            'type' => 'income',
            'category' => 'Sales',
            'amount' => 85,
            'transaction_date' => $timestamp->toDateString(),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('order_payment_events')->insert([
            'order_id' => $orderId,
            'business_id' => $business->id,
            'event_type' => 'payment_confirmed',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('bug_reports')->insert([
            'business_id' => $business->id,
            'subject' => 'Test report',
            'description' => 'Test report for tenant deletion.',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $movementId = DB::table('stock_movements')->insertGetId([
            'stock_item_id' => $stockItemId,
            'user_id' => $owner->id,
            'type' => 'out',
            'quantity' => 1,
            'previous_quantity' => 10,
            'new_quantity' => 9,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        DB::table('stock_movements')->insert([
            'stock_item_id' => $stockItemId,
            'user_id' => $owner->id,
            'type' => 'in',
            'quantity' => 1,
            'previous_quantity' => 9,
            'new_quantity' => 10,
            'reversal_of_id' => $movementId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $this->actingAs($admin)
            ->delete(route('super-admin.businesses.force-destroy', $business->id))
            ->assertNotFound();

        $this->actingAs($admin)->delete(route('super-admin.businesses.destroy', $business))->assertRedirect();
        $this->actingAs($admin)
            ->delete(route('super-admin.businesses.force-destroy', $business->id))
            ->assertRedirect(route('super-admin.businesses', ['archived' => 1]));

        $this->assertDatabaseMissing('businesses', ['id' => $business->id]);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
        $this->assertDatabaseMissing('products', ['id' => $productId]);
        $this->assertDatabaseMissing('stock_items', ['id' => $stockItemId]);
        $this->assertDatabaseMissing('transactions', ['business_id' => $business->id]);
        $this->assertDatabaseMissing('order_payment_events', ['business_id' => $business->id]);
        $this->assertDatabaseMissing('bug_reports', ['business_id' => $business->id]);
    }

    public function test_super_admin_can_manage_bug_reports_and_export_platform_report(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $business = Business::create(['name' => 'Reported Cafe', 'status' => 'active', 'active' => true]);
        $report = BugReport::create([
            'business_id' => $business->id,
            'subject' => 'Policy complaint',
            'category' => 'other',
            'area' => 'payments',
            'priority' => 'high',
            'status' => 'open',
            'description' => 'A platform policy needs administrator review.',
        ]);

        $this->actingAs($admin)->get(route('super-admin.bug-reports.index'))->assertOk()->assertSee('Policy complaint');
        $this->actingAs($admin)->get('/super-admin/bug-reports/create')->assertNotFound();
        $this->actingAs($admin)->post('/super-admin/bug-reports', [])->assertNotFound();

        $this->actingAs($admin)->put(route('super-admin.bug-reports.update', $report), [
            'business_id' => $business->id,
            'subject' => 'Policy complaint',
            'area' => 'payments',
            'priority' => 'high',
            'status' => 'resolved',
            'description' => 'A platform policy needs administrator review.',
            'resolution_notes' => 'Reviewed and resolved.',
            'public_update' => 'This report has been reviewed.',
        ])->assertRedirect();

        $this->assertNotNull($report->fresh()->resolved_at);
        $this->actingAs($admin)->get(route('super-admin.reports.export'))
            ->assertOk()
            ->assertDownload();
    }
}
