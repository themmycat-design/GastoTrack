<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\User;
use App\Models\BugReport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformReportController extends Controller
{
    public function index()
    {
        $statusCounts = Business::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        $businessesByStatus = $statusCounts->keys()->mapWithKeys(fn ($status) => [
            $status => Business::query()
                ->where('status', $status)
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name']),
        ]);
        $typeCounts = Business::selectRaw('business_type, COUNT(*) as total')
            ->groupBy('business_type')->orderByDesc('total')->pluck('total', 'business_type');
        $locations = Business::whereNotNull('city')
            ->selectRaw('city, province, COUNT(*) as total')
            ->groupBy('city', 'province')->orderByDesc('total')->limit(10)->get();
        $registrationTrend = Business::where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->get(['created_at'])
            ->groupBy(fn ($business) => $business->created_at->format('Y-m'))
            ->map->count();

        $summary = [
            'businesses' => Business::count(),
            'staff' => User::where('role', 'staff')->count(),
            'new_this_month' => Business::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'open_bugs' => BugReport::whereIn('status', ['open', 'under_review'])->count(),
        ];

        return view('super-admin.platform-reports', compact(
            'summary', 'statusCounts', 'businessesByStatus', 'typeCounts', 'locations', 'registrationTrend'
        ));
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Business', 'Type', 'Owner', 'Location', 'Status', 'Staff Count', 'Registration Date']);
            // Stream in bounded batches; a platform export must not load every tenant into memory.
            $businesses = Business::with('owner', 'assignedOwner')
                ->withCount(['users as staff_count' => fn ($query) => $query->where('role', 'staff')])
                ->orderBy('id')->lazy(500);
            foreach ($businesses as $business) {
                $row = [
                    $business->name,
                    $business->business_type,
                    $business->owner_account?->name,
                    collect([$business->city, $business->province])->filter()->join(', '),
                    $business->status,
                    $business->staff_count,
                    $business->created_at->toDateString(),
                ];
                // Spreadsheet applications execute leading formula characters.
                fputcsv($output, array_map(static function ($value) {
                    if (!is_string($value)) return $value;
                    return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value) ? "'".$value : $value;
                }, $row));
            }
            fclose($output);
        }, 'gastotrack-platform-report-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
