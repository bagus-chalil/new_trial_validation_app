<?php

namespace App\Actions\ImMaster;

use App\Actions\Validation\LinkCaseNumber;
use App\Models\ImEvidence;
use App\Models\ImNumber;
use App\Models\ImParameter;
use App\Models\ImRevision;
use App\Models\Trial;
use App\Models\User;
use App\Models\ValidationCase;
use App\Support\ImLog;
use App\Support\ImSections;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the whole IM worksheet of a Draft / Need Revision revision at once:
 * header, free-form blocks, structured parameters (replaced wholesale, same as
 * Weighing) and the evidence list. Evidence of kind `trial` must be an
 * Approved, not deleted trial: its approval is evidence, never the IM approval.
 */
class SaveImWorksheet
{
    public function __construct(private readonly LinkCaseNumber $linker) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(ImRevision $revision, array $data, User $user): ImRevision
    {
        if (! $revision->isEditable()) {
            throw ValidationException::withMessages(['status' => __('im_master.error.locked')]);
        }

        $evidence = $this->evidenceRows((array) ($data['evidence'] ?? []));

        return DB::transaction(function () use ($revision, $data, $user, $evidence) {
            $content = [];
            foreach (ImSections::contentKeys() as $key) {
                $value = trim((string) ($data['content'][$key] ?? ''));
                if ($value !== '') {
                    $content[$key] = $value;
                }
            }

            foreach (['description', 'appearance', 'mfg_category', 'customer', 'customer_ref', 'line_label', 'revision_reason'] as $field) {
                $revision->{$field} = trim((string) ($data[$field] ?? ''));
            }
            $revision->issue_date = $data['issue_date'] ?? null;
            $revision->effective_from = $data['effective_from'] ?? null;
            $revision->legacy_reference = trim((string) ($data['legacy_reference'] ?? '')) ?: null;
            $revision->content = $content;
            $revision->save();

            $master = $revision->master;
            if ($master !== null && array_key_exists('bulk_wip_refs', $data)) {
                $master->bulk_wip_refs = array_values(collect((array) $data['bulk_wip_refs'])
                    ->map(fn ($c) => strtoupper(trim((string) $c)))->filter()->unique()->all());
                $master->save();
            }

            ImParameter::query()->where('revision_id', $revision->id)->delete();
            $order = [];
            foreach ((array) ($data['parameters'] ?? []) as $row) {
                $section = (string) ($row['section'] ?? '');
                if (! array_key_exists($section, ImSections::ALL)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $order[$section] = ($order[$section] ?? 0) + 1;
                $numeric = ImSections::ALL[$section]['numeric'];

                ImParameter::create([
                    'revision_id' => $revision->id,
                    'section' => $section,
                    'sort_order' => $order[$section],
                    'code' => $this->text($row['code'] ?? null, 80),
                    'name' => mb_substr($name, 0, 255),
                    'category' => $this->text($row['category'] ?? null, 80),
                    'description' => $this->text($row['description'] ?? null),
                    'specification' => $this->text($row['specification'] ?? null),
                    'frequency' => $this->text($row['frequency'] ?? null, 255),
                    'value_min' => $numeric ? $this->number($row['value_min'] ?? null) : null,
                    'value_max' => $numeric ? $this->number($row['value_max'] ?? null) : null,
                    'unit' => $numeric ? $this->text($row['unit'] ?? null, 20) : null,
                ]);
            }

            ImEvidence::query()->where('revision_id', $revision->id)->delete();
            foreach ($evidence as $row) {
                ImEvidence::create($row + ['revision_id' => $revision->id, 'added_by_user_id' => $user->id]);
            }

            if (! empty($data['case_id']) && $revision->im_number_id !== null) {
                $case = ValidationCase::query()->find((int) $data['case_id']);
                $number = ImNumber::query()->find($revision->im_number_id);
                if ($case !== null && $number !== null) {
                    $this->linker->link($case, $number, $user);
                }
            }

            ImLog::write($user, 'SAVE_WORKSHEET', $revision, ['parameters' => array_sum($order), 'evidence' => count($evidence)]);

            return $revision;
        });
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private function evidenceRows(array $rows): array
    {
        $out = [];

        foreach ($rows as $i => $row) {
            $row = (array) $row;
            $kind = ($row['kind'] ?? 'trial') === 'document' ? 'document' : 'trial';
            $note = $this->text($row['note'] ?? null);

            if ($kind === 'document') {
                $title = trim((string) ($row['title'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $out[] = [
                    'kind' => 'document', 'trial_id' => null, 'case_id' => null,
                    'title' => mb_substr($title, 0, 255),
                    'reference' => mb_substr(trim((string) ($row['reference'] ?? '')), 0, 255),
                    'note' => $note,
                ];

                continue;
            }

            $trialId = (int) ($row['trial_id'] ?? 0);
            if ($trialId === 0) {
                continue;
            }

            $trial = Trial::query()->whereNull('deleted_at')->find($trialId);
            if ($trial === null || $trial->progress_status !== 'Approved') {
                throw ValidationException::withMessages(["evidence.$i.trial_id" => __('im_master.error.evidence_not_approved')]);
            }

            $out[] = [
                'kind' => 'trial', 'trial_id' => $trial->id, 'case_id' => null,
                'title' => mb_substr(trim((string) $trial->product_name), 0, 255),
                'reference' => $trial->trial_code,
                'note' => $note,
            ];
        }

        return $out;
    }

    private function text(mixed $value, ?int $max = null): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return $max !== null ? mb_substr($value, 0, $max) : $value;
    }

    private function number(mixed $value): ?string
    {
        $value = str_replace(',', '.', trim((string) $value));

        return $value !== '' && is_numeric($value) ? $value : null;
    }
}
