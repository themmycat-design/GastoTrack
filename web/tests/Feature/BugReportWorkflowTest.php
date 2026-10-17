<?php

namespace Tests\Feature;

use App\Models\BugReport;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BugReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_legacy_report_ids_and_categories(): void
    {
        $business = Business::create(['name' => 'Legacy Cafe', 'status' => 'active', 'active' => true]);
        $report = BugReport::create([
            'business_id' => $business->id,
            'subject' => 'Original platform report',
            'category' => 'fraud_risk',
            'priority' => 'high',
            'status' => 'under_review',
            'description' => 'Existing report content must remain unchanged.',
        ]);

        $migration = require database_path('migrations/2026_10_16_130000_convert_violation_reports_to_bug_reports.php');
        $migration->down();

        $this->assertDatabaseHas('violation_reports', [
            'id' => $report->id,
            'category' => 'fraud_risk',
            'description' => 'Existing report content must remain unchanged.',
        ]);

        $migration->up();

        $this->assertDatabaseHas('bug_reports', [
            'id' => $report->id,
            'category' => 'fraud_risk',
            'is_legacy' => true,
        ]);
    }

    public function test_owner_can_submit_and_view_business_reports_without_internal_notes(): void
    {
        Storage::fake('local');
        $business = Business::create(['name' => 'Owner Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id, 'status' => 'active']);
        $business->update(['owner_id' => $owner->id]);
        $otherBusiness = Business::create(['name' => 'Other Cafe', 'status' => 'active', 'active' => true]);
        $otherOwner = User::factory()->owner()->create(['business_id' => $otherBusiness->id, 'status' => 'active']);

        $legacyScreenshot = UploadedFile::fake()->image('legacy.png')->store('bug-reports/'.$business->id, 'local');
        $legacyReport = BugReport::create([
            'business_id' => $business->id,
            'reported_by' => $owner->id,
            'subject' => 'Legacy issue',
            'category' => 'complaint',
            'priority' => 'medium',
            'status' => 'resolved',
            'description' => 'A converted report.',
            'resolution_notes' => 'Private administrator-only detail.',
            'public_update' => 'We have reviewed this report.',
            'screenshot_path' => $legacyScreenshot,
            'is_legacy' => true,
        ]);
        BugReport::create([
            'business_id' => $otherBusiness->id,
            'reported_by' => $otherOwner->id,
            'subject' => 'Other tenant issue',
            'category' => 'other',
            'priority' => 'medium',
            'status' => 'open',
            'description' => 'Must not be visible to this owner.',
        ]);

        $this->actingAs($owner)->get(route('owner.bug-reports.index'))
            ->assertOk()
            ->assertSee('Legacy issue')
            ->assertSee('We have reviewed this report.')
            ->assertDontSee('Private administrator-only detail.')
            ->assertDontSee('Other tenant issue');
        $this->get(route('owner.bug-reports.screenshot', $legacyReport))->assertOk();
        $this->actingAs($otherOwner)->get(route('owner.bug-reports.screenshot', $legacyReport))->assertNotFound();

        $this->actingAs($owner)->post(route('owner.bug-reports.store'), [
            'business_id' => $otherBusiness->id,
            'reported_by' => $otherOwner->id,
            'subject' => 'Cart button freezes',
            'area' => 'orders',
            'description' => 'The cart does not open after adding a product.',
            'steps_to_reproduce' => 'Add a product, then tap cart.',
        ])->assertRedirect(route('owner.bug-reports.index'));

        $this->assertDatabaseHas('bug_reports', [
            'business_id' => $business->id,
            'reported_by' => $owner->id,
            'subject' => 'Cart button freezes',
            'area' => 'orders',
            'priority' => 'medium',
            'status' => 'open',
            'is_legacy' => false,
        ]);
        $this->assertDatabaseMissing('bug_reports', [
            'business_id' => $otherBusiness->id,
            'reported_by' => $otherOwner->id,
            'subject' => 'Cart button freezes',
        ]);
    }

    public function test_staff_reports_are_private_to_the_submitter_and_screenshots_are_validated(): void
    {
        Storage::fake('local');
        $business = Business::create(['name' => 'Staff Cafe', 'status' => 'active', 'active' => true]);
        $staff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);
        $otherStaff = User::factory()->staff()->create(['business_id' => $business->id, 'status' => 'active']);
        $staffHeaders = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$staff->createToken('bug-report-test')->plainTextToken,
        ];
        $otherStaffHeaders = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$otherStaff->createToken('bug-report-test')->plainTextToken,
        ];

        $response = $this->post('/api/v1/bug-reports', [
            'business_id' => 999999,
            'reported_by' => $otherStaff->id,
            'subject' => 'Login screen freezes',
            'area' => 'login',
            'description' => 'The app stops responding after tapping Log in.',
            'steps_to_reproduce' => 'Open app, enter credentials, tap Log in.',
            'screenshot' => UploadedFile::fake()->image('login.png'),
        ], $staffHeaders)->assertCreated()
            ->assertJsonPath('bug_report.status', 'open')
            ->assertJsonPath('bug_report.priority', 'medium')
            ->assertJsonPath('bug_report.has_screenshot', true);

        $reportId = $response->json('bug_report.id');
        $report = BugReport::findOrFail($reportId);
        Storage::disk('local')->assertExists($report->screenshot_path);
        $report->update(['resolution_notes' => 'Private engineering detail.']);

        $this->getJson('/api/v1/bug-reports', $staffHeaders)
            ->assertOk()
            ->assertJsonPath('bug_reports.0.id', $reportId);
        $this->get('/api/v1/bug-reports/'.$reportId.'/screenshot', $staffHeaders)->assertOk();
        $this->getJson('/api/v1/bug-reports/'.$reportId, $staffHeaders)
            ->assertOk()
            ->assertJsonMissingPath('bug_report.resolution_notes');

        // Sanctum caches its resolved guard between requests in this test process.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/bug-reports/'.$reportId, $otherStaffHeaders)->assertNotFound();
        $this->get('/api/v1/bug-reports/'.$reportId.'/screenshot', $otherStaffHeaders)->assertNotFound();
        $this->getJson('/api/v1/bug-reports', $otherStaffHeaders)
            ->assertOk()
            ->assertJsonCount(0, 'bug_reports');

        $this->post('/api/v1/bug-reports', [
            'subject' => 'Invalid screenshot',
            'area' => 'other',
            'description' => 'An unsupported file should be rejected.',
            'screenshot' => UploadedFile::fake()->create('script.txt', 5, 'text/plain'),
        ], $staffHeaders)->assertUnprocessable();

        $this->post('/api/v1/bug-reports', [
            'subject' => 'Oversized screenshot',
            'area' => 'other',
            'description' => 'An oversized file should be rejected.',
            'screenshot' => UploadedFile::fake()->image('large.png')->size(5121),
        ], $staffHeaders)->assertUnprocessable();
    }

    public function test_super_admin_controls_status_and_priority_and_legacy_urls_redirect(): void
    {
        $business = Business::create(['name' => 'Admin Cafe', 'status' => 'active', 'active' => true]);
        $owner = User::factory()->owner()->create(['business_id' => $business->id, 'status' => 'active']);
        $business->update(['owner_id' => $owner->id]);
        $admin = User::factory()->create(['role' => 'super_admin', 'business_id' => null]);
        $report = BugReport::create([
            'business_id' => $business->id,
            'reported_by' => $owner->id,
            'subject' => 'Inventory count is incorrect',
            'category' => 'other',
            'area' => 'inventory',
            'priority' => 'medium',
            'status' => 'open',
            'description' => 'The displayed count is wrong.',
        ]);

        $this->actingAs($admin)->put(route('super-admin.bug-reports.update', $report), [
            'business_id' => $business->id,
            'subject' => $report->subject,
            'area' => 'inventory',
            'priority' => 'critical',
            'status' => 'resolved',
            'description' => $report->description,
            'public_update' => 'The inventory display has been corrected.',
            'resolution_notes' => 'Internal engineering detail - do not disclose.',
        ])->assertRedirect(route('super-admin.bug-reports.show', $report));

        $report->refresh();
        $this->assertSame('critical', $report->priority);
        $this->assertSame('resolved', $report->status);
        $this->assertNotNull($report->resolved_at);
        $this->assertSame('Internal engineering detail - do not disclose.', $report->resolution_notes);

        $this->actingAs($owner)->get(route('owner.bug-reports.index'))
            ->assertOk()
            ->assertSee('The inventory display has been corrected.')
            ->assertDontSee('Internal engineering detail - do not disclose.');

        $this->actingAs($owner)->put('/bug-reports/'.$report->id, [
            'status' => 'open',
            'priority' => 'low',
        ])->assertNotFound();

        $this->actingAs($admin)->get(route('super-admin.reports.index'))
            ->assertOk()
            ->assertSee('Open bugs')
            ->assertSee('1');

        $this->actingAs($admin)->get('/super-admin/violations/'.$report->id)
            ->assertRedirect(route('super-admin.bug-reports.show', $report));
    }
}
