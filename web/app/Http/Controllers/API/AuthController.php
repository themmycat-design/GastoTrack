<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordStrength;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
                'email' => ['Incorrect email or password.'],
            ]);
        }

        abort_if($user->status !== 'active', 403, 'This account is inactive. Contact your business owner.');
        abort_if(!$user->isStaff(), 403, 'Only staff accounts can sign in through the GastoTrack mobile app.');

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
        abort_if(!$request->user()->isStaff(), 403, 'Only staff accounts can use mobile access tokens.');
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['token' => $request->user()->createToken('gastotrack-mobile')->plainTextToken]);
    }

    public function changePassword(Request $request)
    {
        abort_if(!$request->user()->isStaff(), 403, 'Only staff accounts can use mobile access tokens.');
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', new PasswordStrength()],
        ]);
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
