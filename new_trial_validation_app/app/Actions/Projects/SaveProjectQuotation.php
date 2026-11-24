<?php

namespace App\Actions\Projects;

use App\Models\MasterOption;
use App\Models\Project;
use App\Models\ProjectQuotation;
use App\Models\User;

class SaveProjectQuotation
{
    public function __construct(private readonly LogProjectActivity $log) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Project $project, ?ProjectQuotation $quotation, array $data, User $user): ProjectQuotation
    {
        $vendor = trim($data['vendor_name']);

        // Link to the vendor master only when it already exists; free-text
        // vendors never create master rows.
        $vendorOption = MasterOption::query()
            ->where('type', 'project_vendor')
            ->where('name', $vendor)
            ->first();

        $attributes = [
            'vendor_name' => $vendor,
            'vendor_option_id' => $vendorOption?->id,
            'quotation_no' => trim((string) ($data['quotation_no'] ?? '')),
            'amount' => $data['amount'] ?? 0,
            'currency' => strtoupper(trim($data['currency'])),
            'quotation_date' => $data['quotation_date'] ?? null,
            'valid_until' => $data['valid_until'] ?? null,
            'status' => $data['status'],
            'note' => $data['note'] ?? null,
            'link' => trim((string) ($data['link'] ?? '')),
        ];

        if (! $quotation) {
            $quotation = new ProjectQuotation;
            $quotation->fill($attributes + ['project_id' => $project->id])->save();
            ($this->log)($user, 'CREATE_QUOTATION', $project->id, $project->name, null, ['vendor' => $vendor, 'status' => $quotation->status]);
        } else {
            $old = $quotation->only(['vendor_name', 'quotation_no', 'status']);
            $quotation->fill($attributes)->save();
            ($this->log)($user, 'UPDATE_QUOTATION', $project->id, $project->name, $old, $quotation->only(array_keys($old)));
        }

        return $quotation;
    }
}
