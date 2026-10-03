<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    // ----------------------------------------------------------------
    // Register — creates user + business in one step
    // ----------------------------------------------------------------
    public function register(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|string|min:8|confirmed',
            'business_name' => 'required|string|max:255',
        ]);

        [$user, $business] = DB::transaction(function () use ($request) {
            $business = Business::create(['name' => $request->business_name, 'status' => 'pending', 'active' => false]);
            $user = User::create(['name' => $request->name, 'email' => $request->email, 'password' => Hash::make($request->password), 'role' => 'owner', 'business_id' => $business->id, 'status' => 'active']);
            $business->update(['owner_id' => $user->id]);
            return [$user, $business];
        });

        $token = $user->createToken('gastotrack-mobile')->plainTextToken;

        return response()->json([
            'message'  => 'Registration successful',
            'user'     => $this->formatUser($user),
            'business' => $business,
            'token'    => $token,
        ], 201);
    }

    // ----------------------------------------------------------------
    // Login
    // ----------------------------------------------------------------
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email o password ay mali.'],
            ]);
        }

        abort_if($user->status !== 'active', 403, 'This account is inactive. Contact your business owner.');
        abort_if($user->isSuperAdmin(), 403, 'Super Administrators sign in through the desktop web portal.');

        $business = $user->business;
        abort_if(!$business, 403, 'This staff account is not assigned to a business.');

        if ($business->isPending()) {
            abort(403, 'Your staff account is active, but the business is awaiting Super Admin approval.');
        }

        if ($business->isSuspended()) {
            abort(403, $business->suspension_reason
                ? 'This business is suspended: '.$business->suspension_reason
                : 'This business is suspended. Contact support.');
        }

        abort_if($business->isInactive() || !$business->active, 403, 'This business is inactive.');

        // Delete old tokens and create new one
        $user->tokens()->delete();
        $token = $user->createToken('gastotrack-mobile')->plainTextToken;

        return response()->json([
            'message'  => 'Login successful',
            'user'     => $this->formatUser($user),
            'business' => $user->business,
            'token'    => $token,
        ]);
    }

    // ----------------------------------------------------------------
    // Logout
    // ----------------------------------------------------------------
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    // ----------------------------------------------------------------
    // Get authenticated user
    // ----------------------------------------------------------------
    public function user(Request $request)
    {
        return response()->json([
            'user'     => $this->formatUser($request->user()),
            'business' => $request->user()->business,
        ]);
    }

    public function refresh(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['token' => $request->user()->createToken('gastotrack-mobile')->plainTextToken]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => 'required|string|min:8|confirmed']);
        $request->user()->update(['password' => Hash::make($data['password'])]);
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Password changed', 'token' => $request->user()->createToken('gastotrack-mobile')->plainTextToken]);
    }

    // ----------------------------------------------------------------
    // Format user for response
    // ----------------------------------------------------------------
    private function formatUser(User $user)
    {
        return [
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'role'        => $user->role,
            'business_id' => $user->business_id,
        ];
    }
}
