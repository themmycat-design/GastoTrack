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

    public function destroy(Request $request, int $notification)
    {
        $user = $request->user();
        $item = Notification::query()
            ->whereKey($notification)
            ->where('user_id', $user->id)
            ->where('business_id', $user->business_id)
            ->whereNull('data->dismissed_at')
            ->firstOrFail();

        // Keep a dismissed record so an unresolved stock or goal alert is not recreated.
        $item->update([
            'read_at' => $item->read_at ?? now(),
            'data' => array_merge($item->data ?? [], ['dismissed_at' => now()->toIso8601String()]),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Notification deleted.',
                'unread_count' => Notification::query()
                    ->where('user_id', $user->id)
                    ->where('business_id', $user->business_id)
                    ->whereNull('data->dismissed_at')
                    ->whereNull('read_at')
                    ->count(),
            ]);
        }

        return back()->with('success', 'Notification deleted.');
    }
}
