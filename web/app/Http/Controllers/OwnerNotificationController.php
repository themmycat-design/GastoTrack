<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class OwnerNotificationController extends Controller
{
    public function readAll(Request $request)
    {
        $user = $request->user();

        Notification::where('user_id', $user->id)
            ->where('business_id', $user->business_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Notifications marked as read.',
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', 'Notifications marked as read.');
    }
}
