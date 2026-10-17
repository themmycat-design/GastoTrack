<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class BugReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->staffUser($request);
        $page = BugReport::where('business_id', $user->business_id)
            ->where('reported_by', $user->id)
            ->where('is_legacy', false)
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 50));

        return response()->json([
            'bug_reports' => $page->getCollection()->map(fn (BugReport $report) => $this->present($report))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->staffUser($request);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'area' => ['required', Rule::in(BugReport::AREAS)],
            'description' => ['required', 'string', 'max:5000'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:5000'],
            'screenshot' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $request->file('screenshot')?->store('bug-reports/'.$user->business_id, 'local');

        try {
            $report = BugReport::create([
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

        return response()->json([
            'message' => 'Bug report submitted.',
            'bug_report' => $this->present($report),
        ], 201);
    }

    public function show(Request $request, int $bugReport)
    {
        $report = $this->staffReport($request, $bugReport);

        return response()->json(['bug_report' => $this->present($report)]);
    }

    public function screenshot(Request $request, int $bugReport)
    {
        $report = $this->staffReport($request, $bugReport);
        abort_unless($report->screenshot_path && Storage::disk('local')->exists($report->screenshot_path), 404);

        return Storage::disk('local')->response($report->screenshot_path, null, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function staffUser(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isStaff(), 403, 'Only staff accounts can use the mobile bug-report API.');

        return $user;
    }

    private function staffReport(Request $request, int $id): BugReport
    {
        $user = $this->staffUser($request);

        return BugReport::where('business_id', $user->business_id)
            ->where('reported_by', $user->id)
            ->where('is_legacy', false)
            ->findOrFail($id);
    }

    private function present(BugReport $report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->subject,
            'area' => $report->area,
            'description' => $report->description,
            'steps_to_reproduce' => $report->steps_to_reproduce,
            'priority' => $report->priority,
            'status' => $report->status,
            'public_update' => $report->public_update,
            'has_screenshot' => $report->has_screenshot,
            'created_at' => $report->created_at?->toISOString(),
        ];
    }
}
