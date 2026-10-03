<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\ViolationReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ViolationReportController extends Controller
{
    public function index(Request $request)
    {
        $query = ViolationReport::with('business', 'reporter');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('business', fn ($business) => $business->where('name', 'like', "%{$search}%"));
            });
        }

        $reports = $query->latest()->paginate(20)->withQueryString();
        $counts = [
            'all' => ViolationReport::count(),
            'open' => ViolationReport::where('status', 'open')->count(),
            'under_review' => ViolationReport::where('status', 'under_review')->count(),
            'resolved' => ViolationReport::where('status', 'resolved')->count(),
        ];

        return view('super-admin.violations.index', compact('reports', 'counts'));
    }

    public function create()
    {
        return view('super-admin.violations.form', [
            'report' => new ViolationReport(),
            'businesses' => Business::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateReport($request);
        $data['reported_by'] = $request->user()->id;
        $data['resolved_at'] = in_array($data['status'], ['resolved', 'dismissed'], true) ? now() : null;
        $report = ViolationReport::create($data);

        return redirect()->route('super-admin.violations.show', $report)
            ->with('success', 'Violation report created.');
    }

    public function show(ViolationReport $violation)
    {
        $violation->load('business', 'reporter');

        return view('super-admin.violations.show', ['report' => $violation]);
    }

    public function edit(ViolationReport $violation)
    {
        return view('super-admin.violations.form', [
            'report' => $violation,
            'businesses' => Business::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, ViolationReport $violation)
    {
        $data = $this->validateReport($request);
        $data['resolved_at'] = in_array($data['status'], ['resolved', 'dismissed'], true)
            ? ($violation->resolved_at ?? now())
            : null;
        $violation->update($data);

        return redirect()->route('super-admin.violations.show', $violation)
            ->with('success', 'Violation report updated.');
    }

    public function destroy(ViolationReport $violation)
    {
        $violation->delete();

        return redirect()->route('super-admin.violations.index')
            ->with('success', 'Violation report deleted.');
    }

    private function validateReport(Request $request): array
    {
        return $request->validate([
            'business_id' => ['required', Rule::exists('businesses', 'id')->whereNull('deleted_at')],
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(['complaint', 'policy_violation', 'fraud_risk', 'account_issue', 'other'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['required', Rule::in(['open', 'under_review', 'resolved', 'dismissed'])],
            'description' => ['required', 'string', 'max:5000'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
