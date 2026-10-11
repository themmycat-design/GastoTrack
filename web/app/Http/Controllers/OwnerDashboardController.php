<?php

namespace App\Http\Controllers;

use App\Services\OwnerDashboardMetrics;
use Illuminate\Http\Request;

class OwnerDashboardController extends Controller
{
    public function __invoke(Request $request, OwnerDashboardMetrics $metrics)
    {
        return view('dashboard', $metrics->overview($request->user()));
    }
}
