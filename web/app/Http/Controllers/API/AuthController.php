<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PasswordStrength;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        abort_if($user->must_reset_password, 403, 'A password reset is required before signing in. Check your email.');
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
        abort_if($business->subscriptionNeedsRenewal(), 403, 'Your subscription has expired or is suspended. Contact the platform administrator to renew.');

        // Delete old tokens and create new one
        $user->tokens()->delete();
        $token = $user->createToken('gastotrack-mobile', ['*'], now()->addDays(30))->plainTextToken;

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

    public function updateProfile(Request $request)
    {
        abort_unless($request->user()->isStaff(), 403, 'Only staff accounts can update a mobile profile.');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'profile_photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $user = $request->user();
        $oldPhotoPath = $user->profile_photo_path;
        $newPhotoPath = $request->hasFile('profile_photo')
            ? $request->file('profile_photo')->store('profile-photos/'.$user->id, 'local')
            : null;

        abort_unless(!$request->hasFile('profile_photo') || $newPhotoPath, 503, 'The profile photo could not be saved. Please try again.');

        try {
            $user->name = $validated['name'];
            $user->phone = $validated['phone'] ?? null;
            if ($newPhotoPath) {
                $user->profile_photo_path = $newPhotoPath;
            }
            $user->save();
        } catch (Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('local')->delete($newPhotoPath);
            }
            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath) {
            Storage::disk('local')->delete($oldPhotoPath);
        }

        return response()->json([
            'message' => 'Personal information updated',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function profilePhoto(Request $request)
    {
        abort_unless($request->user()->isStaff(), 403, 'Only staff accounts can access a mobile profile photo.');

        $path = $request->user()->profile_photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    public function refresh(Request $request)
    {
        abort_if(!$request->user()->isStaff(), 403, 'Only staff accounts can use mobile access tokens.');
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['token' => $request->user()->createToken('gastotrack-mobile', ['*'], now()->addDays(30))->plainTextToken]);
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
        return response()->json(['message' => 'Password changed', 'token' => $request->user()->createToken('gastotrack-mobile', ['*'], now()->addDays(30))->plainTextToken]);
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
            'phone'       => $user->phone,
            'role'        => $user->role,
            'business_id' => $user->business_id,
            'has_profile_photo' => (bool) $user->profile_photo_path,
            'profile_photo_version' => $user->updated_at?->timestamp,
        ];
    }
}
