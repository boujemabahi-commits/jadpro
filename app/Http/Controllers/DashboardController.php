<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Support\Analytics;

class DashboardController extends Controller
{
    public function index()
    {
        // Keep unpaid badges current before counting them.
        Enrollment::rolloverDue();

        $user = auth()->user();
        // Money (revenue, expenses, unpaid students) is only shown to staff allowed to see
        // finances; enrollments only to staff who handle students/enrollments. Data that isn't
        // shown is not computed either, so it never reaches the page (not even in the chart JS).
        $canFinance = $user->can('view-reports') || $user->can('manage-payments');
        $canEnrollments = $user->can('manage-enrollments') || $user->can('manage-students');

        $stats = collect(Analytics::stats())
            ->reject(fn ($s) => ($s['key'] ?? null) === 'unpaid' && ! $canFinance)
            ->values()->all();

        return view('dashboard.index', [
            'stats' => $stats,
            'revenue' => $user->can('view-reports') ? Analytics::revenueByMonth(6) : null,
            'growth' => Analytics::studentGrowthByMonth(6),
            'recentEnrollments' => $canEnrollments ? Analytics::recentEnrollments(5) : null,
            'upcoming' => Analytics::upcomingClassesToday(4),
        ]);
    }
}
