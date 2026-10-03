<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
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
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('assignedOwner', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
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
        return view('super-admin.business-form', ['business' => new Business()]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateBusiness($request);
        $ownerData = $request->validate([
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $business = DB::transaction(function () use ($validated, $ownerData) {
            $validated['active'] = $validated['status'] === 'active';
            $business = Business::create($validated);
            $owner = User::create([
                'name' => $ownerData['owner_name'],
                'email' => $ownerData['owner_email'],
                'phone' => $ownerData['owner_phone'] ?? null,
                'password' => Hash::make($ownerData['password']),
                'role' => 'owner',
                'status' => 'active',
                'business_id' => $business->id,
                'email_verified_at' => now(),
            ]);
            $business->update(['owner_id' => $owner->id]);

            return $business;
        });

        return redirect()->route('super-admin.businesses.show', $business)
            ->with('success', 'Business and owner account created.');
    }

    public function edit(Business $business)
    {
        $business->load('owner', 'assignedOwner');

        return view('super-admin.business-form', compact('business'));
    }

    public function update(Request $request, Business $business)
    {
        $validated = $this->validateBusiness($request);
        $owner = $business->owner_account;
        $ownerData = $request->validate([
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($owner?->id)],
            'owner_phone' => ['nullable', 'string', 'max:50'],
        ]);

        DB::transaction(function () use ($business, $validated, $owner, $ownerData) {
            $validated['active'] = $validated['status'] === 'active';
            $business->update($validated);
            $owner?->update([
                'name' => $ownerData['owner_name'],
                'email' => $ownerData['owner_email'],
                'phone' => $ownerData['owner_phone'] ?? null,
            ]);
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

    /**
     * Show business details
     */
    public function show(Business $business)
    {
        $business->load('owner', 'assignedOwner');
        $staffCount = $business->users()->where('role', 'staff')->count();

        return view('super-admin.show', compact('business', 'staffCount'));
    }

    /**
     * Approve a business
     */
    public function approve(Business $business)
    {
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
