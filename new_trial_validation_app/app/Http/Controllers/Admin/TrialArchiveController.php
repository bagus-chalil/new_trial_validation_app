<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trial;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Archive — a separate, non-deletion concept from Trash/soft-delete
 * (TrashController), ported per direct user request (2026-10-06) mirroring
 * ipc_app's BatchArchiveController: hides a finished (Approved/Rejected)
 * trial from the normal status lists/dashboard (see
 * Trial::scopeVisibleTo()'s archived_at exclusion) without removing
 * anything — the trial, its Report Summary, and every other page keep
 * working normally; only this admin screen and the main lists stop showing
 * it. Gated the same way as Trash/Notifications/Activity Logs
 * (`manage-settings`, isAdmin() only).
 */
class TrialArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('manage-settings');

        $search = trim((string) $request->query('q', ''));

        $trials = Trial::query()
            ->whereNotNull('archived_at')
            ->whereNull('deleted_at')
            ->with(['archivedByUser:id,name,email'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function (Builder $q) use ($like) {
                    $q->where('trial_code', 'like', $like)
                        ->orWhere('product_name', 'like', $like)
                        ->orWhere('product_type', 'like', $like);
                });
            })
            ->orderByDesc('archived_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/archive/index', [
            'trials' => $trials,
            'filters' => ['q' => $search],
        ]);
    }

    public function store(Trial $trial): RedirectResponse
    {
        Gate::authorize('archive', $trial);

        $trial->archived_at = Carbon::now();
        $trial->archived_by = request()->user()->id;
        $trial->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('master_data.toast.trial_archived', ['code' => $trial->trial_code]),
        ]);

        return back();
    }

    public function destroy(Trial $trial): RedirectResponse
    {
        Gate::authorize('unarchive', $trial);

        $trial->archived_at = null;
        $trial->archived_by = null;
        $trial->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('master_data.toast.trial_unarchived', ['code' => $trial->trial_code]),
        ]);

        return to_route('admin.archive.index');
    }
}
