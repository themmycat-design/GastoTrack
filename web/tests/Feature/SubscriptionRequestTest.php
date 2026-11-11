<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubscriptionRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function business(string $name): Business
    {
        $business = Business::create(['name' => $name, 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id]);
        $business->update(['owner_id' => $owner->id]);

        return $business;
    }

    private function payload(): array
    {
        return [
            'plan_tier' => 'pro',
            'payment_method' => 'gcash',
            'payment_reference' => 'TEST-REF-1',
            'owner_note' => 'Test payment',
            'payment_proof' => UploadedFile::fake()->image('proof.png'),
        ];
    }

    public function test_owner_request_is_private_and_does_not_activate_plan(): void
    {
        $first = $this->business('First Shop');
        $second = $this->business('Second Shop');

        $this->actingAs($first->owner_account)
            ->post(route('owner.subscriptions.store'), $this->payload())
            ->assertRedirect(route('owner.subscriptions.index'));

        $record = SubscriptionRequest::firstOrFail();
        $this->assertSame('pending', $record->status);
        $this->assertSame('free', $first->fresh()->plan_tier);
        Storage::disk('local')->assertExists($record->proof_path);
        $this->get(route('owner.subscriptions.proof', $record))->assertOk();

        $this->actingAs($second->owner_account)
            ->get(route('owner.subscriptions.index'))->assertOk()->assertDontSee('First Shop');
        $this->get(route('owner.subscriptions.proof', $record))->assertNotFound();
        $this->get(route('super-admin.subscription-requests.show', $record))->assertForbidden();
    }

    public function test_expired_owner_can_request_renewal_but_cannot_submit_duplicate_or_invalid_proof(): void
    {
        $business = $this->business('Expired Shop');
        $business->update(['plan_tier' => 'pro', 'subscription_ends_at' => now()->subDay()]);

        $this->actingAs($business->owner_account)
            ->post(route('owner.subscriptions.store'), array_replace($this->payload(), [
                'payment_proof' => UploadedFile::fake()->create('proof.pdf', 5, 'application/pdf'),
            ]))->assertSessionHasErrors('payment_proof');
        $this->assertDatabaseCount('subscription_requests', 0);

        $this->post(route('owner.subscriptions.store'), $this->payload())->assertRedirect();
        $this->post(route('owner.subscriptions.store'), $this->payload())->assertSessionHasErrors('plan_tier');
        $this->assertDatabaseCount('subscription_requests', 1);
        $this->assertFalse($business->fresh()->subscriptionIsCurrent());
    }

    public function test_super_admin_can_review_accept_and_delete_request(): void
    {
        $business = $this->business('Review Shop');
        $this->actingAs($business->owner_account)
            ->post(route('owner.subscriptions.store'), $this->payload())->assertRedirect();
        $record = SubscriptionRequest::firstOrFail();
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);

        $this->actingAs($admin)
            ->get(route('super-admin.subscription-requests.index'))->assertOk()->assertSee('Review Shop');
        $this->get(route('super-admin.subscription-requests.show', $record))->assertOk()->assertSee('TEST-REF-1');
        $this->get(route('super-admin.subscription-requests.proof', $record))->assertOk();
        $this->post(route('super-admin.subscription-requests.accept', $record))->assertRedirect();

        $this->assertSame('accepted', $record->fresh()->status);
        $this->assertSame('pro', $business->fresh()->plan_tier);
        $this->assertTrue($business->fresh()->subscriptionIsCurrent());
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'subscription_request.accepted', 'target_id' => $record->id,
        ]);
        $this->post(route('super-admin.subscription-requests.accept', $record))->assertSessionHasErrors('request');

        $this->delete(route('super-admin.subscription-requests.destroy', $record))->assertRedirect();
        $this->assertDatabaseMissing('subscription_requests', ['id' => $record->id]);
        Storage::disk('local')->assertMissing($record->proof_path);
        $this->assertSame('pro', $business->fresh()->plan_tier);
        $this->assertDatabaseHas('admin_activity_logs', [
            'action' => 'subscription_request.deleted', 'target_id' => $record->id,
        ]);
    }

    public function test_same_tier_renewal_extends_existing_end_date(): void
    {
        $business = $this->business('Renewal Shop');
        $originalEnd = now()->addDays(12)->startOfSecond();
        $business->update([
            'plan_tier' => 'pro',
            'subscription_status' => 'active',
            'subscription_ends_at' => $originalEnd,
        ]);
        $this->actingAs($business->owner_account)
            ->post(route('owner.subscriptions.store'), $this->payload())->assertRedirect();

        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $this->actingAs($admin)
            ->post(route('super-admin.subscription-requests.accept', SubscriptionRequest::firstOrFail()))
            ->assertRedirect();

        $this->assertSame(
            $originalEnd->copy()->addDays(30)->timestamp,
            $business->fresh()->subscription_ends_at->timestamp
        );
    }

    public function test_staff_cannot_submit_or_review_subscription_requests(): void
    {
        $business = $this->business('Staff Shop');
        $staff = User::factory()->staff()->create(['business_id' => $business->id]);

        $this->actingAs($staff)->get(route('owner.subscriptions.index'))->assertForbidden();
        $this->post(route('owner.subscriptions.store'), $this->payload())->assertForbidden();
        $this->get(route('super-admin.subscription-requests.index'))->assertForbidden();
    }
}
