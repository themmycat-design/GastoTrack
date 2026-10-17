<?php

namespace App\Http\Controllers;

use App\Models\BugReport;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BugReportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(BugReport::STATUSES)],
            'priority' => ['nullable', Rule::in(BugReport::PRIORITIES)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = BugReport::with(['business', 'reporter']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority')->toString());
        }
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search): void {
                $builder->where('subject', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('business', fn ($business) => $business->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('reporter', fn ($reporter) => $reporter->where('name', 'like', "%{$search}%"));
            });
        }

        $reports = $query->latest()->paginate(20)->withQueryString();
        $counts = [
            'all' => BugReport::count(),
            'open' => BugReport::where('status', 'open')->count(),
            'under_review' => BugReport::where('status', 'under_review')->count(),
            'resolved' => BugReport::where('status', 'resolved')->count(),
            'dismissed' => BugReport::where('status', 'dismissed')->count(),
        ];

        return view('super-admin.bugs.index', compact('reports', 'counts'));
    }

    public function show(BugReport $bugReport)
    {
        $bugReport->load('business', 'reporter');

        return view('super-admin.bugs.show', ['report' => $bugReport]);
    }

    public function edit(BugReport $bugReport)
    {
        return view('super-admin.bugs.form', [
            'report' => $bugReport,
            'businesses' => Business::withTrashed()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, BugReport $bugReport)
    {
        $data = $request->validate([
            'business_id' => ['required', Rule::exists('businesses', 'id')],
            'subject' => ['required', 'string', 'max:255'],
            'area' => ['required', Rule::in(BugReport::AREAS)],
            'description' => ['required', 'string', 'max:5000'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(BugReport::PRIORITIES)],
            'status' => ['required', Rule::in(BugReport::STATUSES)],
            'public_update' => ['nullable', 'string', 'max:5000'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $data['resolved_at'] = in_array($data['status'], ['resolved', 'dismissed'], true)
            ? ($bugReport->resolved_at ?? now())
            : null;

        $oldPath = $bugReport->screenshot_path;
        $newPath = $request->file('screenshot')?->store('bug-reports/'.$bugReport->business_id, 'local');
        if ($newPath) {
            $data['screenshot_path'] = $newPath;
        }

        try {
            $bugReport->update($data);
        } catch (Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return redirect()->route('super-admin.bug-reports.show', $bugReport)
            ->with('success', 'Bug report updated.');
    }

    public function destroy(BugReport $bugReport)
    {
        $screenshotPath = $bugReport->screenshot_path;
        $bugReport->delete();

        if ($screenshotPath) {
            Storage::disk('local')->delete($screenshotPath);
        }

        return redirect()->route('super-admin.bug-reports.index')
            ->with('success', 'Bug report deleted.');
    }

    public function screenshot(BugReport $bugReport)
    {
        abort_unless($bugReport->screenshot_path && Storage::disk('local')->exists($bugReport->screenshot_path), 404);

        return Storage::disk('local')->response($bugReport->screenshot_path, null, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
