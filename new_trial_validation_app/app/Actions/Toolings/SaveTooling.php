<?php

namespace App\Actions\Toolings;

use App\Models\Tooling;
use App\Models\User;
use App\Services\Projects\ToolingWorkflow;
use App\Support\ToolingOptions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates the PRNI request part (section A) of a Check Tooling.
 * Never touches signatures, sample, formula or check results; those have
 * their own steps in ToolingSteps.
 */
class SaveTooling
{
    private const REQUEST_FIELDS = [
        'npd_no', 'request_date', 'requested_by_name', 'client_code', 'product_name', 'marketing', 'packdev',
        'product_type', 'packaging_type', 'quantity_po', 'launch_date', 'dimension_packaging',
        'material_packaging', 'bom_primary', 'pm_ref_detail', 'is_critical',
    ];

    public function __construct(private readonly LogToolingActivity $log) {}

    /**
     * @param  array<string, mixed>  $data  validated input from SaveToolingRequest
     */
    public function __invoke(?Tooling $tooling, array $data, User $user): Tooling
    {
        return DB::transaction(function () use ($tooling, $data, $user) {
            $isNew = $tooling === null;
            $tooling ??= new Tooling;
            $old = $isNew ? null : $tooling->only(self::REQUEST_FIELDS);

            foreach (self::REQUEST_FIELDS as $field) {
                if (! array_key_exists($field, $data)) {
                    continue;
                }
                $value = $data[$field];
                $tooling->{$field} = is_string($value) ? trim($value) : ($value ?? ($field === 'is_critical' ? false : ''));
            }
            foreach (['request_date'] as $date) {
                if (($tooling->{$date} ?? '') === '') {
                    $tooling->{$date} = null;
                }
            }
            foreach (['dimension_packaging', 'material_packaging', 'bom_primary', 'pm_ref_detail'] as $text) {
                if ($tooling->{$text} === '') {
                    $tooling->{$text} = null;
                }
            }

            $tooling->links = array_values(array_filter(array_map(
                fn ($link) => [
                    'title' => trim((string) ($link['title'] ?? '')),
                    'url' => trim((string) ($link['url'] ?? '')),
                ],
                (array) ($data['links'] ?? []),
            ), fn ($link) => $link['url'] !== ''));

            if (ToolingWorkflow::isCriticalPackaging((string) $tooling->packaging_type)) {
                $tooling->is_critical = true;
            }

            if ($isNew) {
                $tooling->doc_code = ToolingOptions::DOC_CODE;
                if (trim((string) $tooling->npd_no) === '') {
                    $tooling->npd_no = ToolingWorkflow::nextNpdNo();
                }
                $tooling->request_date ??= Carbon::today();
                if (trim((string) $tooling->requested_by_name) === '') {
                    $tooling->requested_by_name = $user->name;
                }
                $tooling->submitted_by_user_id = $user->id;
                $tooling->submitted_by_name = $user->name;
                $tooling->submitted_at = Carbon::now();
                $tooling->sample_status = 'Menunggu Sampel';
                $tooling->status = ToolingOptions::STATUS_WAITING_RNI_SAMPLE;
                $tooling->save();
            }

            ($this->log)($user, $isNew ? 'CREATE' : 'UPDATE', $tooling, null, $old, $tooling->only(self::REQUEST_FIELDS));
            $tooling->save();

            return $tooling;
        });
    }
}
