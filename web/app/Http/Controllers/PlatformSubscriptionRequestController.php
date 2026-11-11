<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\SubscriptionRequest;
use App\Services\AdminActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PlatformSubscriptionRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        if (! in_array($status, ['pending', 'accepted', 'all'], true)) {
            $status = 'pending';
        }

        $requests = SubscriptionRequest::query()
            ->with(['business:id,name', 'requester:id,name'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'pending' => SubscriptionRequest::where('status', 'pending')->count(),
            'accepted' => SubscriptionRequest::where('status', 'accepted')->count(),
        ];

        return view('super-admin.subscriptions.index', compact('requests', 'status', 'counts'));
    }

    public function show(SubscriptionRequest $subscriptionRequest)
    {
        $subscriptionRequest->load(['business', 'requester', 'reviewer']);

        return view('super-admin.subscriptions.show', compact('subscriptionRequest'));
    }

    public function proof(SubscriptionRequest $subscriptionRequest)
    {
        abort_unless(Storage::disk('local')->exists($subscriptionRequest->proof_path), 404);

        return Storage::disk('local')->response($subscriptionRequest->proof_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function accept(Request $request, SubscriptionRequest $subscriptionRequest, AdminActivityRecorder $audit)
    {
        DB::transaction(function () use ($request, $subscriptionRequest, $audit): void {
            $record = SubscriptionRequest::whereKey($subscriptionRequest->id)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'pending') {
                throw ValidationException::withMessages(['request' => 'This request has already been reviewed.']);
            }
            if (! Storage::disk('local')->exists($record->proof_path)) {
                throw ValidationException::withMessages(['request' => 'The payment proof is missing. Ask the owner to send a new request.']);
            }

            $business = Business::whereKey($record->business_id)->lockForUpdate()->firstOrFail();
            // Renew the same active tier from its current end; upgrades start today.
            $base = $business->plan_tier === $record->plan_tier
                && $business->subscriptionIsCurrent()
                && $business->subscription_ends_at?->isFuture()
                    ? $business->subscription_ends_at->copy()
                    : now();

            $business->update([
                'plan_tier' => $record->plan_tier,
                'subscription_status' => 'active',
                'subscription_ends_at' => $base->addDays(30),
            ]);
            $record->update([
                'status' => 'accepted',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
            $audit->record($request->user(), 'subscription_request.accepted', 'subscription_request', $record->id);
        });

        return redirect()->route('super-admin.subscription-requests.show', $subscriptionRequest)
            ->with('success', 'Request accepted. The paid plan is active for 30 days from today or its existing end date.');
    }

    public function destroy(Request $request, SubscriptionRequest $subscriptionRequest, AdminActivityRecorder $audit)
    {
        $proofPath = $subscriptionRequest->proof_path;
        DB::transaction(function () use ($request, $subscriptionRequest, $audit): void {
            $subscriptionRequest->delete();
            $audit->record($request->user(), 'subscription_request.deleted', 'subscription_request', $subscriptionRequest->id);
        });
        Storage::disk('local')->delete($proofPath);

        return redirect()->route('super-admin.subscription-requests.index')
            ->with('success', 'Subscription request and proof deleted. Any already activated plan remains active.');
    }
}
