<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessBulkActionController extends Controller
{
    public function __invoke(Request $request, AdminActivityRecorder $audit)
    {
        $data = $request->validate([
            'business_ids' => ['required', 'array', 'min:1', 'max:100'],
            'business_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'action' => ['required', Rule::in(['approve', 'activate', 'suspend', 'export'])],
        ]);

        $ids = array_map('intval', $data['business_ids']);
        if ($data['action'] === 'export') {
            $businesses = Business::with('owner', 'assignedOwner')->whereIn('id', $ids)->orderBy('id')->get();
            $this->assertCompleteSelection($businesses, $ids);
            foreach ($businesses as $business) {
                $audit->record($request->user(), 'business.exported', 'business', $business->id);
            }

            return response()->streamDownload(function () use ($businesses): void {
                $stream = fopen('php://output', 'w');
                fputcsv($stream, ['Business ID', 'Business', 'Type', 'Status', 'Plan', 'Subscription', 'Owner', 'City', 'Province', 'Registered']);
                foreach ($businesses as $business) {
                    $owner = $business->verified_owner_account;
                    fputcsv($stream, array_map(self::safeCsv(...), [
                        $business->id, $business->name, $business->business_type, $business->status,
                        $business->plan_tier, $business->subscription_status,
                        $owner?->name ?? 'Email verification pending',
                        $business->city, $business->province, $business->created_at?->toDateString(),
                    ]));
                }
                fclose($stream);
            }, 'gastotrack-selected-businesses.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        DB::transaction(function () use ($ids, $data, $request, $audit): void {
            $businesses = Business::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $this->assertCompleteSelection($businesses, $ids);
            $businesses->load('owner', 'assignedOwner');

            foreach ($businesses as $business) {
                if ($data['action'] === 'approve' && $business->status !== 'pending') {
                    throw ValidationException::withMessages(['business_ids' => 'Approve only pending businesses. No changes were saved.']);
                }
                if (in_array($data['action'], ['approve', 'activate'], true) && ! $business->verified_owner_account) {
                    throw ValidationException::withMessages(['business_ids' => 'Every selected business needs a verified owner before activation. No changes were saved.']);
                }
                if ($data['action'] === 'suspend' && $business->status !== 'active') {
                    throw ValidationException::withMessages(['business_ids' => 'Suspend only active businesses. No changes were saved.']);
                }
            }

            foreach ($businesses as $business) {
                if (in_array($data['action'], ['approve', 'activate'], true)) {
                    $business->update(['status' => 'active', 'active' => true, 'suspension_reason' => null]);
                } else {
                    $business->update(['status' => 'suspended', 'active' => false,
                        'suspension_reason' => 'Suspended by a platform administrator.']);
                    $business->users()->each(fn ($user) => $user->tokens()->delete());
                }
                $audit->record($request->user(), 'business.bulk_'.$data['action'], 'business', $business->id);
            }
        });

        return back()->with('success', count($ids).' businesses updated.');
    }

    private function assertCompleteSelection($businesses, array $ids): void
    {
        if ($businesses->count() !== count($ids)) {
            throw ValidationException::withMessages(['business_ids' => 'One or more selected businesses are unavailable. No changes were saved.']);
        }
    }

    private static function safeCsv($value): string
    {
        $text = (string) ($value ?? '');
        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $text) ? "'".$text : $text;
    }
}
