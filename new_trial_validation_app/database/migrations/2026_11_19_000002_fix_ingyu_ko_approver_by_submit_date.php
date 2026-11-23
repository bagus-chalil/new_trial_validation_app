<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD = 'ingyu.ko@cosmax.com';

    private const APPROVER_BEFORE = 'moonsh@cosmax.com';

    private const APPROVER_FROM = 'jaejung.kim@cosmax.com';

    private const CUTOFF = '2026-11-16 00:00:00';

    /**
     * Corrects 2026_11_19_000001: that first version sent every undecided
     * ingyu.ko trial to jaejung.kim by "today's date". The right rule is the
     * date ingyu.ko was picked as approver (latest SUBMIT_REVIEW activity log
     * naming him): before 16 Nov => moonsh, from 16 Nov => jaejung.kim.
     *
     * ingyu.ko's rows no longer exist after 000001, so the original approver is
     * recovered from activity_logs (never rewritten by 000001). Only still-open
     * trials whose latest submit named ingyu.ko are touched; trials someone else
     * was picked for are left alone. Idempotent.
     */
    public function up(): void
    {
        if (! Schema::hasTable('trials_header') || ! Schema::hasTable('activity_logs')) {
            return;
        }

        $users = DB::table('users')
            ->whereIn(DB::raw('LOWER(email)'), [self::APPROVER_BEFORE, self::APPROVER_FROM])
            ->get()->keyBy(fn ($u) => strtolower($u->email));
        if (! $users->has(self::APPROVER_BEFORE) || ! $users->has(self::APPROVER_FROM)) {
            return;
        }

        $cutoff = Carbon::parse(self::CUTOFF);
        $pick = fn (string $date) => $users->get(
            Carbon::parse($date)->lt($cutoff) ? self::APPROVER_BEFORE : self::APPROVER_FROM
        );
        $counts = ['trials' => 0, 'line_config' => 0, 'notifications' => 0];

        DB::transaction(function () use ($users, $pick, $cutoff, &$counts) {
            // Latest submit log per trial that names the approver.
            $logs = DB::table('activity_logs')
                ->where('action', 'SUBMIT_REVIEW')->whereIn('module', ['TRIAL', 'REVIEW'])
                ->where('new_data', 'like', '%"approver"%')
                ->orderBy('id')->get(['record_id', 'new_data', 'created_at']);
            $latest = [];
            foreach ($logs as $l) {
                $data = json_decode((string) $l->new_data, true);
                if (is_array($data) && isset($data['approver'])) {
                    $latest[(int) $l->record_id] = ['approver' => strtolower(trim((string) $data['approver'])), 'at' => $l->created_at];
                }
            }

            $moonshId = $users->get(self::APPROVER_BEFORE)->id;
            $jaejungId = $users->get(self::APPROVER_FROM)->id;
            $pendingNames = [$users->get(self::APPROVER_BEFORE)->name, $users->get(self::APPROVER_FROM)->name];

            $open = DB::table('trials_header')
                ->whereIn('approver_user_id', [$moonshId, $jaejungId])
                ->whereNull('approved_at')->whereNull('rejected_at')
                ->whereNotIn('progress_status', ['Approved', 'Rejected'])
                ->get(['id', 'approver_user_id', 'progress_status', 'pending_with']);

            foreach ($open as $t) {
                $info = $latest[$t->id] ?? null;
                if ($info === null || $info['approver'] !== self::OLD) {
                    continue; // someone else was picked: not ours
                }
                $to = $pick($info['at']);
                $update = [];
                if ((int) $t->approver_user_id !== (int) $to->id) {
                    $update['approver_user_id'] = $to->id;
                }
                if ($t->progress_status === 'Ready for Approval' && in_array($t->pending_with, $pendingNames, true) && $t->pending_with !== $to->name) {
                    $update['pending_with'] = $to->name;
                }
                if ($update !== []) {
                    DB::table('trials_header')->where('id', $t->id)->update($update);
                    $counts['trials']++;
                }
            }

            // Line Configuration reports not yet decided by the approver: by report creation date.
            if (Schema::hasTable('trial_line_configuration_reports')) {
                $reports = DB::table('trial_line_configuration_reports')
                    ->where('approved_pie_user_id', $jaejungId)->whereNull('approved_pie_at')
                    ->where('created_at', '<', $cutoff->toDateTimeString())
                    ->get(['id']);
                foreach ($reports as $r) {
                    DB::table('trial_line_configuration_reports')->where('id', $r->id)->update(['approved_pie_user_id' => $moonshId]);
                    $counts['line_config']++;
                }
            }

            // Unread approval notifications that follow the trial's (now corrected) approver.
            if (Schema::hasTable('notifications')) {
                $notes = DB::table('notifications as n')
                    ->join('trials_header as h', 'h.id', '=', 'n.trial_id')
                    ->where('n.type', 'approval')->where('n.is_read', 0)
                    ->whereIn('n.user_id', [$moonshId, $jaejungId])
                    ->whereIn('h.approver_user_id', [$moonshId, $jaejungId])
                    ->whereColumn('n.user_id', '<>', 'h.approver_user_id')
                    ->get(['n.id', 'h.approver_user_id as to_id']);
                foreach ($notes as $n) {
                    DB::table('notifications')->where('id', $n->id)->update(['user_id' => $n->to_id]);
                    $counts['notifications']++;
                }
            }
        });

        Log::info('PIC approver correction (ingyu.ko by submit date) done', $counts);
    }

    public function down(): void
    {
        // Intentionally irreversible.
    }
};
