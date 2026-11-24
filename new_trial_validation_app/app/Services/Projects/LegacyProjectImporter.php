<?php

namespace App\Services\Projects;

use App\Models\ActivityLog;
use App\Models\Improvement;
use App\Models\ImprovementFile;
use App\Models\MasterOption;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectMilestone;
use App\Models\ProjectQuotation;
use App\Models\ProjectSetting;
use App\Models\ProjectStage;
use App\Models\ProjectStageTemplate;
use App\Models\PurchaseOrder;
use App\Models\Tooling;
use App\Models\ToolingApproval;
use App\Models\ToolingItem;
use App\Models\User;
use App\Models\VendorContact;
use App\Support\PurchaseOrderSettings;
use App\Support\ToolingOptions;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Moves the standalone Project Tracking PIE database (`project_tracker`) into
 * the pm_* tables of this app (see PROJECT_MODULE_PLAN.md).
 *
 * Two phases: plan() reads the source READ-ONLY and builds plain arrays (used by
 * --dry-run, never writes); apply() writes them idempotently, keyed on
 * `legacy_id`, inside one transaction.
 *
 * Source quirks this class absorbs (verified against the real database):
 * - `project_stages` / `project_quotations` rows get brand-new UUIDs on every
 *   save of a project in the old app (DELETE + INSERT), and drop actual dates
 *   and progress. The stable, richer copy is in `projects.metadata` JSON, so
 *   stages and quotations are imported from there (the table counts are
 *   reported alongside as a cross-check).
 * - Improvements and Check Tooling requests only exist inside the
 *   `settings.tracker_state` JSON blob (`improvements`, `toolings`). Their
 *   files are `project_files` rows with project_id NULL and a stored name
 *   prefixed IMP- / IMP-AFTER- / TLG-, linked back through the blob's
 *   `attachments` / `afterAttachments` lists.
 * - No cross-database JOIN is used: users/vendors are matched in PHP, so the
 *   utf8mb4_unicode_ci vs utf8mb4_0900_ai_ci collation clash (error 1267)
 *   cannot occur. Any future SQL JOIN across the two DBs needs an explicit COLLATE.
 */
class LegacyProjectImporter
{
    /** @var array<string, int|null> */
    private array $userCache = [];

    /** @var array<string, int> lowercase name => user id (unambiguous only) */
    private array $usersByName = [];

    /** @var array<string, int> lowercase email => user id */
    private array $usersByEmail = [];

    /** @var array<string, string> lowercase legacy name => lowercase email */
    private array $explicitMap = [];

    /** @var array<string, true> */
    private array $unmatchedNames = [];

    /**
     * @param  array<string, string>  $userMap  legacy name => email
     */
    public function __construct(
        private readonly string $connection,
        private readonly string $filesPath,
        array $userMap = [],
    ) {
        foreach ($userMap as $name => $email) {
            $this->explicitMap[mb_strtolower(trim((string) $name))] = mb_strtolower(trim((string) $email));
        }
    }

