<?php

namespace App\Http\Controllers;

use App\Actions\Mixing\MixingWorkflow;
use App\Actions\Mixing\SaveMixingFiles;
use App\Actions\Mixing\SaveMixingValidation;
use App\Models\Mixing;
use App\Models\MixingFile;
use App\Models\MixingReview;
use App\Models\User;
use App\Models\ValidationParameter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mixing wizard steps (Header lives in MixingController): Validation,
 * Attachments (D8: the batch record revision proof is uploaded here),
 * Review and Submit; plus the reviewer / approver actions. Mixing has no
 * Weighing step (see App\Support\ValidationStepConfig).
 */
class MixingStepController extends Controller
{
    public function validationEdit(int $mixing): Response
    {
        $mixing = $this->find($mixing);
        Gate::authorize('view', $mixing);

        $parameters = ValidationParameter::query()
            ->where('is_active', 1)->whereNull('deleted_at')
            ->where('product_type', $mixing->product_type)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'parameter_name', 'specification']);

        return Inertia::render('mixing/step', [
            'step' => 'validation',
            'mixing' => $this->summary($mixing),
            'canEdit' => Gate::allows('update', $mixing),
            'parameters' => $parameters,
            'results' => $mixing->results()->get()->keyBy('parameter_id'),
        ]);
    }

    public function validationUpdate(Request $request, int $mixing, SaveMixingValidation $action): RedirectResponse
    {
        $mixing = $this->find($mixing);
        Gate::authorize('update', $mixing);

        $request->validate([
            'results' => ['required', 'array'],
            'results.*.parameter_id' => ['required', 'integer'],
            'results.*.decision' => ['required', Rule::in(['OK', 'NOT OK', 'N/A'])],
            'results.*.result' => ['nullable', 'string', 'max:255'],
            'results.*.remark' => ['nullable', 'string', 'max:5000'],
        ]);

        $rows = collect((array) $request->input('results'))->values();

        $expected = ValidationParameter::query()
            ->where('is_active', 1)->whereNull('deleted_at')
            ->where('product_type', $mixing->product_type)
            ->pluck('id')->all();
        $submitted = $rows->pluck('parameter_id')->map(fn ($v) => (int) $v)->all();

        $problems = [];
        if (array_diff($expected, $submitted) !== []) {
            $problems[] = __('mixing.validation.missing_rows');
        }
        foreach ($rows as $row) {
            if (! in_array((int) $row['parameter_id'], $expected, true)) {
                $problems[] = __('mixing.validation.unknown_parameter');
                break;
            }
            if ($row['decision'] === 'NOT OK' && (trim((string) ($row['result'] ?? '')) === '' || trim((string) ($row['remark'] ?? '')) === '')) {
                $problems[] = __('mixing.validation.not_ok_needs_text');
                break;
            }
        }
        if ($problems !== []) {
            throw ValidationException::withMessages(['results' => implode(' | ', $problems)]);
        }

        $action($mixing, $rows->all(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('mixing.toast.validation_saved')]);

        return to_route('mixings.attachments.edit', $mixing->id);
    }

    public function attachmentsEdit(int $mixing): Response
    {
        $mixing = $this->find($mixing);
        Gate::authorize('view', $mixing);

        return Inertia::render('mixing/step', [
            'step' => 'attachments',
            'mixing' => $this->summary($mixing),
            'canEdit' => Gate::allows('update', $mixing),
            'categories' => MixingFile::CATEGORIES,
            'files' => $mixing->files()->orderBy('category')->orderBy('id')->get()->map(fn (MixingFile $f) => [
                'id' => $f->id,
                'category' => $f->category,
                'original_name' => $f->original_name,
                'mime' => $f->mime,
                'caption' => $f->caption,
            ])->values(),
        ]);
    }

    public function attachmentsStore(Request $request, int $mixing, SaveMixingFiles $action): RedirectResponse
    {
        $mixing = $this->find($mixing);
        Gate::authorize('update', $mixing);

        $data = $request->validate([
            'category' => ['required', Rule::in(MixingFile::CATEGORIES)],
            'caption' => ['nullable', 'string', 'max:255'],
            'files' => ['required', 'array', 'min:1'],
        ]);

        $result = $action->store($mixing, $data['category'], (array) $request->file('files', []), $data['caption'] ?? null, $request->user());

        if ($result['errors'] !== []) {
            Inertia::flash('toast', ['type' => $result['saved'] > 0 ? 'warning' : 'error', 'message' => implode(' ', $result['errors'])]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('mixing.toast.files_saved', ['count' => $result['saved']])]);
        }

        return back();
    }

    public function attachmentsDestroy(Request $request, int $mixing, int $file, SaveMixingFiles $action): RedirectResponse
    {
        $mixing = $this->find($mixing);
        Gate::authorize('update', $mixing);

        $record = MixingFile::query()->where('mixing_id', $mixing->id)->findOrFail($file);
        $action->delete($mixing, $record, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('mixing.toast.file_deleted')]);

        return back();
    }

    public function fileShow(int $mixing, int $file): StreamedResponse
    {
        $mixing = $this->find($mixing);
        Gate::authorize('view', $mixing);

        $record = MixingFile::query()->where('mixing_id', $mixing->id)->findOrFail($file);
        $path = 'mixing/'.$mixing->id.'/'.$record->file_name;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $record->original_name, [
            'Content-Type' => $record->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }

    public function reviewEdit(Request $request, int $mixing, MixingWorkflow $workflow): Response
    {
        $mixing = $this->find($mixing);
        Gate::authorize('view', $mixing);

        $approvers = User::query()->where('is_active', 1)->whereNull('deleted_at');
        User::applyEffectiveRoleIn($approvers, User::approverEligibleRoles());

        return Inertia::render('mixing/step', [
            'step' => 'review',
            'mixing' => $this->summary($mixing),
            'canEdit' => $this->canSubmit($request->user(), $mixing),
            'completeness' => $workflow->completeness($mixing),
            'reviewerDepartments' => User::reviewerDepartmentCodes(),
            'approvers' => $approvers->orderBy('name')->get(['id', 'name', 'email', 'role', 'app_role'])
                ->map(fn (User $u) => ['id' => $u->id, 'label' => trim((string) ($u->name ?: $u->email)).' - '.$u->effectiveRole()])->values(),
            'selectedApproverId' => $mixing->approver_user_id,
        ]);
    }

    public function submit(Request $request, int $mixing, MixingWorkflow $workflow): RedirectResponse
    {
        $mixing = $this->find($mixing);
        abort_unless($this->canSubmit($request->user(), $mixing), 403);

        $data = $request->validate([
            'departments' => ['required', 'array', 'min:1'],
            'departments.*' => ['string', Rule::in(User::reviewerDepartmentCodes())],
            'approver_user_id' => ['required', 'integer'],
        ]);

        $approverQuery = User::query()->where('is_active', 1)->whereNull('deleted_at');
        User::applyEffectiveRoleIn($approverQuery, User::approverEligibleRoles());
        $approver = $approverQuery->find((int) $data['approver_user_id']);
        if ($approver === null) {
            throw ValidationException::withMessages(['approver_user_id' => __('mixing.review.invalid_approver')]);
        }

        $errors = $workflow->completeness($mixing);
        if ($errors !== []) {
            throw ValidationException::withMessages(['departments' => implode(' | ', $errors)]);
        }

        $workflow->submit($mixing, array_values(array_unique($data['departments'])), $approver, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('mixing.toast.submitted')]);

        return to_route('mixings.show', $mixing->id);
    }

    public function reviewSave(Request $request, int $review, MixingWorkflow $workflow): RedirectResponse
    {
        $review = MixingReview::query()->with('mixing')->findOrFail($review);
        Gate::authorize('review', $review);

        $data = $request->validate(['comment' => ['required', 'string', 'max:5000']]);

        $workflow->review($review, trim($data['comment']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('mixing.toast.review_saved')]);

        return back();
    }

    public function decide(Request $request, int $mixing, MixingWorkflow $workflow): RedirectResponse
    {
        $mixing = $this->find($mixing);
        Gate::authorize('approve', $mixing);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['Approved', 'Need Revision', 'Rejected'])],
            'approval_comment' => ['required', 'string', 'max:5000'],
            'signature_password' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (! $user->is_active || ! Hash::check($data['signature_password'], (string) $user->password_hash)) {
            throw ValidationException::withMessages(['signature_password' => __('messages.validation.signature_wrong')]);
        }

        /** @var 'Approved'|'Need Revision'|'Rejected' $decision */
        $decision = $data['decision'];
        $workflow->decide($mixing, $decision, trim($data['approval_comment']), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('mixing.toast.decided')]);

        return back();
    }

    private function find(int $id): Mixing
    {
        return Mixing::query()->whereNull('deleted_at')->findOrFail($id);
    }

    private function canSubmit(User $user, Mixing $mixing): bool
    {
        return in_array($mixing->progress_status, ['Draft', 'Need Revision'], true) && Gate::forUser($user)->allows('update', $mixing);
    }

    /** @return array<string, mixed> */
    private function summary(Mixing $m): array
    {
        return [
            'id' => $m->id,
            'mixing_code' => $m->mixing_code,
            'product_name' => $m->product_name,
            'product_type' => $m->product_type,
            'progress_status' => $m->progress_status,
            'current_step' => $m->current_step,
            'case_id' => $m->case_id,
            'files_count' => $m->files()->count(),
        ];
    }
}
