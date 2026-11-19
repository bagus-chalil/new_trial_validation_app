<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD = 'ingyu.ko@cosmax.com';

    private const NEW_REVIEWERS = ['fahrul.rozy@cosmax.com', 'zahra.syahda@cosmax.com'];

    /**
     * Only pending reviews move to the new reviewers. Reviews ingyu.ko already
     * completed are history and stay under his name. 2026_11_19_000001 moved
     * those too, so this puts them back.
     *
     * A row is restored only when it is Reviewed, currently sits on one of the
     * two new reviewers, AND activity_logs holds ingyu.ko's own review-save log
     * for exactly that row (same record_id and same timestamp as reviewed_at).
     * That keeps any review Fahrul/Zahra genuinely did themselves untouched.
     * Idempotent.
     */
    public function up(): void
    {
        if (! Schema::hasTable('trials_review') || ! Schema::hasTable('activity_logs')) {
            return;
        }

        $old = DB::table('users')->whereRaw('LOWER(email) = ?', [self::OLD])->first();
        $newIds = DB::table('users')->whereIn(DB::raw('LOWER(email)'), self::NEW_REVIEWERS)->pluck('id')->all();
        if ($old === null || $newIds === []) {
            return;
        }

        $restored = 0;

        DB::transaction(function () use ($old, $newIds, &$restored) {
            $rows = DB::table('trials_review as r')
                ->join('activity_logs as a', function ($j) use ($old) {
                    $j->on('a.record_id', '=', 'r.id')
                        ->on('a.created_at', '=', 'r.reviewed_at')
                        ->where('a.module', 'REVIEW')
                        ->where('a.action', 'SUBMIT_REVIEW')
                        ->where('a.user_id', $old->id);
                })
                ->where('r.status', 'Reviewed')
                ->whereIn('r.reviewer_user_id', $newIds)
                ->distinct()
                ->pluck('r.id');

            foreach ($rows as $id) {
                DB::table('trials_review')->where('id', $id)->update([
                    'reviewer_user_id' => $old->id,
                    'reviewer_name' => $old->name,
                    'reviewer_email' => $old->email,
                ]);
                $restored++;
            }
        });

        Log::info('PIC migration: completed ingyu.ko reviews restored', ['reviews' => $restored]);
    }

    public function down(): void
    {
        // Intentionally irreversible.
    }
};
