<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminActivityRecorder;
use App\Services\AdminPasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformUserController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['super_admin', 'owner', 'staff'])],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'sort' => ['nullable', Rule::in(['name', 'email', 'role', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $query = User::with('business');
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('role', 'like', "%{$term}%")
                    ->orWhereHas('business', fn ($business) => $business->where('name', 'like', "%{$term}%"));
            });
        }
        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (! empty($filters['business_id'])) {
            $query->where('business_id', $filters['business_id']);
        }

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';
        $users = $query->orderBy($sort, $direction)->orderBy('id')->paginate(20)->withQueryString();

        return view('super-admin.users.index', compact('users'));
    }

    public function show(User $user)
    {
        $user->load('business');

        return view('super-admin.users.show', compact('user'));
    }

    public function sendPasswordReset(User $user, AdminActivityRecorder $audit, AdminPasswordResetService $reset)
    {
        abort_if($user->is(request()->user()), 422, 'Use your account settings to change your own password.');
        abort_if($user->isOwner() && ! $user->hasVerifiedEmail(), 422, 'The owner must verify their email first.');
        $status = $reset->forceReset($user);
        if ($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            $audit->record(request()->user(), 'user.password_reset_requested', 'user', $user->id);
        }

        return back()->with($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT ? 'success' : 'error', __($status));
    }

    public function lock(User $user, AdminActivityRecorder $audit)
    {
        abort_if($user->is(request()->user()), 422, 'You cannot lock your own account.');
        DB::transaction(function () use ($user, $audit): void {
            $user->update(['status' => 'inactive']);
            $user->tokens()->delete();
            $audit->record(request()->user(), 'user.locked', 'user', $user->id);
        });

        return back()->with('success', 'Account locked and mobile sessions revoked.');
    }

    public function unlock(User $user, AdminActivityRecorder $audit)
    {
        DB::transaction(function () use ($user, $audit): void {
            $user->update(['status' => 'active']);
            $audit->record(request()->user(), 'user.unlocked', 'user', $user->id);
        });

        return back()->with('success', 'Account unlocked. Business approval rules still apply.');
    }
}
