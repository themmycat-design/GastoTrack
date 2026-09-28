<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
            'password'      => 'required|string|min:6|confirmed',
            'business_name' => 'required|string|max:255',
            'role'          => 'sometimes|in:owner,staff',
        ]);

        // Create business first
        $business = Business::create([
            'owner_id' => 0, // temp, updated below
            'name'     => $request->business_name,
            'location' => 'Calasiao, Pangasinan',
            'status'   => 'active',
        ]);

        // Create user
        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'role'        => $request->role ?? 'staff',
            'business_id' => $business->id,
        ]);

        // Update business owner
        $business->update(['owner_id' => $user->id]);

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