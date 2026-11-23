<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD = 'ingyu.ko@cosmax.com';

    private const NEW_APPROVERS = ['moonsh@cosmax.com', 'jaejung.kim@cosmax.com'];

    private const CUTOFF = '2026-11-16 00:00:00';

    /**
     * Only still-pending work moves to the new approvers. Approvals ingyu.ko
     * already gave (Approved/Rejected trials, signed Line Configuration
     * reports) are history and must stay under his name.
     * 2026_11_19_000001 moved those too, so this puts them back.
     *
     * - Trials: Approved/Rejected trials whose latest SUBMIT_REVIEW log names
     *   ingyu.ko as approver get approver_user_id + approved_by/rejected_by back.
     * - Line Configuration reports: rows already signed (approved_pie_at set,
     *   before the cutoff) go back to ingyu.ko.
     *
     * Idempotent: nothing matches once the rows are restored.
     */
    public function up(): void
    {
        if (! Schema::hasTable('trials_header') || ! Schema::hasTable('activity_logs')) {
            return;
        }

        $old = DB::table('users')->whereRaw('LOWER(email) = ?', [self::OLD])->first();
        $new = DB::table('users')->whereIn(DB::raw('LOWER(email)'), self::NEW_APPROVERS)->get();
        if ($old === null || $new->isEmpty()) {
            return;
        }
        $newIds = $new->pluck('id')->all();
        $newNames = $new->pluck('name')->all();
        $counts = ['trials' => 0, 'line_config' => 0];

        DB::transaction(function () use ($old, $newIds, $newNames, &$counts) {
            $latest = [];
            $logs = DB::table('activity_logs')
                ->where('action', 'SUBMIT_REVIEW')->whereIn('module', ['TRIAL', 'REVIEW'])
                ->where('new_data', 'like', '%"approver"%')
                ->orderBy('id')->get(['record_id', 'new_data']);
            foreach ($logs as $l) {
                $data = json_decode((string) $l->new_data, true);
                if (is_array($data) && isset($data['approver'])) {
                    $latest[(int) $l->record_id] = strtolower(trim((string) $data['approver']));
                }
            }

            $decided = DB::table('trials_header')
                ->whereIn('approver_user_id', $newIds)
                ->whereIn('progress_status', ['Approved', 'Rejected'])
                ->get(['id', 'approved_by', 'rejected_by']);
            foreach ($decided as $t) {
                if (($latest[$t->id] ?? null) !== self::OLD) {
                    continue;
                }
                $update = ['approver_user_id' => $old->id];
                if (in_array($t->approved_by, $newNames, true)) {
                    $update['approved_by'] = $old->name;
                }
                if (in_array($t->rejected_by, $newNames, true)) {
                    $update['rejected_by'] = $old->name;
                }
                DB::table('trials_header')->where('id', $t->id)->update($update);
                $counts['trials']++;
            }

            if (Schema::hasTable('trial_line_configuration_reports')) {
                $reports = DB::table('trial_line_configuration_reports')
                    ->whereIn('approved_pie_user_id', $newIds)
                    ->whereNotNull('approved_pie_at')->where('approved_pie_at', '<', self::CUTOFF)
                    ->get(['id', 'approved_pie_by']);
                foreach ($reports as $r) {
                    $update = ['approved_pie_user_id' => $old->id];
                    if (in_array($r->approved_pie_by, $newNames, true)) {
                        $update['approved_pie_by'] = $old->name;
                    }
                    DB::table('trial_line_configuration_reports')->where('id', $r->id)->update($update);
                    $counts['line_config']++;
                }
            }
        });

        Log::info('PIC migration: decided ingyu.ko approvals restored', $counts);
    }

    public function down(): void
    {
        // Intentionally irreversible.
    }
};
