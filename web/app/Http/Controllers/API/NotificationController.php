<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $query = Notification::where('user_id', $request->user()->id)->latest();
        if ($request->boolean('unread')) $query->whereNull('read_at');
        return response()->json(['notifications' => $query->paginate(20), 'unread_count' => Notification::where('user_id', $request->user()->id)->whereNull('read_at')->count()]);
    }
    public function read(Request $request, $id) { $item = Notification::where('user_id', $request->user()->id)->findOrFail($id); $item->update(['read_at' => now()]); return response()->json(['notification' => $item]); }
    public function readAll(Request $request) { Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]); return response()->json(['message' => 'All notifications marked as read']); }
    public function destroy(Request $request, $id) { Notification::where('user_id', $request->user()->id)->findOrFail($id)->delete(); return response()->json(['message' => 'Notification deleted']); }
}
