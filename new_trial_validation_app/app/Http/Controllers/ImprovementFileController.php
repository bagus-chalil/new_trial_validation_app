<?php

namespace App\Http\Controllers;

use App\Actions\Improvements\DeleteImprovementFile;
use App\Actions\Improvements\StoreImprovementFile;
use App\Http\Requests\Improvements\StoreImprovementFileRequest;
use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Support\ProjectOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Improvement attachments: private disk, policy-gated download (same rules as project files). */
class ImprovementFileController extends Controller
{
    public function store(StoreImprovementFileRequest $request, int $improvement, StoreImprovementFile $action): RedirectResponse
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);

        try {
            $action(
                $improvement,
                $request->file('file'),
                $request->filled('description') ? (string) $request->input('description') : null,
                $request->user(),
                $request->input('phase') === 'after' && $request->user()->can('review', $improvement) ? 'after' : 'before',
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.file_uploaded')]);

        return to_route('projects.improvements.show', $improvement);
    }

    public function show(Request $request, int $improvement, int $file): StreamedResponse
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);

        Gate::authorize('view', $improvement);

        $file = ImprovementFile::where('improvement_id', $improvement->id)->findOrFail($file);
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

    public function destroy(Request $request, int $improvement, int $file, DeleteImprovementFile $action): RedirectResponse
    {
        $improvement = Improvement::whereNull('deleted_at')->findOrFail($improvement);
        $file = ImprovementFile::where('improvement_id', $improvement->id)->findOrFail($file);

        $user = $request->user();
        abort_unless($user->can('update', $improvement) || $user->can('review', $improvement), 403);

        $action($improvement, $file, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.file_deleted')]);

        return to_route('projects.improvements.show', $improvement);
    }
}