    /**
     * Read the source and build the import plan. Writes nothing.
     *
     * @return array<string, mixed>
     */
    public function plan(): array
    {
        $this->loadUsers();
        $src = DB::connection($this->connection);

        $projectRows = $src->table('projects')->orderBy('id')->get();
        $stateRow = $src->table('settings')->where('key_name', 'tracker_state')->value('value_json');
        $trackerRow = $src->table('settings')->where('key_name', 'tracker')->value('value_json');
        $state = $this->decode($stateRow);
        $tracker = $this->decode($trackerRow);

        $plan = [
            'projects' => [], 'stages' => [], 'milestones' => [], 'quotations' => [],
            'files' => [], 'improvements' => [], 'improvement_files' => [], 'logs' => [],
            'toolings' => [],
            'templates' => [], 'categories' => [], 'vendors' => [],
            'purchase_orders' => [], 'vendor_contacts' => [], 'po_template' => null,
            'source' => [
                'projects' => $projectRows->count(),
                'project_stages' => $src->table('project_stages')->count(),
                'project_quotations' => $src->table('project_quotations')->count(),
                'project_milestones' => $src->table('project_milestones')->count(),
                'project_files' => $src->table('project_files')->count(),
                'project_logs' => $src->table('project_logs')->count(),
            ],
            'warnings' => [],
        ];

        $stageAttachments = [];
        $quotationFiles = [];
        $seenStageIds = [];
        $metaMilestones = 0;

        foreach ($projectRows as $row) {
            $meta = $this->decode($row->metadata);
            $legacyId = (string) $row->id;
            $createdBy = (string) ($meta['createdBy'] ?? '');

            $plan['projects'][] = [
                'legacy_id' => $legacyId,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'category' => (string) $row->category,
                'status' => (string) $row->status,
                'priority' => (string) $row->priority,
                'pic_name' => (string) $row->pic,
                'pic_user_id' => $this->resolveUser((string) $row->pic),
                'area' => (string) $row->area,
                'start_date' => $this->date($row->start_date),
                'end_date' => $this->date($row->end_date),
                'progress' => max(0, min(100, (int) $row->progress)),
                'description' => $row->description,
                'vendor' => (string) $row->vendor,
                'budget' => (float) $row->budget,
                'actual' => (float) $row->actual,
                'po' => (string) $row->po,
                'created_by_name' => $createdBy,
                'created_by_user_id' => $this->resolveUser($createdBy),
                'updated_by_name' => (string) $row->updated_by,
                'updated_by_user_id' => $this->resolveUser((string) $row->updated_by),
                'legacy_meta' => ['log' => $meta['log'] ?? [], 'pos' => $meta['pos'] ?? []],
                'updated_at' => $row->updated_at,
            ];
            $metaMilestones += count($meta['milestones'] ?? []);

            foreach (array_values($meta['stages'] ?? []) as $i => $stg) {
                if (! is_array($stg) || trim((string) ($stg['name'] ?? '')) === '') {
                    continue;
                }
                $stageId = (string) ($stg['id'] ?? ($legacyId.'-stage-'.($i + 1)));
                if (isset($seenStageIds[$stageId])) {
                    $stageId = $legacyId.':'.$stageId;
                }
                $seenStageIds[$stageId] = true;
                $name = trim((string) $stg['name']);

                $plan['stages'][] = [
                    'legacy_id' => $stageId,
                    'project_legacy_id' => $legacyId,
                    'stage_type' => preg_match('/valid|trial/i', $name) === 1 ? 'Validation' : 'General',
                    'name' => mb_substr($name, 0, 160),
                    'pic_name' => mb_substr(trim((string) ($stg['pic'] ?? '')), 0, 100),
                    'pic_user_id' => $this->resolveUser((string) ($stg['pic'] ?? '')),
                    'plan_start' => $this->date($stg['ps'] ?? null),
                    'plan_end' => $this->date($stg['pf'] ?? null),
                    'actual_start' => $this->date($stg['as'] ?? null),
                    'actual_end' => $this->date($stg['af'] ?? null),
                    'progress' => max(0, min(100, (int) ($stg['prog'] ?? 0))),
                    'is_parallel' => (bool) ($stg['par'] ?? false),
                    'status' => mb_substr(trim((string) ($stg['status'] ?? 'Not Started')), 0, 50),
                    'note' => (string) ($stg['note'] ?? ''),
                    'sort_order' => $i,
                ];
                foreach ($stg['attachments'] ?? [] as $att) {
                    if (is_array($att) && isset($att['file'])) {
                        $stageAttachments[(string) $att['file']] = ['stage' => $stageId, 'att' => $att];
                    }
                }
            }

            foreach (array_values($meta['quotations'] ?? []) as $i => $q) {
                if (! is_array($q)) {
                    continue;
                }
                $qid = (string) ($q['id'] ?? ($legacyId.'-q-'.($i + 1)));
                $plan['quotations'][] = [
                    'legacy_id' => $qid,
                    'project_legacy_id' => $legacyId,
                    'vendor_name' => mb_substr(trim((string) ($q['vendor'] ?? '')), 0, 255),
                    'quotation_no' => mb_substr(trim((string) ($q['no'] ?? $q['quotation_no'] ?? '')), 0, 120),
                    'amount' => max(0.0, (float) ($q['amount'] ?? 0)),
                    'currency' => mb_substr(trim((string) ($q['currency'] ?? 'IDR')) ?: 'IDR', 0, 8),
                    'quotation_date' => $this->date($q['date'] ?? null),
                    'valid_until' => $this->date($q['valid'] ?? $q['valid_until'] ?? null),
                    'status' => mb_substr(trim((string) ($q['status'] ?? 'Diterima')), 0, 50),
                    'note' => (string) ($q['note'] ?? ''),
                    'link' => mb_substr((string) ($q['link'] ?? ''), 0, 500),
                    'file_stored_name' => (string) ($q['file'] ?? ''),
                ];
                if (! empty($q['file'])) {
                    $quotationFiles[(string) $q['file']] = $qid;
                }
            }
        }

        $seenPoNos = [];
        foreach ($projectRows as $row) {
            $meta = $this->decode($row->metadata);
            foreach (array_values($meta['pos'] ?? []) as $i => $po) {
                if (! is_array($po)) {
                    continue;
                }
                $no = trim((string) ($po['no'] ?? ''));
                if ($no === '') {
                    $plan['warnings'][] = 'Purchase order without a number skipped (project '.$row->code.')';

                    continue;
                }
                if (isset($seenPoNos[$no])) {
                    $plan['warnings'][] = 'Duplicate PO number "'.$no.'" in the source (project '.$row->code.'); imported as "'.$no.' (dup)"';
                    $no .= ' (dup)';
                }
                $seenPoNos[$no] = true;
                $createdBy = trim((string) ($po['by'] ?? ''));
                $plan['purchase_orders'][] = [
                    'legacy_id' => (string) ($po['id'] ?? ((string) $row->id.'-po-'.($i + 1))),
                    'project_legacy_id' => (string) $row->id,
                    'quotation_legacy_id' => ($po['qid'] ?? '') !== '' ? (string) $po['qid'] : null,
                    'po_no' => mb_substr($no, 0, 120),
                    'po_date' => $this->date($po['date'] ?? null),
                    'vendor_name' => mb_substr(trim((string) ($po['vendor'] ?? '')), 0, 255),
                    'currency' => mb_substr(trim((string) ($po['currency'] ?? '')) ?: 'IDR', 0, 8),
                    'vendor_address' => $this->nullableText($po['addr'] ?? null),
                    'attn' => mb_substr(trim((string) ($po['attn'] ?? '')), 0, 150),
                    'tel' => mb_substr(trim((string) ($po['tel'] ?? '')), 0, 80),
                    'email' => mb_substr(trim((string) ($po['email'] ?? '')), 0, 190),
                    'quotation_no' => mb_substr(trim((string) ($po['qno'] ?? '')), 0, 120),
                    'subject' => mb_substr(trim((string) ($po['subject'] ?? '')), 0, 255),
                    'tax_percent' => max(0, min(100, (float) ($po['tax'] ?? 0))),
                    'terms' => $this->nullableText($po['terms'] ?? null),
                    'delivery' => mb_substr(trim((string) ($po['delivery'] ?? '')), 0, 255),
                    'lead_time' => mb_substr(trim((string) ($po['leadTime'] ?? '')), 0, 255),
                    'note' => $this->nullableText($po['note'] ?? null),
                    'ship_name' => mb_substr(trim((string) ($po['shipName'] ?? '')), 0, 190),
                    'ship_address' => $this->nullableText($po['shipAddr'] ?? null),
                    'ship_tel' => mb_substr(trim((string) ($po['shipTel'] ?? '')), 0, 80),
                    'ship_attn' => mb_substr(trim((string) ($po['shipAttn'] ?? '')), 0, 150),
                    'use_signature' => (bool) ($po['useSig'] ?? false),
                    'created_by_name' => mb_substr($createdBy, 0, 100),
                    'created_by_user_id' => $this->resolveUser($createdBy),
                    'created_at' => $this->ms($po['at'] ?? null),
                    'items' => array_values(array_map(fn ($it) => [
                        'description' => (string) ($it['desc'] ?? ''),
                        'qty' => max(0.0, (float) ($it['qty'] ?? 0)),
                        'unit' => mb_substr(trim((string) ($it['unit'] ?? '')) ?: 'Unit', 0, 30),
                        'unit_price' => max(0.0, (float) ($it['price'] ?? 0)),
                        'due_date' => $this->date($it['due'] ?? null),
                    ], array_filter($po['items'] ?? [], 'is_array'))),
                ];
            }
        }

        foreach ($src->table('project_milestones')->get() as $m) {
            $plan['milestones'][] = [
                'legacy_id' => (string) $m->id,
                'project_legacy_id' => (string) $m->project_id,
                'name' => (string) $m->name,
                'due_date' => $this->date($m->due_date),
                'status' => (string) $m->status,
                'note' => $m->note,
            ];
        }
        if ($metaMilestones !== $plan['source']['project_milestones']) {
            $plan['warnings'][] = "metadata holds {$metaMilestones} milestone(s) but project_milestones has {$plan['source']['project_milestones']}; the table is authoritative.";
        }

        // Improvements (blob) and their attachment index.
        $improvementFiles = [];
        foreach ($state['improvements'] ?? [] as $imp) {
            if (! is_array($imp) || ! isset($imp['id'])) {
                continue;
            }
            $iid = (string) $imp['id'];
            $plan['improvements'][] = [
                'legacy_id' => $iid,
                'title' => mb_substr((string) ($imp['title'] ?? ''), 0, 255),
                'department' => mb_substr((string) ($imp['department'] ?? ''), 0, 100),
                'submitted_by_name' => mb_substr((string) ($imp['submittedBy'] ?? ''), 0, 100),
                'submitted_by_user_id' => $this->resolveUser((string) ($imp['submittedBy'] ?? '')),
                'submitted_at' => $this->ms($imp['submittedAt'] ?? null),
                'type' => mb_substr((string) ($imp['type'] ?? ''), 0, 50),
                'issue' => $imp['issue'] ?? null,
                'background' => $imp['background'] ?? null,
                'purpose' => $imp['purpose'] ?? null,
                'goals' => $imp['goals'] ?? null,
                'saving_mp' => mb_substr((string) ($imp['savingMp'] ?? ''), 0, 255),
                'quality_metric' => mb_substr((string) ($imp['qualityMetric'] ?? ''), 0, 255),
                'links' => $imp['links'] ?? [],
                'status' => (string) ($imp['status'] ?? 'Baru'),
                'decision' => (string) ($imp['decision'] ?? 'Pending'),
                'pi_owner' => mb_substr((string) ($imp['piOwner'] ?? ''), 0, 100),
                'pi_feedback' => $imp['piFeedback'] ?? null,
                'pi_suggestion' => $imp['piSuggestion'] ?? null,
                'pi_action' => $imp['piAction'] ?? null,
                'pi_reason' => $imp['piReason'] ?? null,
                'feedback_log' => $imp['feedbackLog'] ?? [],
                'project_legacy_id' => (string) ($imp['projectId'] ?? ''),
                'updated_by_name' => mb_substr((string) ($imp['updatedBy'] ?? ''), 0, 100),
                'updated_at' => $this->ms($imp['updatedAt'] ?? null),
                'after_condition' => $this->nullableText($imp['afterCondition'] ?? null),
                'after_result' => $this->nullableText($imp['afterResult'] ?? null),
                'after_date' => $this->date($imp['afterDate'] ?? null),
                'after_pic' => mb_substr(trim((string) ($imp['afterPic'] ?? '')), 0, 100),
                'after_links' => $this->links($imp['afterLinks'] ?? []),
            ];
            foreach (['attachments' => 'before', 'afterAttachments' => 'after'] as $key => $phase) {
                foreach ($imp[$key] ?? [] as $att) {
                    if (is_array($att) && isset($att['file'])) {
                        $improvementFiles[(string) $att['file']] = ['improvement' => $iid, 'att' => $att, 'phase' => $phase];
                    }
                }
            }
        }

        // Check Tooling requests (blob) and their attachment index.
        $toolingFiles = [];
        foreach ($state['toolings'] ?? [] as $tlg) {
            if (! is_array($tlg) || ! isset($tlg['id'])) {
                continue;
            }
            $plan['toolings'][] = $this->planTooling($tlg, $plan['warnings']);
            foreach ($tlg['attachments'] ?? [] as $att) {
                if (is_array($att) && isset($att['file'])) {
                    $toolingFiles[(string) $att['file']] = ['tooling' => (string) $tlg['id'], 'att' => $att];
                }
            }
        }

        // Files: every project_files row is accounted for exactly once.
        $handled = [];
        foreach ($src->table('project_files')->orderBy('id')->get() as $f) {
            $stored = (string) $f->stored_name;
            $handled[$stored] = true;
            $base = [
                'legacy_id' => (string) $f->id,
                'file_name' => (string) $f->file_name,
                'mime_type' => (string) $f->mime_type,
                'size_bytes' => (int) $f->size_bytes,
                'stored_name' => $stored,
                'uploaded_at' => $f->uploaded_at,
            ];
            if (isset($improvementFiles[$stored])) {
                $plan['improvement_files'][] = $base + [
                    'improvement_legacy_id' => $improvementFiles[$stored]['improvement'],
                    'description' => $improvementFiles[$stored]['att']['description'] ?? null,
                    'phase' => $improvementFiles[$stored]['phase'],
                ];

                continue;
            }
            $context = 'project';
            $stageLegacy = null;
            $toolingLegacy = null;
            $description = null;
            $uploader = '';
            if (isset($stageAttachments[$stored])) {
                $context = 'stage';
                $stageLegacy = $stageAttachments[$stored]['stage'];
                $description = $stageAttachments[$stored]['att']['description'] ?? null;
                $uploader = (string) ($stageAttachments[$stored]['att']['uploadedBy'] ?? '');
            } elseif (isset($quotationFiles[$stored])) {
                $context = 'quotation';
            } elseif (isset($toolingFiles[$stored]) || str_starts_with($stored, 'TLG-')) {
                // Check Tooling attachment (Fase 4b). An orphan TLG- file whose
                // request no longer lists it is kept unlinked so nothing is lost.
                $context = 'tooling';
                $toolingLegacy = $toolingFiles[$stored]['tooling'] ?? null;
                $description = $toolingFiles[$stored]['att']['description'] ?? null;
            }
            $plan['files'][] = $base + [
                'project_legacy_id' => $f->project_id === null ? null : (string) $f->project_id,
                'stage_legacy_id' => $stageLegacy,
                'tooling_legacy_id' => $toolingLegacy,
                'context' => $context,
                'description' => $description,
                'uploaded_by_name' => mb_substr($uploader, 0, 100),
            ];
        }
        foreach ($improvementFiles as $stored => $info) {
            if (isset($handled[$stored])) {
                continue;
            }
            $att = $info['att'];
            $plan['improvement_files'][] = [
                'legacy_id' => (string) ($att['id'] ?? $stored),
                'file_name' => (string) ($att['name'] ?? $stored),
                'mime_type' => (string) ($att['type'] ?? 'application/octet-stream'),
                'size_bytes' => (int) ($att['size'] ?? 0),
                'stored_name' => $stored,
                'uploaded_at' => null,
                'improvement_legacy_id' => $info['improvement'],
                'description' => $att['description'] ?? null,
                'phase' => $info['phase'],
            ];
            $plan['warnings'][] = "improvement attachment {$stored} is not in project_files; imported from the JSON blob.";
        }
        foreach ($plan['files'] as &$file) {
            $file['source_state'] = $this->sourceState($file);
        }
        unset($file);
        foreach ($plan['improvement_files'] as &$file) {
            $file['source_state'] = $this->sourceState($file);
        }
        unset($file);

        foreach ($src->table('project_logs')->orderBy('created_at')->orderBy('id')->get() as $l) {
            $plan['logs'][] = [
                'legacy_id' => (string) $l->id,
                'scope' => (string) $l->project_id,
                'actor' => (string) $l->actor,
                'text' => (string) $l->action,
                'created_at' => $l->created_at,
            ];
        }

        foreach ($tracker['templates'] ?? [] as $tpl) {
            if (is_array($tpl) && isset($tpl['id'])) {
                $plan['templates'][] = [
                    'legacy_id' => (string) $tpl['id'],
                    'name' => mb_substr((string) ($tpl['name'] ?? ''), 0, 160),
                    'category' => mb_substr((string) ($tpl['category'] ?? ''), 0, 100),
                    'owner_name' => mb_substr((string) ($tpl['owner'] ?? ''), 0, 100),
                    'stages' => $tpl['stages'] ?? [],
                ];
            }
        }

        foreach ($tracker['vendors'] ?? [] as $v) {
            if (is_array($v) && trim((string) ($v['name'] ?? '')) !== '') {
                $plan['vendor_contacts'][] = [
                    'name' => mb_substr(trim((string) $v['name']), 0, 255),
                    'contact' => mb_substr(trim((string) ($v['contact'] ?? '')), 0, 150),
                    'phone' => mb_substr(trim((string) ($v['phone'] ?? '')), 0, 80),
                    'email' => mb_substr(trim((string) ($v['email'] ?? '')), 0, 190),
                    'address' => $this->nullableText($v['address'] ?? null),
                ];
            }
        }
        foreach ($plan['purchase_orders'] as $po) {
            if ($po['vendor_name'] !== '') {
                $plan['vendor_contacts'][] = ['name' => $po['vendor_name'], 'contact' => $po['attn'], 'phone' => $po['tel'], 'email' => $po['email'], 'address' => $po['vendor_address']];
            }
        }

        if (is_array($tracker['po'] ?? null)) {
            $t = $tracker['po'];
            $plan['po_template'] = [
                'company' => (string) ($t['company'] ?? ''),
                'doc_code' => (string) ($t['docCode'] ?? ''),
                'logo' => (string) ($t['logo'] ?? ''),
                'address' => (string) ($t['address'] ?? ''),
                'contact' => (string) ($t['contact'] ?? ''),
                'title' => (string) ($t['title'] ?? ''),
                'pattern' => (string) ($t['pattern'] ?? ''),
                'date_format' => (string) ($t['dateFmt'] ?? ''),
                'lang' => (string) ($t['lang'] ?? ''),
                'tax' => (float) ($t['tax'] ?? 0),
                'terms' => (string) ($t['terms'] ?? ''),
                'lead_time' => (string) ($t['leadTime'] ?? ''),
                'delivery' => (string) ($t['delivery'] ?? ''),
                'ship_name' => (string) ($t['shipName'] ?? ''),
                'ship_address' => (string) ($t['shipAddr'] ?? ''),
                'ship_tel' => (string) ($t['shipTel'] ?? ''),
                'ship_attn' => (string) ($t['shipAttn'] ?? ''),
                'billing' => (string) ($t['billing'] ?? ''),
                'notice' => (string) ($t['notice'] ?? ''),
                'signers' => is_array($t['sign'] ?? null) ? $t['sign'] : [],
            ];
        }

        $categories = array_column(array_filter($tracker['categories'] ?? [], 'is_array'), 'name');
        foreach ($plan['projects'] as $p) {
            $categories[] = $p['category'];
        }
        $plan['categories'] = $this->uniqueNames($categories);
        $vendors = array_column(array_filter($tracker['vendors'] ?? [], 'is_array'), 'name');
        foreach ($plan['quotations'] as $q) {
            $vendors[] = $q['vendor_name'];
        }
        $plan['vendors'] = $this->uniqueNames($vendors);
        $plan['unmatched_names'] = array_keys($this->unmatchedNames);
        sort($plan['unmatched_names']);

        return $plan;
    }

