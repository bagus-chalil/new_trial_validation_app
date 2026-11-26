<?php

namespace App\Actions\Validation;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\QcoNumber;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Issues the next QCO number QCO-{YYYY}-{NNNN} (PRNI). The sequence comes from
 * a locked vd_number_counters row per year (scope `qco`), never MAX()+1; a
 * unique (year, seq) index is the backstop. No approval step: the issuer
 * issues directly and it is logged. A void number is never reused.
 */
class IssueQcoNumber
{
    /**
     * @param  array{product_id?: int|null, product_name?: string|null, client?: string|null, sales_month?: string|null, qty_po?: int|null, notes?: string|null}  $data
     */
    public function issue(array $data, User $user): QcoNumber
    {
        return DB::transaction(function () use ($data, $user) {
            $now = now();
            $year = (int) $now->format('Y');

            DB::table('vd_number_counters')->insertOrIgnore([
                'scope' => 'qco',
                'period' => (string) $year,
                'last_seq' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $row = DB::table('vd_number_counters')
                ->where('scope', 'qco')
                ->where('period', (string) $year)
                ->lockForUpdate()
                ->first();

            $seq = (int) $row->last_seq + 1;
            DB::table('vd_number_counters')->where('id', $row->id)->update(['last_seq' => $seq, 'updated_at' => $now]);

            $product = ! empty($data['product_id']) ? Product::query()->find((int) $data['product_id']) : null;

            $qco = QcoNumber::create([
                'number' => sprintf('QCO-%d-%04d', $year, $seq),
                'year' => $year,
                'seq' => $seq,
                'product_id' => $product?->id,
                'product_name' => $product !== null ? $product->product_name : trim((string) ($data['product_name'] ?? '')),
                'client' => trim((string) ($data['client'] ?? '')),
                'sales_month' => $data['sales_month'] ?? null,
                'qty_po' => $data['qty_po'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'Issued',
                'issued_by_user_id' => $user->id,
                'issued_by_name' => $user->name,
            ]);

            $this->log($user, 'ISSUE_QCO', $qco);

            return $qco;
        });
    }

    public function void(QcoNumber $qco, string $reason, User $user): void
    {
        if ($qco->status === 'Void') {
            return;
        }

        $qco->status = 'Void';
        $qco->void_reason = $reason;
        $qco->save();

        $this->log($user, 'VOID_QCO', $qco);
    }

    private function log(User $user, string $action, QcoNumber $qco): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'action' => $action,
            'module' => 'QCO_NUMBER',
            'record_id' => (string) $qco->id,
            'record_label' => $qco->number,
            'old_data' => null,
            'new_data' => json_encode(['number' => $qco->number, 'status' => $qco->status, 'reason' => $qco->void_reason]),
        ]);
    }
}
