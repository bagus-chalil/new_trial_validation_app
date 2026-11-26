<?php

namespace App\Actions\Mixing;

use App\Actions\Validation\EnsureMixingCase;
use App\Models\ActivityLog;
use App\Models\Mixing;
use App\Models\Product;
use App\Models\User;
use App\Models\ValidationCase;
use Illuminate\Support\Facades\DB;

/**
 * Create or update the Mixing header. product_name is always re-read from the
 * Product row (never trusted from the client), like CreateTrial. A new record
 * is put under a MIX Case (the given one, else found/created per product).
 */
class SaveMixingHeader
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $user, ?ValidationCase $case = null): Mixing
    {
        return DB::transaction(function () use ($data, $user, $case) {
            $product = Product::query()->where('id', $data['product_id'])->firstOrFail();

            $mixing = new Mixing;
            $mixing->mixing_code = $this->newCode();
            $mixing->progress_status = 'Draft';
            $mixing->current_step = 'Validation';
            $mixing->revision_no = 0;
            $mixing->pending_with = '';
            $mixing->created_by = (string) $user->email;
            $this->fill($mixing, $data, $product);
            $mixing->save();

            app(EnsureMixingCase::class)($mixing, $user->id, $case);
            $this->log($user, 'CREATE', $mixing);

            return $mixing->fresh() ?? $mixing;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Mixing $mixing, array $data, User $user): Mixing
    {
        $product = Product::query()->where('id', $data['product_id'])->firstOrFail();
        $this->fill($mixing, $data, $product);
        $mixing->save();
        $this->log($user, 'UPDATE', $mixing);

        return $mixing;
    }

    /** @param array<string, mixed> $data */
    private function fill(Mixing $mixing, array $data, Product $product): void
    {
        $mixing->product_id = $product->id;
        $mixing->product_name = (string) $product->product_name;
        $mixing->product_type = (string) $data['product_type'];
        $mixing->batch_no = (string) ($data['batch_no'] ?? '');
        $mixing->batch_size_kg = $data['batch_size_kg'] ?? null;
        $mixing->mixing_machine = (string) ($data['mixing_machine'] ?? '');
        $mixing->mixing_date = $data['mixing_date'] ?? null;
        $mixing->objective = $data['objective'] ?? null;
        $mixing->batch_record_no = (string) ($data['batch_record_no'] ?? '');
        $mixing->batch_record_revision = (string) ($data['batch_record_revision'] ?? '');
        $mixing->revision_summary = $data['revision_summary'] ?? null;
    }

    private function newCode(): string
    {
        do {
            $code = 'MIX-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        } while (Mixing::query()->where('mixing_code', $code)->exists());

        return $code;
    }

    private function log(User $user, string $action, Mixing $mixing): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'MIXING',
            'record_id' => (string) $mixing->id,
            'record_label' => $mixing->mixing_code,
            'old_data' => null,
            'new_data' => json_encode(['product' => $mixing->product_name, 'batch_no' => $mixing->batch_no]),
        ]);
    }
}
