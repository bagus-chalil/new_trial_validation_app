<?php

namespace App\Http\Controllers;

use App\Actions\Projects\DeleteProjectFile;
use App\Actions\Projects\StoreProjectFile;
use App\Http\Requests\Projects\StoreProjectFileRequest;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Support\ProjectOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload / download / delete of project files. Files are never reachable by a
 * public path: the `project_files` disk is private and `show()` streams
 * through the policy-gated route.
 */
class ProjectFileController extends Controller
{
    public function store(StoreProjectFileRequest $request, int $project, StoreProjectFile $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        try {
            $action(
                $project,
                $request->file('file'),
                (string) $request->input('context'),
                $request->filled('stage_id') ? (int) $request->input('stage_id') : null,
                $request->filled('quotation_id') ? (int) $request->input('quotation_id') : null,
                $request->filled('description') ? (string) $request->input('description') : null,
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.file_uploaded')]);

        return to_route('projects.show', $project);
    }

    public function show(int $project, int $file): StreamedResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);

        Gate::authorize('view', $project);

        $file = ProjectFile::where('project_id', $project->id)->findOrFail($file);
        $stored = basename($file->stored_name);
        $disk = Storage::disk('project_files');

        abort_unless($stored !== '' && ! $file->is_missing && $disk->exists($stored), 404);

        $extension = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
        $inlineType = ProjectOptions::INLINE_TYPES[$extension] ?? null;

        return $disk->response($stored, $file->file_name, [
            'Content-Type' => $inlineType ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], $inlineType ? 'inline' : 'attachment');
    }

    public function destroy(Request $request, int $project, int $file, DeleteProjectFile $action): RedirectResponse
    {
        $project = Project::whereNull('deleted_at')->findOrFail($project);
        $file = ProjectFile::where('project_id', $project->id)->findOrFail($file);

        Gate::authorize('update', $project);

        $action($project, $file, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.file_deleted')]);

        return to_route('projects.show', $project);
    }
}