    /**
     * Write a plan to the database. Idempotent: a second run updates rows in
     * place (matched by legacy_id) and never duplicates.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, array<string, int>>
     */
    public function apply(array $plan): array
    {
        $stats = [];
        $tally = function (string $key, bool $created) use (&$stats): void {
            $stats[$key] ??= ['created' => 0, 'updated' => 0];
            $stats[$key][$created ? 'created' : 'updated']++;
        };
        $disk = Storage::disk('project_files');

        DB::transaction(function () use ($plan, &$stats, $tally, $disk) {
            foreach ($plan['categories'] as $i => $name) {
                MasterOption::firstOrCreate(['type' => 'project_category', 'name' => $name], ['sort_order' => $i, 'is_active' => true]);
            }
            $vendorIds = [];
            foreach ($plan['vendors'] as $i => $name) {
                $vendorIds[mb_strtolower($name)] = MasterOption::firstOrCreate(['type' => 'project_vendor', 'name' => $name], ['sort_order' => $i, 'is_active' => true])->id;
            }

            $projectIds = [];
            foreach ($plan['projects'] as $p) {
                $model = Project::firstOrNew(['legacy_id' => $p['legacy_id']]);
                $tally('projects', ! $model->exists);
                $attrs = $p;
                unset($attrs['updated_at']);
                $model->fill($attrs);
                $model->updated_at = $p['updated_at'] ?? Carbon::now();
                if (! $model->exists) {
                    $model->created_at = $p['updated_at'] ?? Carbon::now();
                }
                $model->saveQuietly();
                $projectIds[$p['legacy_id']] = $model->id;
            }

            $stageIds = [];
            foreach ($plan['stages'] as $s) {
                if (! isset($projectIds[$s['project_legacy_id']])) {
                    continue;
                }
                $model = ProjectStage::firstOrNew(['legacy_id' => $s['legacy_id']]);
                $tally('stages', ! $model->exists);
                $attrs = $s;
                unset($attrs['project_legacy_id']);
                $model->fill($attrs + ['project_id' => $projectIds[$s['project_legacy_id']]])->save();
                $stageIds[$s['legacy_id']] = $model->id;
            }

            foreach ($plan['milestones'] as $m) {
                if (! isset($projectIds[$m['project_legacy_id']])) {
                    continue;
                }
                $model = ProjectMilestone::firstOrNew(['legacy_id' => $m['legacy_id']]);
                $tally('milestones', ! $model->exists);
                $attrs = $m;
                unset($attrs['project_legacy_id']);
                $model->fill($attrs + ['project_id' => $projectIds[$m['project_legacy_id']]])->save();
            }

            $quotationIds = [];
            foreach ($plan['quotations'] as $q) {
                if (! isset($projectIds[$q['project_legacy_id']])) {
                    continue;
                }
                $model = ProjectQuotation::firstOrNew(['legacy_id' => $q['legacy_id']]);
                $tally('quotations', ! $model->exists);
                $attrs = $q;
                unset($attrs['project_legacy_id'], $attrs['file_stored_name']);
                $model->fill($attrs + [
                    'project_id' => $projectIds[$q['project_legacy_id']],
                    'vendor_option_id' => $vendorIds[mb_strtolower($q['vendor_name'])] ?? null,
                ])->save();
                $quotationIds[$q['legacy_id']] = $model->id;
            }

            foreach ($plan['purchase_orders'] as $po) {
                if (! isset($projectIds[$po['project_legacy_id']])) {
                    continue;
                }
                $model = PurchaseOrder::firstOrNew(['legacy_id' => $po['legacy_id']]);
                $tally('purchase_orders', ! $model->exists);
                $attrs = $po;
                unset($attrs['project_legacy_id'], $attrs['quotation_legacy_id'], $attrs['items'], $attrs['created_at']);
                $model->fill($attrs + [
                    'project_id' => $projectIds[$po['project_legacy_id']],
                    'quotation_id' => $po['quotation_legacy_id'] !== null ? ($quotationIds[$po['quotation_legacy_id']] ?? null) : null,
                ]);
                if (! $model->exists && $po['created_at']) {
                    $model->created_at = $po['created_at'];
                }
                $model->save();
                $model->items()->delete();
                foreach ($po['items'] as $n => $item) {
                    $model->items()->create($item + ['sort_order' => $n]);
                }
            }

            foreach ($plan['vendor_contacts'] as $vc) {
                $known = VendorContact::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($vc['name'])])->exists();
                if ($known) {
                    continue;
                }
                $tally('vendor_contacts', true);
                VendorContact::create($vc);
            }

            // The PO template is only seeded: once an admin saved one in this app a re-import must not overwrite it.
            if ($plan['po_template'] !== null && ProjectSetting::read(PurchaseOrderSettings::KEY) === []) {
                PurchaseOrderSettings::save(array_filter($plan['po_template'], fn ($v) => $v !== '' && $v !== []));
                $tally('po_template', true);
            }

            $improvementIds = [];
            foreach ($plan['improvements'] as $imp) {
                $model = Improvement::firstOrNew(['legacy_id' => $imp['legacy_id']]);
                $tally('improvements', ! $model->exists);
                $attrs = $imp;
                unset($attrs['project_legacy_id'], $attrs['updated_at']);
                $model->fill($attrs + ['project_id' => $projectIds[$imp['project_legacy_id']] ?? null]);
                $model->updated_at = $imp['updated_at'] ?? Carbon::now();
                if (! $model->exists) {
                    $model->created_at = $imp['submitted_at'] ?? Carbon::now();
                }
                $model->saveQuietly();
                $improvementIds[$imp['legacy_id']] = $model->id;
            }

            $toolingIds = [];
            foreach ($plan['toolings'] as $t) {
                $toolingIds[$t['legacy_id']] = $this->applyTooling($t, $projectIds, $tally);
            }

            // Files an earlier import put in pm_files (IMP-AFTER-* before
            // afterAttachments were mapped) now belong to pm_improvement_files.
            $movedToImprovement = array_column($plan['improvement_files'], 'legacy_id');
            if ($movedToImprovement !== []) {
                $removed = ProjectFile::whereIn('legacy_id', $movedToImprovement)->whereNull('project_id')->delete();
                if ($removed > 0) {
                    $stats['files_moved_to_improvement'] = ['removed_from_pm_files' => $removed];
                }
            }

            $quotationFile = [];
            foreach ($plan['files'] as $f) {
                $state = $this->copyFile($disk, $f['stored_name'], $f['size_bytes']);
                $stats['files_copy'][$state] = ($stats['files_copy'][$state] ?? 0) + 1;
                $model = ProjectFile::firstOrNew(['legacy_id' => $f['legacy_id']]);
                $tally('files', ! $model->exists);
                $attrs = $f;
                unset($attrs['project_legacy_id'], $attrs['stage_legacy_id'], $attrs['tooling_legacy_id'], $attrs['source_state']);
                $model->fill($attrs + [
                    'project_id' => $f['project_legacy_id'] !== null ? ($projectIds[$f['project_legacy_id']] ?? null) : null,
                    'stage_id' => $f['stage_legacy_id'] !== null ? ($stageIds[$f['stage_legacy_id']] ?? null) : null,
                    'tooling_id' => $f['tooling_legacy_id'] !== null ? ($toolingIds[$f['tooling_legacy_id']] ?? null) : null,
                    'is_missing' => $state === 'missing',
                ])->save();
                $quotationFile[$f['stored_name']] = $model->id;
            }
            foreach ($plan['quotations'] as $q) {
                if ($q['file_stored_name'] !== '' && isset($quotationIds[$q['legacy_id']], $quotationFile[$q['file_stored_name']])) {
                    ProjectQuotation::whereKey($quotationIds[$q['legacy_id']])->update(['file_id' => $quotationFile[$q['file_stored_name']]]);
                }
            }

            foreach ($plan['improvement_files'] as $f) {
                if (! isset($improvementIds[$f['improvement_legacy_id']])) {
                    continue;
                }
                $state = $this->copyFile($disk, $f['stored_name'], $f['size_bytes']);
                $stats['files_copy'][$state] = ($stats['files_copy'][$state] ?? 0) + 1;
                $model = ImprovementFile::firstOrNew(['legacy_id' => $f['legacy_id']]);
                $tally('improvement_files', ! $model->exists);
                $attrs = $f;
                unset($attrs['improvement_legacy_id'], $attrs['source_state']);
                $model->fill($attrs + [
                    'improvement_id' => $improvementIds[$f['improvement_legacy_id']],
                    'is_missing' => $state === 'missing',
                ])->save();
            }

            foreach ($plan['templates'] as $t) {
                $model = ProjectStageTemplate::firstOrNew(['legacy_id' => $t['legacy_id']]);
                $tally('templates', ! $model->exists);
                $model->fill($t)->save();
            }

            $existing = ActivityLog::query()->where('module', 'PROJECT')
                ->where('record_id', 'like', 'legacy:%')->pluck('record_id')->flip();
            $rows = [];
            foreach ($plan['logs'] as $l) {
                $recordId = 'legacy:'.$l['legacy_id'];
                if (isset($existing[$recordId])) {
                    $stats['logs']['updated'] = ($stats['logs']['updated'] ?? 0) + 1;

                    continue;
                }
                $stats['logs']['created'] = ($stats['logs']['created'] ?? 0) + 1;
                $rows[] = [
                    'user_id' => $this->resolveUser($l['actor']),
                    'user_name' => mb_substr($l['actor'], 0, 150),
                    'action' => 'LEGACY_LOG',
                    'module' => 'PROJECT',
                    'record_id' => $recordId,
                    'record_label' => mb_substr($l['text'], 0, 255),
                    'new_data' => json_encode(['scope' => $l['scope'], 'text' => $l['text']], JSON_UNESCAPED_UNICODE),
                    'created_at' => $l['created_at'],
                ];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('activity_logs')->insert($chunk);
            }
            $stats['logs'] ??= ['created' => 0, 'updated' => 0];
            $stats['logs'] += ['created' => 0, 'updated' => 0];

            ActivityLog::create([
                'action' => 'IMPORT',
                'module' => 'PROJECT',
                'record_label' => 'projects:import-legacy',
                'new_data' => json_encode($stats),
            ]);
        });

        return $stats;
    }

    /** Copy one file into the project_files disk and verify its size. Returns copied|present|missing. */
    private function copyFile(Filesystem $disk, string $stored, int $expectedSize): string
    {
        $safe = basename($stored);
        $source = rtrim($this->filesPath, '/\\').DIRECTORY_SEPARATOR.$safe;
        if ($safe === '' || ! is_file($source)) {
            return 'missing';
        }
        $size = (int) filesize($source);
        if ($disk->exists($safe) && $disk->size($safe) === $size) {
            return 'present';
        }
        $stream = fopen($source, 'rb');
        if ($stream === false) {
            return 'missing';
        }
        $disk->put($safe, $stream);
        fclose($stream);
        if ($disk->size($safe) !== $size) {
            $disk->delete($safe);
            throw new RuntimeException("Size mismatch after copying {$safe}: expected {$size}, got a different size on the destination disk.");
        }

        return 'copied';
    }

    /** @param array<string, mixed> $file */
    private function sourceState(array $file): string
    {
        $safe = basename((string) $file['stored_name']);
        $path = rtrim($this->filesPath, '/\\').DIRECTORY_SEPARATOR.$safe;
        if ($safe === '' || ! is_file($path)) {
            return 'missing';
        }

        return (int) filesize($path) === (int) $file['size_bytes'] ? 'ok' : 'size-differs';
    }

    private function loadUsers(): void
    {
        $this->usersByName = $this->usersByEmail = $this->userCache = [];
        $names = [];
        foreach (User::query()->whereNull('deleted_at')->get(['id', 'name', 'email']) as $u) {
            $this->usersByEmail[mb_strtolower(trim((string) $u->email))] = $u->id;
            $key = mb_strtolower(trim((string) $u->name));
            $names[$key] = isset($names[$key]) ? null : $u->id; // ambiguous => null
        }
        $this->usersByName = array_filter($names, fn ($v) => $v !== null);
    }

    /** Map a legacy plain-text name to a Trial-app user id, or null (never creates accounts). */
    private function resolveUser(string $name): ?int
    {
        $key = mb_strtolower(trim($name));
        if ($key === '') {
            return null;
        }
        if (array_key_exists($key, $this->userCache)) {
            return $this->userCache[$key];
        }
        $id = null;
        if (isset($this->explicitMap[$key])) {
            $id = $this->usersByEmail[$this->explicitMap[$key]] ?? null;
        }
        $id ??= $this->usersByName[$key] ?? $this->usersByEmail[$key] ?? null;
        if ($id === null) {
            $this->unmatchedNames[trim($name)] = true;
        }

        return $this->userCache[$key] = $id;
    }

    /** @return array<string, mixed> */
    private function decode(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }
        $data = json_decode((string) $json, true);

        return is_array($data) ? $data : [];
    }

    private function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function ms(mixed $value): ?Carbon
    {
        $ms = (int) $value;

        return $ms > 0 ? Carbon::createFromTimestampMs($ms) : null;
    }

    /**
     * @param  list<mixed>  $names
     * @return list<string>
     */
    private function uniqueNames(array $names): array
    {
        $out = [];
        foreach ($names as $n) {
            $n = trim((string) $n);
            if ($n !== '') {
                $out[mb_strtolower($n)] ??= $n;
            }
        }

        return array_values($out);
    }

    /**
     * One Check Tooling request of the blob as plain arrays (items and
     * approvals nested). The status is re-derived from the signatures and the
     * sample status like ToolingWorkflow does, except the manual Revisi / Hold,
     * Ditolak and an explicit Approved, which are kept.
     *
     * @param  array<string, mixed>  $t
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function planTooling(array $t, array &$warnings): array
    {
        $id = (string) $t['id'];
        $signed = [];
        $approvals = [];
        foreach ($t['approvals'] ?? [] as $a) {
            if (! is_array($a) || ! in_array($a['role'] ?? null, ToolingOptions::ROLES, true)) {
                continue;
            }
            $role = (string) $a['role'];
            $name = mb_substr(trim((string) ($a['name'] ?? '')), 0, 120);
            $sig = (string) ($a['sig'] ?? '');
            $signed[$role] = ! empty($a['approved']);
            $approvals[$role] = [
                'role' => $role,
                'approved' => $signed[$role],
                'signer_name' => $name,
                'signer_user_id' => $signed[$role] ? $this->resolveUser($name) : null,
                'sign_date' => $this->date($a['date'] ?? null),
                'signed_at' => $this->ms($a['at'] ?? null),
                'note' => $this->nullableText($a['note'] ?? null),
                'signature_method' => 'legacy',
                'signature_image' => strlen($sig) <= 65536 && preg_match('#^data:image/(?:png|jpeg);base64,[A-Za-z0-9+/=]+$#', $sig) === 1 ? $sig : null,
                'signed_by_account' => mb_substr(trim((string) ($a['signedBy'] ?? '')), 0, 100),
            ];
        }

        $sampleStatus = in_array($t['sampleStatus'] ?? '', ToolingOptions::SAMPLE_STATUSES, true) ? (string) $t['sampleStatus'] : 'Menunggu Sampel';
        $hasSample = in_array($sampleStatus, ['Sampel Diterima', 'Selesai Dicek'], true);
        $is = fn (string $r): bool => $signed[$r] ?? false;
        $derived = match (true) {
            $is('prni') && $is('rni') && $is('pi') && $is('production') => ToolingOptions::STATUS_APPROVED,
            $is('pi') => ToolingOptions::STATUS_PRODUCTION,
            $is('rni') && $hasSample => ToolingOptions::STATUS_PIE_CHECK,
            $is('rni') => ToolingOptions::STATUS_WAITING_SAMPLE,
            $hasSample => ToolingOptions::STATUS_WAITING_RNI,
            default => ToolingOptions::STATUS_WAITING_RNI_SAMPLE,
        };
        $legacyStatus = (string) ($t['status'] ?? '');
        $kept = [ToolingOptions::STATUS_HOLD, ToolingOptions::STATUS_REJECTED, ToolingOptions::STATUS_APPROVED];
        $status = in_array($legacyStatus, $kept, true) ? $legacyStatus : $derived;
        if ($status !== $legacyStatus) {
            $warnings[] = "tooling {$id} (".($t['npdNo'] ?? '')."): status '{$legacyStatus}' imported as '{$status}' (derived from signatures + sample).";
        }

        $items = [];
        foreach (array_values($t['tools'] ?? []) as $i => $tool) {
            if (! is_array($tool)) {
                continue;
            }
            $items[] = [
                'legacy_id' => isset($tool['id']) ? mb_substr((string) $tool['id'], 0, 40) : null,
                'tool' => mb_substr(trim((string) ($tool['tool'] ?? '')), 0, 160),
                'actual' => mb_substr(trim((string) ($tool['actual'] ?? '')), 0, 255),
                'status' => in_array($tool['status'] ?? '', ToolingOptions::TOOL_STATUSES, true) ? (string) $tool['status'] : '',
                'sort_order' => $i,
            ];
        }

        $text = fn (string $key, int $max): string => mb_substr(trim((string) ($t[$key] ?? '')), 0, $max);

        return [
            'legacy_id' => $id,
            'doc_code' => $text('docCode', 50) ?: ToolingOptions::DOC_CODE,
            'npd_no' => $text('npdNo', 120),
            'request_date' => $this->date($t['requestDate'] ?? null),
            'requested_by_name' => $text('requestedBy', 100),
            'submitted_by_name' => $text('submittedBy', 100),
            'submitted_by_user_id' => $this->resolveUser((string) ($t['submittedBy'] ?? '')),
            'submitted_at' => $this->ms($t['submittedAt'] ?? null),
            'sample_status' => $sampleStatus,
            'sample_send_date' => $this->date($t['sampleSendDate'] ?? null),
            'sample_send_note' => $this->nullableText($t['sampleSendNote'] ?? null),
            'sample_received_date' => $this->date($t['sampleReceivedDate'] ?? null),
            'sample_received_note' => $this->nullableText($t['sampleReceivedNote'] ?? null),
            'sample_received_by_name' => $text('sampleReceivedBy', 100),
            'sample_received_by_user_id' => $this->resolveUser((string) ($t['sampleReceivedBy'] ?? '')),
            'due_date' => $this->date($t['dueDate'] ?? null),
            'is_critical' => ! empty($t['isCritical']),
            'client_code' => $text('clientCode', 100),
            'product_name' => $text('productName', 255),
            'marketing' => $text('marketing', 120),
            'packdev' => $text('packdev', 120),
            'product_type' => in_array($t['productType'] ?? '', ToolingOptions::PRODUCT_TYPES, true) ? (string) $t['productType'] : 'skin_care',
            'packaging_type' => $text('packagingType', 255),
            'quantity_po' => $text('quantityPo', 120),
            'launch_date' => $text('launchDate', 120),
            'dimension_packaging' => $this->nullableText($t['dimensionPackaging'] ?? null),
            'material_packaging' => $this->nullableText($t['materialPackaging'] ?? null),
            'bom_primary' => $this->nullableText($t['bomPrimary'] ?? null),
            'pm_ref_detail' => $this->nullableText($t['pmRefDetail'] ?? null),
            'links' => $this->links($t['links'] ?? []),
            'formula_info' => $this->nullableText($t['formulaInfo'] ?? null),
            'volume_suggest' => $text('volumeSuggest', 255),
            'recommended_machine' => $text('recommendedMachine', 255),
            'need_invest' => in_array($t['needInvest'] ?? '', ToolingOptions::INVEST_VALUES, true) ? (string) $t['needInvest'] : '',
            'need_invest_note' => $this->nullableText($t['needInvestNote'] ?? null),
            'production_note' => $this->nullableText($t['productionNote'] ?? null),
            'risk_level' => in_array($t['riskLevel'] ?? '', ToolingOptions::RISK_LEVELS, true) ? (string) $t['riskLevel'] : '',
            'pi_owner_name' => $text('piOwner', 100),
            'pi_owner_user_id' => $this->resolveUser((string) ($t['piOwner'] ?? '')),
            'status' => $status,
            'project_legacy_id' => (string) ($t['projectId'] ?? ''),
            'history_log' => array_values(array_filter($t['historyLog'] ?? [], 'is_array')),
            'updated_by_name' => $text('updatedBy', 100),
            'updated_at' => $this->ms($t['updatedAt'] ?? null),
            'items' => $items,
            'approvals' => array_values($approvals),
        ];
    }

    /**
     * Writes one planned tooling with its items and approvals; returns its id.
     * Items are replaced wholesale on every run; approvals are matched by role.
     *
     * @param  array<string, mixed>  $t
     * @param  array<string, int>  $projectIds
     */
    private function applyTooling(array $t, array $projectIds, callable $tally): int
    {
        $model = Tooling::firstOrNew(['legacy_id' => $t['legacy_id']]);
        $tally('toolings', ! $model->exists);
        $attrs = $t;
        unset($attrs['project_legacy_id'], $attrs['updated_at'], $attrs['items'], $attrs['approvals']);
        $model->fill($attrs + ['project_id' => $projectIds[$t['project_legacy_id']] ?? null]);
        $model->updated_at = $t['updated_at'] ?? Carbon::now();
        if (! $model->exists) {
            $model->created_at = $t['submitted_at'] ?? $t['updated_at'] ?? Carbon::now();
        }
        $model->saveQuietly();

        ToolingItem::where('tooling_id', $model->id)->delete();
        foreach ($t['items'] as $item) {
            ToolingItem::create($item + ['tooling_id' => $model->id]);
        }

        foreach ($t['approvals'] as $a) {
            $approval = ToolingApproval::firstOrNew(['tooling_id' => $model->id, 'role' => $a['role']]);
            $tally('tooling_approvals', ! $approval->exists);
            $approval->fill($a)->save();
        }

        return $model->id;
    }

    /**
     * Legacy links are {id, title, url, description}; keep http(s) only.
     *
     * @return list<array{title: string, url: string}>
     */
    private function links(mixed $links): array
    {
        $out = [];
        foreach (is_array($links) ? $links : [] as $link) {
            $url = trim((string) (is_array($link) ? ($link['url'] ?? '') : $link));
            if (preg_match('#^https?://#i', $url) === 1) {
                $title = is_array($link) ? trim((string) ($link['title'] ?? '')) : '';
                $out[] = ['title' => mb_substr($title, 0, 200), 'url' => mb_substr($url, 0, 500)];
            }
        }

        return $out;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Rows already present in the target, for the dry-run report. Returns null
     * when the pm_* tables do not exist yet (nothing to compare against).
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, int>|null
     */
    public function existingCounts(array $plan): ?array
    {
        if (! Schema::hasTable('pm_projects') || ! Schema::hasTable('pm_improvements')) {
            return null;
        }
        $count = fn (string $table, array $items) => DB::table($table)->whereIn('legacy_id', array_column($items, 'legacy_id'))->count();

        return [
            'projects' => $count('pm_projects', $plan['projects']),
            'stages' => $count('pm_stages', $plan['stages']),
            'quotations' => $count('pm_quotations', $plan['quotations']),
            'files' => $count('pm_files', $plan['files']),
            'improvements' => $count('pm_improvements', $plan['improvements']),
            'toolings' => Schema::hasTable('pm_toolings') ? $count('pm_toolings', $plan['toolings']) : 0,
            'purchase_orders' => Schema::hasTable('pm_purchase_orders') ? $count('pm_purchase_orders', $plan['purchase_orders']) : 0,
        ];
    }
}
