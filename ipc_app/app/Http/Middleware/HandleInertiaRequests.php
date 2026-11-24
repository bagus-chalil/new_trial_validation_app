<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ClientEventController;
use App\Http\Controllers\ImpersonationController;
use App\Models\IpcApproval;
use App\Models\IpcBatch;
use App\Models\IpcRoundCorrection;
use App\Models\User;
use App\Support\RoundAudit;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'portalUrl' => fn () => config('services.portal.url') ?: $request->getScheme().'://'.$request->getHost(),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'openClientIssues' => fn () => $request->user()?->isAdmin() ? ClientEventController::openIssueCount() : 0,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'recentBatches' => fn () => $request->user()
                ? IpcBatch::query()
                    ->visibleTo($request->user())
                    ->with(['masterProduct:id,product_name,fg_code', 'masterLine:id,name'])
                    ->latest('id')
                    ->limit(20)
                    ->get(['id', 'no_batch', 'current_stage', 'flow_type', 'master_product_id', 'master_line_id'])
                : [],
            // Set while an Admin is "login sebagai" another user: drives the banner on every page.
            'impersonator' => fn () => $this->impersonator($request),
            'canApproveIpc' => fn () => (bool) $request->user()?->isApprover(),
            'canApproveL1' => fn () => (bool) $request->user()?->isApproverL1(),
            'canApproveL2' => fn () => (bool) $request->user()?->isApproverL2(),
            'canManageMaster' => fn () => (bool) $request->user()?->isAdmin(),
            'canUseVisionTest' => fn () => (bool) $request->user()?->can('use-vision-test'),
            'canCreateBatch' => fn () => (bool) $request->user()?->can('create', IpcBatch::class),
            // Set on any page of a batch whose report an approver sent back for correction, so
            // QC sees the note and which stages were reopened on every stage page.
            // Approval pages only: the saved TH Progress rounds an approver can flag in "Minta
            // Revisi", plus the audit of rounds QC already corrected.
            'roundAudit' => function () use ($request) {
                $batch = $request->route('batch');
                if (! $batch instanceof IpcBatch || ! $request->user()?->isApprover() || ! str_starts_with((string) $request->route()?->getName(), 'approval.')) {
                    return null;
                }

                return RoundAudit::forBatch($batch);
            },
            'revisionRequest' => function () use ($request) {
                $batch = $request->route('batch');
                if (! $batch instanceof IpcBatch) {
                    return null;
                }

                $stages = $batch->openRevisionStages();
                if ($stages === []) {
                    return null;
                }

                $approval = $batch->approvals()
                    ->where('decision', IpcApproval::DECISION_REVISION)
                    ->with('approver:id,name')
                    ->latest('updated_at')
                    ->first();

                $corrections = $batch->roundCorrections()
                    ->where('status', IpcRoundCorrection::STATUS_OPEN)
                    ->whereNotNull('revision_no')
                    ->orderBy('stage')
                    ->orderBy('revision_no')
                    ->get(['id', 'stage', 'revision_no'])
                    ->map(fn (IpcRoundCorrection $c) => [
                        'id' => $c->id,
                        'stage' => $c->stage,
                        'no' => (int) $c->revision_no,
                        'label' => (IpcApproval::STAGE_LABELS[$c->stage] ?? $c->stage).' TH #'.$c->revision_no,
                        'url' => route('corrections.edit', [$batch, $c]),
                    ])
                    ->all();

                // Saved rounds of the reopened stages that QC can still ask to correct — also
                // covers a revision requested before round-level flagging existed.
                $open = collect($corrections)->map(fn ($c) => $c['stage'].':'.$c['no'])->all();
                $correctable = [];
                foreach (array_intersect($stages, [IpcBatch::STAGE_FILLING, IpcBatch::STAGE_PACKING, IpcBatch::STAGE_FINISHED]) as $stage) {
                    $check = match ($stage) {
                        IpcBatch::STAGE_FILLING => $batch->fillingCheck,
                        IpcBatch::STAGE_PACKING => $batch->packingCheck,
                        default => $batch->finishedCheck,
                    };
                    foreach ($check?->revisions()->orderBy('revision_no')->pluck('revision_no') ?? [] as $no) {
                        if (! in_array($stage.':'.$no, $open, true)) {
                            $correctable[] = ['stage' => $stage, 'no' => (int) $no, 'label' => (IpcApproval::STAGE_LABELS[$stage] ?? $stage).' TH #'.$no];
                        }
                    }
                }

                return [
                    'batch_id' => $batch->id,
                    'correctable' => $correctable,
                    'corrections' => $corrections,
                    'stages' => array_map(fn (string $s) => IpcApproval::STAGE_LABELS[$s] ?? $s, $stages),
                    'remarks' => $approval?->remarks,
                    'by' => $approval?->approver?->name,
                    'at' => $approval?->approved_at?->toIso8601String(),
                ];
            },
        ]);
    }

    /**
     * The Admin behind a "login sebagai" session, or null in a normal session (banner on every page).
     *
     * @return array{name: string, email: string}|null
     */
    private function impersonator(Request $request): ?array
    {
        $id = $request->hasSession() ? $request->session()->get(ImpersonationController::SESSION_KEY) : null;
        $admin = is_int($id) ? User::find($id) : null;

        return $admin ? ['name' => $admin->name, 'email' => $admin->email] : null;
    }
}
