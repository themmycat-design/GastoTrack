<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\SubscriptionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class OwnerSubscriptionRequestController extends Controller
{
    public function index(Request $request)
    {
        $business = $request->user()->business;
        $requests = $business->subscriptionRequests()->latest()->paginate(10);
        $hasPendingRequest = $business->subscriptionRequests()->where('status', 'pending')->exists();

        return view('subscriptions.owner', compact('business', 'requests', 'hasPendingRequest'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'plan_tier' => ['required', Rule::in(SubscriptionRequest::PAID_PLANS)],
            'payment_method' => ['required', Rule::in(SubscriptionRequest::PAYMENT_METHODS)],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'owner_note' => ['nullable', 'string', 'max:1000'],
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $businessId = (int) $request->user()->business_id;
        $path = null;

        try {
            DB::transaction(function () use ($request, $data, $businessId, &$path): void {
                // Serialize submissions per tenant so one business has one pending request.
                $business = Business::whereKey($businessId)->lockForUpdate()->firstOrFail();
                if ($business->subscriptionRequests()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages([
                        'plan_tier' => 'A subscription request is already waiting for review.',
                    ]);
                }

                $path = $request->file('payment_proof')->store('subscription-proofs/'.$businessId, 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['payment_proof' => 'The proof image could not be saved. Please try again.']);
                }

                $business->subscriptionRequests()->create([
                    'requested_by' => $request->user()->id,
                    'plan_tier' => $data['plan_tier'],
                    'payment_method' => $data['payment_method'],
                    'payment_reference' => $data['payment_reference'] ?? null,
                    'owner_note' => $data['owner_note'] ?? null,
                    'proof_path' => $path,
                    'status' => 'pending',
                ]);
            });
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('owner.subscriptions.index')
            ->with('success', 'Subscription request sent for Super Admin review. Your current plan stays unchanged until approval.');
    }

    public function proof(Request $request, SubscriptionRequest $subscriptionRequest)
    {
        abort_unless((int) $subscriptionRequest->business_id === (int) $request->user()->business_id, 404);
        abort_unless(Storage::disk('local')->exists($subscriptionRequest->proof_path), 404);

        return Storage::disk('local')->response($subscriptionRequest->proof_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
