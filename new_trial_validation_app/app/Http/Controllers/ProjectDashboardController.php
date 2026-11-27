<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Projects\ProjectDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Project dashboard (the old Project Tracking home screen): KPIs, donuts,
 * priority reminders, needs-attention list and progress board, filterable by
 * a date range. See App\Services\Projects\ProjectDashboard for the rules.
 */
class ProjectDashboardController extends Controller
{
    public function index(Request $request, ProjectDashboard $dashboard): Response
    {
        Gate::authorize('viewAny', Project::class);

        $user = $request->user();
        $canFinance = Gate::forUser($user)->allows('access-finance');

        $data = $dashboard->build(
            $user,
            (string) $request->query('period', 'all'),
            $request->query('start') !== null ? (string) $request->query('start') : null,
            $request->query('end') !== null ? (string) $request->query('end') : null,
            (string) $request->query('reminder', 'week'),
            $canFinance,
        );

        return Inertia::render('projects/dashboard', $data + [
            'can' => ['finance' => $canFinance],
        ]);
    }
}
