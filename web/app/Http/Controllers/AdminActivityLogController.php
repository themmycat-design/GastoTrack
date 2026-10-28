<?php

namespace App\Http\Controllers;

use App\Models\AdminActivityLog;

class AdminActivityLogController extends Controller
{
    public function index()
    {
        $logs = AdminActivityLog::with('admin:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30);

        return view('super-admin.audit-logs', compact('logs'));
    }
}
