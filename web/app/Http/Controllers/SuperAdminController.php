<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use App\Rules\PasswordStrength;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    /**
     * Display the super admin dashboard
     */
    public function dashboard()
    {
        $totalBusinesses = Business::count();
        $activeBusinesses = Business::where('status', 'active')->count();
        $pendingBusinesses = Business::where('status', 'pending')->count();
        $suspendedBusinesses = Business::where('status', 'suspended')->count();

        $recentBusinesses = Business::with('owner', 'assignedOwner')
            ->withCount(['users as staff_count' => fn ($query) => $query->where('role', 'staff')])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('super-admin.dashboard', compact(
            'totalBusinesses',
            'activeBusinesses',
            'pendingBusinesses',
            'suspendedBusinesses',
            'recentBusinesses'
        ));
    }

    /**
     * Display all businesses
     */
    public function businesses(Request $request)
    {
        $query = ($request->boolean('archived') ? Business::onlyTrashed() : Business::query())
            ->with('owner', 'assignedOwner')
            ->withCount(['users as staff_count' => fn ($staffQuery) => $staffQuery->where('role', 'staff')]);
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('owner', function($q) use ($search) {
                      $q->whereNotNull('email_verified_at')
                        ->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('assignedOwner', function($q) use ($search) {
                      $q->whereNotNull('email_verified_at')
                        ->where('name', 'like', "%{$search}%");
                  });
            });
        }
        
        $businesses = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $allCount = Business::count();
        $pendingCount = Business::where('status', 'pending')->count();
        $activeCount = Business::where('status', 'active')->count();
        $suspendedCount = Business::where('status', 'suspended')->count();
        $inactiveCount = Business::where('status', 'inactive')->count();
        $archivedCount = Business::onlyTrashed()->count();

        return view('super-admin.businesses', compact(
            'businesses',
            'allCount',
            'pendingCount',
            'activeCount',
            'suspendedCount',
            'inactiveCount',
            'archivedCount'
        ));
    }

    public function create()
    {
        return view('super-admin.business-form', [
            'business' => new Business(),
            'ownerAccountForForm' => null,
            'ownerDetailsHidden' => false,
            'ownerEmailVerified' => false,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateBusiness($request);
        $ownerData = $request->validate([
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', new PasswordStrength()],
        ]);

        [$business, $owner] = DB::transaction(function () use ($validated, $ownerData) {
            // A newly created owner must verify their email before the business
            // can be approved, regardless of the status selected in the form.
            if ($validated['status'] === 'active') {
                $validated['status'] = 'pending';
            }
            $validated['active'] = false;
            $business = Business::create($validated);
            $owner = User::create([
                'name' => $ownerData['owner_name'],
                'email' => $ownerData['owner_email'],
                'phone' => $ownerData['owner_phone'] ?? null,
                'password' => Hash::make($ownerData['password']),
                'role' => 'owner',
                'status' => 'active',
                'business_id' => $business->id,
            ]);
            $business->update(['owner_id' => $owner->id]);

            return [$business, $owner];
        });

        event(new Registered($owner));

        return redirect()->route('super-admin.businesses.show', $business)
            ->with('success', 'Business and owner account created. The owner must verify their email before approval.');
    }

    public function edit(Business $business)
    {
        $business->load('owner', 'assignedOwner');
        $owner = $business->owner_account;
        $ownerEmailVerified = $owner?->hasVerifiedEmail() ?? false;
        $ownerDetailsHidden = $owner !== null && ! $owner->hasVerifiedEmail();
        $ownerAccountForForm = $ownerDetailsHidden ? null : $owner;

        return view('super-admin.business-form', compact('business', 'ownerAccountForForm', 'ownerDetailsHidden', 'ownerEmailVerified'));
    }

    public function update(Request $request, Business $business)
    {
        $validated = $this->validateBusiness($request);
        $owner = $business->owner_account;
        $ownerEmailVerified = $owner?->hasVerifiedEmail() ?? false;
        $ownerData = $ownerEmailVerified ? $request->validate([
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($owner->id)],
            'owner_phone' => ['nullable', 'string', 'max:50'],
        ]) : [];

        $ownerEmailChanging = $ownerEmailVerified && $request->input('owner_email') !== $owner->email;

        if ($validated['status'] === 'active' && $business->status !== 'active' && (! $ownerEmailVerified || $ownerEmailChanging)) {
            return back()->withInput()->with('error', 'The business owner must verify their email before this business can be activated.');
        }

        DB::transaction(function () use ($business, $validated, $owner, $ownerData, $ownerEmailVerified) {
            $validated['active'] = $validated['status'] === 'active';
            $business->update($validated);

            if ($owner && $ownerEmailVerified) {
                $emailChanged = $owner->email !== $ownerData['owner_email'];
                $owner->update([
                    'name' => $ownerData['owner_name'],
                    'email' => $ownerData['owner_email'],
                    'phone' => $ownerData['owner_phone'] ?? null,
                ]);

                if ($emailChanged) {
                    $owner->forceFill(['email_verified_at' => null])->save();
                }
            }
        });

        if ($business->status !== 'active') {
            $this->revokeBusinessTokens($business);
        }

        return redirect()->route('super-admin.businesses.show', $business)
            ->with('success', 'Business registration details updated.');
    }

    public function destroy(Business $business)
    {
        $business->update([
            'status' => 'inactive',
            'active' => false,
            'suspension_reason' => 'Archived by a platform administrator.',
        ]);
        $this->revokeBusinessTokens($business);
        $business->delete();

        return redirect()->route('super-admin.businesses')
            ->with('success', 'Business archived. Its private records were preserved.');
    }

    public function restore(int $business)
    {
        $record = Business::onlyTrashed()->findOrFail($business);
        $record->restore();
        $record->update(['status' => 'inactive', 'active' => false]);

        return redirect()->route('super-admin.businesses.show', $record)
            ->with('success', 'Business restored as inactive. Review it before reactivation.');
    }

    public function forceDestroy(int $business)
    {
        $record = Business::onlyTrashed()->findOrFail($business);

        DB::transaction(function () use ($record): void {
            $this->revokeBusinessTokens($record);

            // These audit tables restrict business deletion; remove only this
            // tenant's rows so the rest of the business data can cascade away.
            DB::table('order_payment_events')->where('business_id', $record->id)->delete();
            $bugScreenshotPaths = DB::table('bug_reports')
                ->where('business_id', $record->id)
                ->whereNotNull('screenshot_path')
                ->pluck('screenshot_path');
            DB::table('bug_reports')->where('business_id', $record->id)->delete();

            // Clear restrictive child references before the business cascade
            // removes its products, orders, and stock items.
            $transactionIds = DB::table('transactions')->where('business_id', $record->id)->pluck('id');
            DB::table('transactions')
                ->where('business_id', $record->id)
                ->whereIn('reversal_of_id', $transactionIds)
                ->update(['reversal_of_id' => null]);
            DB::table('transactions')->whereIn('id', $transactionIds)->delete();

            $orderIds = DB::table('orders')->where('business_id', $record->id)->pluck('id');
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();

            $stockItemIds = DB::table('stock_items')->where('business_id', $record->id)->pluck('id');
            if ($stockItemIds->isNotEmpty()) {
                $movementIds = DB::table('stock_movements')
                    ->whereIn('stock_item_id', $stockItemIds)
                    ->pluck('id');

                DB::table('stock_movements')
                    ->whereIn('stock_item_id', $stockItemIds)
                    ->whereIn('reversal_of_id', $movementIds)
                    ->update(['reversal_of_id' => null]);
                DB::table('stock_movements')->whereIn('id', $movementIds)->delete();
            }

            // Tenant-owned users, orders, inventory, and financial records are
            // removed by their configured foreign-key cascades.
            $record->forceDelete();

            DB::afterCommit(fn () => Storage::disk('local')->delete($bugScreenshotPaths->all()));
        });

        return redirect()->route('super-admin.businesses', ['archived' => 1])
            ->with('success', 'The archived business and its associated records were permanently deleted.');
    }

    /**
     * Show business details
     */
    public function show(Business $business)
    {
        $business->load('owner', 'assignedOwner');
        $owner = $business->owner_account;
        $ownerEmailVerified = $owner?->hasVerifiedEmail() ?? false;
        $ownerAccountExists = $owner !== null;
        $ownerDetailsHidden = $ownerAccountExists && ! $ownerEmailVerified;
        $ownerAccountForDisplay = $ownerEmailVerified ? $owner : null;
        $staffCount = $business->users()->where('role', 'staff')->count();

        return view('super-admin.show', compact(
            'business',
            'staffCount',
            'ownerEmailVerified',
            'ownerAccountExists',
            'ownerDetailsHidden',
            'ownerAccountForDisplay',
        ));
    }

    /**
     * Approve a business
     */
    public function approve(Business $business)
    {
        if (! ($business->owner_account?->hasVerifiedEmail() ?? false)) {
            return redirect()->back()->with('error', 'The business owner must verify their email before this business can be approved.');
        }

        $business->update([
            'status' => 'active',
            'active' => true,
            'suspension_reason' => null,
        ]);
        
        return redirect()->back()->with('success', 'Business approved successfully!');
    }

    /**
     * Suspend a business
     */
    public function suspend(Request $request, Business $business)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);
        
        $business->update([
            'status' => 'suspended',
            'active' => false,
            'suspension_reason' => $request->reason,
        ]);

        $this->revokeBusinessTokens($business);
        
        return redirect()->back()->with('success', 'Business suspended successfully!');
    }

    /**
     * Reactivate a business
     */
    public function reactivate(Business $business)
    {
        if (! ($business->owner_account?->hasVerifiedEmail() ?? false)) {
            return redirect()->back()->with('error', 'The business owner must verify their email before this business can be reactivated.');
        }

        $business->update([
            'status' => 'active',
            'active' => true,
            'suspension_reason' => null,
        ]);
        
        return redirect()->back()->with('success', 'Business reactivated successfully!');
    }

    /**
     * Deactivate a business permanently
     */
    public function deactivate(Request $request, Business $business)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);
        
        $business->update([
            'status' => 'inactive',
            'active' => false,
            'suspension_reason' => $request->reason,
        ]);

        $this->revokeBusinessTokens($business);
        
        return redirect()->back()->with('success', 'Business deactivated successfully!');
    }

    public function activateOwner(Business $business)
    {
        $owner = $business->owner_account;
        abort_unless($owner, 404, 'This business has no owner account.');

        $owner->update(['status' => 'active']);

        return back()->with('success', 'Business owner account activated.');
    }

    public function deactivateOwner(Business $business)
    {
        $owner = $business->owner_account;
        abort_unless($owner, 404, 'This business has no owner account.');

        $owner->update(['status' => 'inactive']);
        $owner->tokens()->delete();

        return back()->with('success', 'Business owner account deactivated.');
    }

    public function sendOwnerPasswordReset(Business $business)
    {
        $owner = $business->owner_account;
        abort_unless($owner, 404, 'This business has no owner account.');

        $status = Password::sendResetLink(['email' => $owner->email]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', __($status))
            : back()->with('error', __($status));
    }

    private function revokeBusinessTokens(Business $business): void
    {
        $business->users()->each(fn ($user) => $user->tokens()->delete());
    }

    private function validateBusiness(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', Rule::in(['restaurant', 'cafe', 'retail', 'service', 'other'])],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['pending', 'active', 'suspended', 'inactive'])],
            'suspension_reason' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
