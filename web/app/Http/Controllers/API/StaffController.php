<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffController extends Controller
{
    private function owner(Request $request): void { abort_unless($request->user()->isOwner(), 403); }
    public function index(Request $request) { $this->owner($request); return response()->json(['staff' => User::where('business_id', $request->user()->business_id)->where('role', 'staff')->get()]); }
    public function show(Request $request, $id) { $this->owner($request); return response()->json(['staff' => User::where('business_id', $request->user()->business_id)->where('role', 'staff')->findOrFail($id)]); }
    public function store(Request $request)
    {
        $this->owner($request); $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'phone' => 'nullable|string|max:30', 'password' => 'required|string|min:8|confirmed']);
        $staff = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'staff',
            'business_id' => $request->user()->business_id,
            'status' => 'active',
        ]);
        return response()->json(['message' => 'Staff created', 'staff' => $staff], 201);
    }
    public function destroy(Request $request, $id)
    {
        $this->owner($request); $staff = User::where('business_id', $request->user()->business_id)->where('role', 'staff')->findOrFail($id);
        $staff->tokens()->delete(); $staff->delete(); return response()->json(['message' => 'Staff removed']);
    }
    public function activity(Request $request, $id)
    {
        $this->owner($request); User::where('business_id', $request->user()->business_id)->where('role', 'staff')->findOrFail($id);
        return response()->json(ActivityLog::where('business_id', $request->user()->business_id)->where('user_id', $id)->latest()->paginate(20));
    }
}
