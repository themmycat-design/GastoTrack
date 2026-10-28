<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Services\AdminActivityRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusinessSubscriptionController extends Controller
{
    public function update(Request $request, Business $business, AdminActivityRecorder $audit)
    {
        $data = $request->validate([
            'plan_tier' => ['required', Rule::in(['free', 'basic', 'pro', 'enterprise'])],
            'subscription_status' => ['required', Rule::in(['active', 'suspended', 'expired'])],
            'subscription_ends_at' => ['nullable', 'date'],
        ]);

        if ($data['plan_tier'] !== 'free' && $data['subscription_status'] === 'active' && empty($data['subscription_ends_at'])) {
            return back()->withErrors(['subscription_ends_at' => 'Set an expiration date for an active paid plan.'])->withInput();
        }
        // The date picker uses the shop's Philippine calendar day, while the
        // stored timestamp remains UTC like the rest of the Laravel app.
        $data['subscription_ends_at'] = $data['plan_tier'] === 'free'
            ? null
            : (empty($data['subscription_ends_at']) ? null : Carbon::parse($data['subscription_ends_at'], 'Asia/Manila')->endOfDay()->utc());

        DB::transaction(function () use ($business, $data, $request, $audit): void {
            $business->update($data);
            $audit->record($request->user(), 'business.subscription_updated', 'business', $business->id);
        });

        return back()->with('success', 'Subscription updated. Business approval status was not changed.');
    }

    public function extend(Request $request, Business $business, AdminActivityRecorder $audit)
    {
        abort_if($business->plan_tier === 'free', 422, 'The Free plan does not need renewal.');

        DB::transaction(function () use ($business, $request, $audit): void {
            $base = $business->subscription_ends_at?->isFuture() ? $business->subscription_ends_at : now();
            $business->update([
                'subscription_status' => 'active',
                'subscription_ends_at' => $base->copy()->addDays(30),
            ]);
            $audit->record($request->user(), 'business.subscription_extended', 'business', $business->id);
        });

        return back()->with('success', 'Subscription extended by 30 days from its current end date or today.');
    }
}
