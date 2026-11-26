<?php

namespace App\Http\Controllers;

use App\Actions\ImMaster\DiscardImRevision;
use App\Actions\ImMaster\ImWorkflow;
use App\Actions\ImMaster\SaveImFiles;
use App\Actions\ImMaster\SaveImWorksheet;
use App\Models\ImFile;
use App\Models\ImMaster;
use App\Models\ImParameter;
use App\Models\ImRevision;
use App\Models\ImSignoff;
use App\Models\Trial;
use App\Models\User;
use App\Models\ValidationCaseLink;
use App\Services\Pdf\PdfService;
use App\Services\Validation\ValidationRoles;
use App\Support\ImSections;
use App\Support\ImSignSlots;
use App\Support\SelfApprovalGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trial Validation v2.0 Tahap 6: one IM revision. QA edits the worksheet,
 * attaches the evidence, submits it into the QA chain (Prepared -> Review ->
 * Approved, each signed with the user's own password) and downloads the
 * generated PDF that mirrors the paper IM (FR.QAC.050.01).
 */
class ValidationImRevisionController extends Controller
{
    public function show(Request $request, int $revision, ImWorkflow $workflow): Response
    {
        $revision = $this->find($revision);
        Gate::authorize('view', $revision);

        $master = $revision->master ?? abort(404);
        $user = $request->user();
        $canEdit = Gate::allows('update', $revision);

        $mySignoff = ImSignoff::query()
            ->where('revision_id', $revision->id)->where('round', $revision->review_round)
            ->where('stage', 'review')->where('status', 'Pending')->where('user_id', $user->id)->first();

        $qaUsers = $canEdit
            ? ValidationRoles::qaUsers()->where('id', '!=', $user->id)->orderBy('name')->get(['id', 'name', 'email'])
                ->map(fn (User $u) => ['id' => $u->id, 'label' => trim((string) ($u->name ?: $u->email))])->values()
            : collect();

        $linked = ValidationCaseLink::query()->where('kind', 'im')->where('ref_key', $revision->im_number)->with('validationCase:id,case_no')->first();

        return Inertia::render('validation/im-masters/revision', [
            'revision' => $this->payload($revision, $master),
            'parameters' => $revision->parameters->map(fn (ImParameter $p) => [
                'section' => $p->section, 'code' => $p->code, 'name' => $p->name, 'category' => $p->category,
                'description' => $p->description, 'specification' => $p->specification, 'frequency' => $p->frequency,
                'value_min' => $p->value_min, 'value_max' => $p->value_max, 'unit' => $p->unit,
            ])->values(),
            'evidence' => $revision->evidence->map(fn ($e) => [
                'kind' => $e->kind, 'trial_id' => $e->trial_id, 'title' => $e->title, 'reference' => $e->reference, 'note' => $e->note,
            ])->values(),
            'files' => $revision->files->map(fn (ImFile $f) => [
                'id' => $f->id, 'category' => $f->category, 'caption' => $f->caption, 'original_name' => $f->original_name, 'mime' => $f->mime,
            ])->values(),
            'signoffs' => $revision->signoffs->map(fn (ImSignoff $s) => [
                'id' => $s->id, 'round' => $s->round, 'stage' => $s->stage, 'role_label' => $s->role_label, 'user_name' => $s->user_name,
                'status' => $s->status, 'comment' => $s->comment, 'signed_at' => $s->signed_at?->toDateTimeString(),
            ])->values(),
            'sections' => array_keys(ImSections::ALL),
            'numericSections' => array_keys(array_filter(ImSections::ALL, fn ($s) => $s['numeric'])),
            'fileCategories' => ImFile::CATEGORIES,
            'completeness' => $canEdit ? $workflow->completeness($revision) : [],
            'qaUsers' => $qaUsers,
            'approvedTrials' => $canEdit ? $this->approvedTrials($master) : [],
            'cases' => $canEdit ? ValidationImMasterController::caseOptions($master) : [],
            'linkedCase' => $linked === null ? null : ['id' => $linked->case_id, 'case_no' => $linked->validationCase?->case_no],
            'mySignoffId' => $mySignoff?->id,
            'myKnowledgeIds' => $revision->signoffs
                ->where('round', $revision->review_round)->where('stage', 'knowledge')
                ->filter(fn (ImSignoff $s) => Gate::forUser($user)->allows('acknowledge', $s))
                ->pluck('id')->values(),
            'reviewLabels' => ImSignSlots::reviewLabels(),
            'can' => [
                'edit' => $canEdit,
                'delete' => Gate::allows('delete', $revision),
                'approve' => Gate::allows('approve', $revision),
            ],
        ]);
    }

    public function update(Request $request, int $revision, SaveImWorksheet $action): RedirectResponse
    {
        $revision = $this->find($revision);
        Gate::authorize('update', $revision);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'appearance' => ['nullable', 'string', 'max:100'],
            'mfg_category' => ['nullable', 'string', 'max:50'],
            'customer' => ['nullable', 'string', 'max:150'],
            'customer_ref' => ['nullable', 'string', 'max:150'],
            'line_label' => ['nullable', 'string', 'max:200'],
            'issue_date' => ['nullable', 'date'],
            'effective_from' => ['nullable', 'date'],
            'revision_reason' => ['nullable', 'string', 'max:255'],
            'legacy_reference' => ['nullable', 'string', 'max:255'],
            'bulk_wip_refs' => ['nullable', 'array'],
            'bulk_wip_refs.*' => ['nullable', 'string', 'max:60'],
            'content' => ['nullable', 'array'],
            'content.*' => ['nullable', 'string', 'max:5000'],
            'parameters' => ['nullable', 'array', 'max:400'],
            'parameters.*.section' => ['required', Rule::in(ImSections::keys())],
            'parameters.*.name' => ['nullable', 'string', 'max:255'],
            'parameters.*.code' => ['nullable', 'string', 'max:80'],
            'parameters.*.category' => ['nullable', 'string', 'max:80'],
            'parameters.*.description' => ['nullable', 'string', 'max:5000'],
            'parameters.*.specification' => ['nullable', 'string', 'max:5000'],
            'parameters.*.frequency' => ['nullable', 'string', 'max:255'],
            'parameters.*.value_min' => ['nullable', 'numeric'],
            'parameters.*.value_max' => ['nullable', 'numeric'],
            'parameters.*.unit' => ['nullable', 'string', 'max:20'],
            'evidence' => ['nullable', 'array', 'max:100'],
            'evidence.*.kind' => ['required', Rule::in(['trial', 'document'])],
            'evidence.*.trial_id' => ['nullable', 'integer'],
            'evidence.*.title' => ['nullable', 'string', 'max:255'],
            'evidence.*.reference' => ['nullable', 'string', 'max:255'],
            'evidence.*.note' => ['nullable', 'string', 'max:5000'],
            'case_id' => ['nullable', 'integer'],
        ]);

        $action($revision, $data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('im_master.toast.saved')]);

        return back();
    }

    public function destroy(Request $request, int $revision, DiscardImRevision $action): RedirectResponse
    {
        $revision = $this->find($revision);
        Gate::authorize('delete', $revision);

        $masterId = $revision->master_id;
        $action($revision, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('im_master.toast.discarded')]);

        return ImMaster::query()->whereKey($masterId)->exists()
            ? to_route('validation.im-masters.show', $masterId)
            : to_route('validation.im-masters.index');
    }

    public function submit(Request $request, int $revision, ImWorkflow $workflow): RedirectResponse
    {
        $revision = $this->find($revision);
        Gate::authorize('update', $revision);

        $data = $request->validate([
            'reviewer_ids' => ['required', 'array', 'min:1'],
            'reviewer_ids.*' => ['integer'],
            'reviewer_labels' => ['nullable', 'array'],
            'reviewer_labels.*' => ['nullable', 'string', Rule::in(ImSignSlots::reviewLabels())],
            'approver_user_id' => ['required', 'integer'],
            'signature_password' => ['required', 'string'],
        ]);

        $user = $request->user();
        $this->assertPassword($user, $data['signature_password']);

        $approver = ValidationRoles::qaUsers()->find((int) $data['approver_user_id']);
        if ($approver === null) {
            throw ValidationException::withMessages(['approver_user_id' => __('im_master.error.invalid_qa_user')]);
        }

        $reviewerIds = array_values(array_unique(array_map('intval', $data['reviewer_ids'])));
        $reviewers = ValidationRoles::qaUsers()->whereIn('id', $reviewerIds)->get();
        if ($reviewers->count() !== count($reviewerIds)) {
            throw ValidationException::withMessages(['reviewer_ids' => __('im_master.error.invalid_qa_user')]);
        }

        if (in_array($approver->id, $reviewerIds, true)) {
            throw ValidationException::withMessages(['approver_user_id' => __('im_master.error.approver_is_reviewer')]);
        }

        foreach ($reviewers->push($approver) as $person) {
            if (SelfApprovalGuard::isPreparerById($person, $user->id)) {
                throw ValidationException::withMessages(['reviewer_ids' => __('messages.validation.self_approval')]);
            }
        }

        $errors = $workflow->completeness($revision);
        if ($errors !== []) {
            throw ValidationException::withMessages(['reviewer_ids' => implode(' | ', $errors)]);
        }

        $labels = [];
        foreach ((array) ($data['reviewer_labels'] ?? []) as $id => $label) {
            if (in_array((int) $id, $reviewerIds, true) && filled($label)) {
                $labels[(int) $id] = (string) $label;
            }
        }

        $workflow->submit($revision, $reviewerIds, $approver, $user, $labels);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('im_master.toast.submitted')]);

        return back();
    }

    public function review(Request $request, int $signoff, ImWorkflow $workflow): RedirectResponse
    {
        $signoff = ImSignoff::query()->with('revision')->findOrFail($signoff);
        Gate::authorize('review', $signoff);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
            'signature_password' => ['required', 'string'],
        ]);

        $this->assertPassword($request->user(), $data['signature_password']);

        $workflow->review($signoff, trim($data['comment']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('im_master.toast.reviewed')]);

        return back();
    }

    public function acknowledge(Request $request, int $signoff, ImWorkflow $workflow): RedirectResponse
    {
        $signoff = ImSignoff::query()->with('revision')->findOrFail($signoff);
        Gate::authorize('acknowledge', $signoff);

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:5000'],
            'signature_password' => ['required', 'string'],
        ]);

        $this->assertPassword($request->user(), $data['signature_password']);

        $workflow->acknowledge($signoff, trim((string) ($data['comment'] ?? '')), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('im_master.toast.acknowledged')]);

        return back();
    }

    public function decide(Request $request, int $revision, ImWorkflow $workflow): RedirectResponse
    {
        $revision = $this->find($revision);
        Gate::authorize('approve', $revision);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['Approved', 'Need Revision', 'Rejected'])],
            'approval_comment' => ['required', 'string', 'max:5000'],
            'signature_password' => ['required', 'string'],
        ]);

        $this->assertPassword($request->user(), $data['signature_password']);

        /** @var 'Approved'|'Need Revision'|'Rejected' $decision */
        $decision = $data['decision'];
        $workflow->decide($revision, $decision, trim($data['approval_comment']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('im_master.toast.decided')]);

        return back();
    }

    public function fileStore(Request $request, int $revision, SaveImFiles $action): RedirectResponse
    {
        $revision = $this->find($revision);
        Gate::authorize('update', $revision);

        $data = $request->validate([
            'category' => ['required', Rule::in(ImFile::CATEGORIES)],
            'caption' => ['nullable', 'string', 'max:255'],
            'files' => ['required', 'array', 'min:1'],
        ]);

        $result = $action->store($revision, $data['category'], (array) $request->file('files', []), $data['caption'] ?? null, $request->user());

        Inertia::flash('toast', $result['errors'] !== []
            ? ['type' => $result['saved'] > 0 ? 'warning' : 'error', 'message' => implode(' ', $result['errors'])]
            : ['type' => 'success', 'message' => __('im_master.toast.files_saved', ['count' => $result['saved']])]);

        return back();
    }

    public function fileDestroy(Request $request, int $revision, int $file, SaveImFiles $action): RedirectResponse
    {
        $revision = $this->find($revision);
        Gate::authorize('update', $revision);

        $action->delete($revision, ImFile::query()->where('revision_id', $revision->id)->findOrFail($file), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('im_master.toast.file_deleted')]);

        return back();
    }

    public function fileShow(int $revision, int $file): StreamedResponse
    {
        $revision = $this->find($revision);
        Gate::authorize('view', $revision);

        $record = ImFile::query()->where('revision_id', $revision->id)->findOrFail($file);
        $path = 'im/'.$revision->id.'/'.$record->file_name;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $record->original_name, [
            'Content-Type' => $record->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox',
        ]);
    }

    public function pdf(int $revision, PdfService $pdf): HttpResponse
    {
        $revision = $this->find($revision);
        Gate::authorize('view', $revision);

        $master = $revision->master ?? abort(404);
        $params = $revision->parameters->groupBy('section');

        $images = [];
        foreach ($revision->files as $file) {
            $path = 'im/'.$revision->id.'/'.$file->file_name;
            if (str_starts_with($file->mime, 'image/') && Storage::disk('local')->exists($path)) {
                $images[$file->category][] = [
                    'caption' => $file->caption,
                    'src' => 'data:'.$file->mime.';base64,'.base64_encode((string) Storage::disk('local')->get($path)),
                ];
            }
        }

        $signoffs = $revision->signoffs->where('round', $revision->review_round);

        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $revision->im_number).'.pdf';

        return $pdf->fromView('pdf.im-revision', [
            'revision' => $revision,
            'master' => $master,
            'params' => $params,
            'content' => $revision->content ?? [],
            'images' => $images,
            'prepared' => $signoffs->firstWhere('stage', 'prepared'),
            'reviews' => $signoffs->where('stage', 'review')->values(),
            'approval' => $signoffs->firstWhere('stage', 'approved'),
            'knowledge' => $signoffs->where('stage', 'knowledge')->keyBy('role_label'),
            'knowledgeSlots' => ImSignSlots::knowledgeSlots(),
            'isEffective' => $revision->status === 'Effective',
            'title' => $revision->im_number,
        ], $filename, ['landscape' => true]);
    }

    private function find(int $id): ImRevision
    {
        return ImRevision::query()->with('master')->findOrFail($id);
    }

    private function assertPassword(User $user, string $password): void
    {
        if (! $user->is_active || ! Hash::check($password, (string) $user->password_hash)) {
            throw ValidationException::withMessages(['signature_password' => __('messages.validation.signature_wrong')]);
        }
    }

    /** @return array<string, mixed> */
    private function payload(ImRevision $r, ImMaster $master): array
    {
        return [
            'id' => $r->id, 'master_id' => $master->id, 'fg_code' => $master->fg_code, 'product_name' => $master->product_name,
            'bulk_wip_refs' => $master->bulk_wip_refs ?? [],
            'im_number' => $r->im_number, 'revision_no' => $r->revision_no, 'status' => $r->status,
            'description' => $r->description, 'appearance' => $r->appearance, 'mfg_category' => $r->mfg_category,
            'customer' => $r->customer, 'customer_ref' => $r->customer_ref, 'line_label' => $r->line_label,
            'issue_date' => $r->issue_date?->toDateString(), 'effective_from' => $r->effective_from?->toDateString(),
            'revision_reason' => $r->revision_reason, 'replaces_number' => $r->replaces_number,
            'legacy_reference' => $r->legacy_reference, 'is_onboarded' => $r->is_onboarded,
            'content' => $r->content ?? (object) [],
            'prepared_by_name' => $r->prepared_by_name, 'pending_with' => $r->pending_with,
            'approval_comment' => $r->approval_comment, 'approved_by' => $r->approved_by,
            'approved_at' => $r->approved_at?->toDateTimeString(),
            'review_round' => $r->review_round,
        ];
    }

    /** @return list<array{id: int, label: string}> */
    private function approvedTrials(ImMaster $master): array
    {
        return array_values(Trial::query()
            ->whereNull('deleted_at')->where('progress_status', 'Approved')
            ->where(function ($q) use ($master) {
                $q->where('product_id', $master->product_id);
                if ($master->product_name !== '') {
                    $q->orWhere('product_name', $master->product_name);
                }
            })
            ->orderByDesc('id')->limit(100)->get(['id', 'trial_code', 'product_name'])
            ->map(fn (Trial $t) => ['id' => $t->id, 'label' => $t->trial_code.' · '.$t->product_name])->all());
    }
}
