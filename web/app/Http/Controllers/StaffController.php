<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $businessId = $user->business_id;
        
        // Start query
        $query = User::where('business_id', $businessId);
        
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        
        // Get staff with pagination
        $staff = $query->orderBy('role')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
        
        // Get stats
        $totalStaff = User::where('business_id', $businessId)->where('role', 'staff')->count();
        $totalOwners = User::where('business_id', $businessId)->where('role', 'owner')->count();
        $totalUsers = User::where('business_id', $businessId)->count();
        
        return view('staff.index', compact('staff', 'totalStaff', 'totalOwners', 'totalUsers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:owner,staff'],
        ]);
        
        $user = auth()->user();
        
        User::create([
            'business_id' => $user->business_id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => 'active',
        ]);
        
        return redirect()->route('staff.index')
            ->with('success', 'User created successfully!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $staff)
    {
        // Ensure the user belongs to the same business
        if ($staff->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$staff->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'in:owner,staff'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);
        
        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
        ];
        
        // Only update password if provided
        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }
        
        $staff->update($updateData);
        
        return redirect()->route('staff.index')
            ->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $staff)
    {
        // Ensure the user belongs to the same business
        if ($staff->business_id !== auth()->user()->business_id) {
            abort(403);
        }
        
        // Prevent deleting yourself
        if ($staff->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 400);
        }
        
        $staff->delete();
        
        return response()->json(['success' => true]);
    }
}
