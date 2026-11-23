<?php

namespace App\Http\Controllers;

use App\Actions\Toolings\ToolingFiles;
use App\Models\ProjectFile;
use App\Models\Tooling;
use App\Support\ProjectOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Check Tooling attachments: private disk, policy-gated download (same rules as project files). */
class ToolingFileController extends Controller
{
    public function store(Request $request, int $tooling, ToolingFiles $files): RedirectResponse
    {
        $tooling = Tooling::whereNull('deleted_at')->where('id', $tooling)->firstOrFail();
        Gate::authorize('attach', $tooling);

        $request->validate([
            'file' => ['required', 'file'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $files->store($tooling, $request->file('file'), $request->filled('description') ? (string) $request->input('description') : null, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.file_uploaded')]);

        return to_route('projects.toolings.show', $tooling);
    }

    public function show(Request $request, int $tooling, int $file): StreamedResponse
    {
        $tooling = Tooling::whereNull('deleted_at')->where('id', $tooling)->firstOrFail();
        Gate::authorize('view', $tooling);

        $file = ProjectFile::where('tooling_id', $tooling->id)->where('id', $file)->firstOrFail();
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

    public function destroy(Request $request, int $tooling, int $file, ToolingFiles $files): RedirectResponse
    {
        $tooling = Tooling::whereNull('deleted_at')->where('id', $tooling)->firstOrFail();
        Gate::authorize('deleteFile', $tooling);

        $file = ProjectFile::where('tooling_id', $tooling->id)->where('id', $file)->firstOrFail();
        $files->destroy($tooling, $file, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('projects.toast.file_deleted')]);

        return to_route('projects.toolings.show', $tooling);
    }
}
