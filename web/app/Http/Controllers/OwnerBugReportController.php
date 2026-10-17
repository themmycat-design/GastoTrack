<?php

namespace App\Http\Controllers;

use App\Models\BugReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class OwnerBugReportController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;
        $reports = BugReport::where('business_id', $businessId)
            ->with('reporter:id,name')
            ->latest()
            ->paginate(15);

        $counts = [
            'total' => BugReport::where('business_id', $businessId)->count(),
            'open' => BugReport::where('business_id', $businessId)->where('status', 'open')->count(),
            'under_review' => BugReport::where('business_id', $businessId)->where('status', 'under_review')->count(),
            'resolved' => BugReport::where('business_id', $businessId)->where('status', 'resolved')->count(),
        ];

        return view('bugs.index', compact('reports', 'counts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'area' => ['required', Rule::in(BugReport::AREAS)],
            'description' => ['required', 'string', 'max:5000'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:5000'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $path = $request->file('screenshot')?->store('bug-reports/'.$user->business_id, 'local');

        try {
            BugReport::create([
                'business_id' => $user->business_id,
                'reported_by' => $user->id,
                'subject' => $data['subject'],
                'category' => 'other',
                'area' => $data['area'],
                'description' => $data['description'],
                'steps_to_reproduce' => $data['steps_to_reproduce'] ?? null,
                'screenshot_path' => $path,
                'priority' => 'medium',
                'status' => 'open',
                'is_legacy' => false,
            ]);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('owner.bug-reports.index')
            ->with('success', 'Bug report submitted. You can follow its status below.');
    }

    public function screenshot(Request $request, BugReport $bugReport)
    {
        abort_unless((int) $bugReport->business_id === (int) $request->user()->business_id, 404);
        abort_unless($bugReport->screenshot_path && Storage::disk('local')->exists($bugReport->screenshot_path), 404);

        return Storage::disk('local')->response($bugReport->screenshot_path, null, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
