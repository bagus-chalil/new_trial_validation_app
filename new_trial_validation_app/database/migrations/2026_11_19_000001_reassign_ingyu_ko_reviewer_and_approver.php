<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD = 'ingyu.ko@cosmax.com';

    /** Reviewer replacements: ISO week odd => FAHRUL, even => ZAHRA. */
    private const REVIEW_ODD = 'fahrul.rozy@cosmax.com';

    private const REVIEW_EVEN = 'zahra.syahda@cosmax.com';

    /** Approver replacements: decided/submitted before the cutoff => MOON, from the cutoff => JAEJUNG. */
    private const APPROVER_BEFORE = 'moonsh@cosmax.com';

    private const APPROVER_FROM = 'jaejung.kim@cosmax.com';

    private const APPROVER_CUTOFF = '2026-11-16 00:00:00';

    /**
     * Hands every reviewer/approver duty held by ingyu.ko over to the new PICs.
     * Only rows that belong to ingyu.ko are touched; every other reviewer and
     * approver is left alone. Idempotent: once no row references ingyu.ko
     * the second run is a no-op. Needs all 4 target users to exist, otherwise
     * it throws (nothing is changed — the whole run is one transaction).
     */
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('trials_review') || ! Schema::hasTable('trials_header')) {
            return;
        }

        $users = DB::table('users')
            ->whereIn(DB::raw('LOWER(email)'), [
                self::OLD, self::REVIEW_ODD, self::REVIEW_EVEN, self::APPROVER_BEFORE, self::APPROVER_FROM,
            ])
            ->get()
            ->keyBy(fn ($u) => strtolower($u->email));

        $old = $users->get(self::OLD);
        if ($old === null) {
            // Nothing to migrate (e.g. fresh install / test DB).
            return;
        }

        foreach ([self::REVIEW_ODD, self::REVIEW_EVEN, self::APPROVER_BEFORE, self::APPROVER_FROM] as $email) {
            if (! $users->has($email)) {
                throw new RuntimeException("PIC migration aborted: user {$email} does not exist.");
            }
        }

        $now = Carbon::now();
        $cutoff = Carbon::parse(self::APPROVER_CUTOFF);

        $reviewerFor = fn (?string $when) => $users->get(
            (($when ? Carbon::parse($when) : $now)->isoWeek % 2 === 1) ? self::REVIEW_ODD : self::REVIEW_EVEN
        );
        $approverFor = fn (?string $decidedAt) => $users->get(
            (($decidedAt ? Carbon::parse($decidedAt) : $now)->lt($cutoff)) ? self::APPROVER_BEFORE : self::APPROVER_FROM
        );
        $isOldName = fn (?string $v) => $v !== null && in_array(strtolower(trim($v)), [strtolower($old->name), self::OLD], true);

        $counts = ['reviews' => 0, 'trials' => 0, 'line_config' => 0, 'notifications' => 0];

        DB::transaction(function () use ($old, $reviewerFor, $approverFor, $isOldName, &$counts) {
            // 1. Reviews (Reviewed rows carry only reviewer_email; Pending rows may carry reviewer_user_id).
            $reviews = DB::table('trials_review')
                ->where(fn ($q) => $q->where('reviewer_user_id', $old->id)->orWhereRaw('LOWER(reviewer_email) = ?', [self::OLD]))
                ->get();
            foreach ($reviews as $r) {
                $to = $reviewerFor($r->reviewed_at);
                DB::table('trials_review')->where('id', $r->id)->update([
                    'reviewer_user_id' => $to->id,
                    'reviewer_name' => $to->name,
                    'reviewer_email' => $to->email,
                ]);
                $counts['reviews']++;
            }

            // 2. Trial approver.
            $trials = DB::table('trials_header')->where('approver_user_id', $old->id)->get();
            foreach ($trials as $t) {
                // Date that decides the approver: the decision date; if still undecided,
                // when the trial was submitted for review (that is when ingyu.ko was
                // picked as approver); else when the trial was created.
                $submittedAt = Schema::hasTable('activity_logs')
                    ? DB::table('activity_logs')
                        ->where('module', 'TRIAL')->where('action', 'SUBMIT_REVIEW')->where('record_id', $t->id)
                        ->min('created_at')
                    : null;
                $to = $approverFor($t->approved_at ?? $t->rejected_at ?? $submittedAt ?? $t->created_at);
                $update = ['approver_user_id' => $to->id];
                if ($isOldName($t->approved_by)) {
                    $update['approved_by'] = $to->name;
                }
                if ($isOldName($t->rejected_by)) {
                    $update['rejected_by'] = $to->name;
                }
                if ($isOldName($t->pending_with)) {
                    $update['pending_with'] = $to->name;
                }
                DB::table('trials_header')->where('id', $t->id)->update($update);
                $counts['trials']++;
            }

            // 3. Line Configuration Report "Approved (PIE)" slot.
            if (Schema::hasTable('trial_line_configuration_reports')) {
                $reports = DB::table('trial_line_configuration_reports')->where('approved_pie_user_id', $old->id)->get();
                foreach ($reports as $rep) {
                    $to = $approverFor($rep->approved_pie_at ?? $rep->created_at);
                    $update = ['approved_pie_user_id' => $to->id];
                    if ($isOldName($rep->approved_pie_by)) {
                        $update['approved_pie_by'] = $to->name;
                    }
                    DB::table('trial_line_configuration_reports')->where('id', $rep->id)->update($update);
                    $counts['line_config']++;
                }
            }

            // 4. Unread notifications still waiting on ingyu.ko (read ones are history, left as-is).
            if (Schema::hasTable('notifications')) {
                $notes = DB::table('notifications')
                    ->where('user_id', $old->id)->where('is_read', 0)->whereIn('type', ['review', 'approval'])
                    ->get();
                foreach ($notes as $n) {
                    if ($n->type === 'approval') {
                        $approverId = $n->trial_id
                            ? DB::table('trials_header')->where('id', $n->trial_id)->value('approver_user_id')
                            : null;
                        $toId = ($approverId && $approverId !== $old->id) ? $approverId : $approverFor($n->created_at)->id;
                    } else {
                        $toId = $reviewerFor($n->created_at)->id;
                    }
                    DB::table('notifications')->where('id', $n->id)->update(['user_id' => $toId]);
                    $counts['notifications']++;
                }
            }
        });

        Log::info('PIC migration ingyu.ko done', $counts);
    }

    public function down(): void
    {
        // Intentionally irreversible: restore from backup if needed.
    }
};
