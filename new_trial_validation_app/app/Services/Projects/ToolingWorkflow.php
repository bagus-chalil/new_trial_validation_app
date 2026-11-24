<?php

namespace App\Services\Projects;

use App\Models\Tooling;
use App\Models\User;
use App\Support\ToolingOptions;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Rules of the Check Tooling flow, ported from the production Project
 * Tracking app (api.php ~1130-1160, effToolingStatus()/toolingBottleneck() in
 * public/app.js):
 *
 *   PRNI submits + signs "Prepared by"   -+
 *   PRNI ships the physical sample        +- any order
 *   RNI fills formula + signs "Reviewed"  -+
 *   PIE confirms the sample arrived (starts a 5 working-day SLA)
 *   PIE checks the tooling + signs        (needs PRNI + RNI signed, sample received)
 *   Production signs                      (needs PRNI + RNI + PIE signed) -> Approved
 *
 * The status is derived from the signatures and the sample status so it can
 * never drift from them. "Revisi / Hold" and "Ditolak" are set by hand (PIE or
 * Admin) and override the derived value until released.
 *
 * Roles come from the user's department / review unit (decision 2026-11-23):
 * PRNI -> prni, RNI -> rni, PROD/PRD -> production, PIE/PI -> pi. Viewers hold
 * none. Like Trial Approval (decision 2026-11-24), no single account can sign
 * everything: Admins hold NO signing role (they only manage: create, edit,
 * hold/reject/resume, attach, promote, delete), and one person can sign at
 * most one box on the same request (separation of duties).
 */
class ToolingWorkflow
{
    private const DEPARTMENT_ROLES = [
        'PRNI' => 'prni',
        'RNI' => 'rni',
        'PROD' => 'production',
        'PRD' => 'production',
        'PRODUCTION' => 'production',
        'PIE' => 'pi',
        'PI' => 'pi',
    ];

    /** @return list<string> tooling roles (prni/rni/pi/production) the user acts as */
    public static function rolesFor(User $user): array
    {
        if ($user->isAdmin() || $user->isViewer()) {
            return [];
        }

        $roles = [];
        foreach ([$user->department, $user->review_unit] as $value) {
            $code = strtoupper(trim((string) $value));
            if (isset(self::DEPARTMENT_ROLES[$code])) {
                $roles[] = self::DEPARTMENT_ROLES[$code];
            }
        }

        return array_values(array_unique($roles));
    }

    public static function hasRole(User $user, string $role): bool
    {
        return in_array($role, self::rolesFor($user), true);
    }

    /** Admins manage requests (but never sign): used by the policy for the non-signing steps. */
    public static function canManage(User $user): bool
    {
        return $user->isAdmin();
    }

    /** True when this user already signed a different box on the same request. */
    public static function signedAnotherRole(User $user, Tooling $tooling, string $role): bool
    {
        return $tooling->approvals()
            ->where('signer_user_id', $user->id)
            ->where('approved', true)
            ->where('role', '!=', $role)
            ->exists();
    }

    /** Department role + has not already signed another box on this request. */
    public static function canSign(User $user, Tooling $tooling, string $role): bool
    {
        return self::hasRole($user, $role) && ! self::signedAnotherRole($user, $tooling, $role);
    }

    /** The status implied by the signatures and sample status (a manual hold/reject is kept). */
    public static function deriveStatus(Tooling $tooling): string
    {
        if (in_array($tooling->status, [ToolingOptions::STATUS_HOLD, ToolingOptions::STATUS_REJECTED], true)) {
            return $tooling->status;
        }

        return self::signedStatus($tooling);
    }

    /** The derived status ignoring a manual hold/reject (used when a hold is released). */
    public static function signedStatus(Tooling $tooling): string
    {
        $all = true;
        foreach (ToolingOptions::ROLES as $role) {
            $all = $all && $tooling->isSigned($role);
        }

        return match (true) {
            $all => ToolingOptions::STATUS_APPROVED,
            $tooling->isSigned('pi') => ToolingOptions::STATUS_PRODUCTION,
            $tooling->isSigned('rni') && $tooling->hasSample() => ToolingOptions::STATUS_PIE_CHECK,
            $tooling->isSigned('rni') => ToolingOptions::STATUS_WAITING_SAMPLE,
            $tooling->hasSample() => ToolingOptions::STATUS_WAITING_RNI,
            default => ToolingOptions::STATUS_WAITING_RNI_SAMPLE,
        };
    }

    public static function isFinal(Tooling $tooling): bool
    {
        return in_array($tooling->status, ToolingOptions::FINAL_STATUSES, true);
    }

    /** Not finished and not on hold: signatures and sample steps are allowed. */
    public static function isOpen(Tooling $tooling): bool
    {
        return ! self::isFinal($tooling) && $tooling->status !== ToolingOptions::STATUS_HOLD;
    }

    /**
     * Who the request is waiting on: prni, rni_sample, rni, sample, pie,
     * production, hold, rejected or done (old app: toolingBottleneck()).
     */
    public static function bottleneck(Tooling $tooling): string
    {
        return match (true) {
            $tooling->status === ToolingOptions::STATUS_REJECTED => 'rejected',
            $tooling->status === ToolingOptions::STATUS_HOLD => 'hold',
            $tooling->status === ToolingOptions::STATUS_APPROVED => 'done',
            ! $tooling->isSigned('prni') => 'prni',
            ! $tooling->isSigned('rni') && ! $tooling->hasSample() => 'rni_sample',
            ! $tooling->isSigned('rni') => 'rni',
            ! $tooling->hasSample() => 'sample',
            ! $tooling->isSigned('pi') => 'pie',
            default => 'production',
        };
    }

    /**
     * The next step this user can take on the request, or null: sign_prni,
     * sign_rni, send_sample, receive_sample, pie_check or sign_production.
     * Drives the "your action" banner on the detail page and My Work.
     */
    public static function pendingActionFor(Tooling $tooling, User $user): ?string
    {
        if (! self::isOpen($tooling)) {
            return null;
        }

        $is = fn (string $role): bool => self::canSign($user, $tooling, $role);

        return match (true) {
            $is('prni') && ! $tooling->isSigned('prni') => 'sign_prni',
            $is('rni') && ! $tooling->isSigned('rni') => 'sign_rni',
            self::hasRole($user, 'prni') && $tooling->sample_status === 'Menunggu Sampel' => 'send_sample',
            self::hasRole($user, 'pi') && $tooling->sample_status === 'Sampel Sedang Dikirim' => 'receive_sample',
            $is('pi') && self::canPieCheck($tooling) => 'pie_check',
            $is('production') && self::canProductionSign($tooling) => 'sign_production',
            default => null,
        };
    }

    /** PIE may check once the sample arrived and PRNI + RNI have signed. */
    public static function canPieCheck(Tooling $tooling): bool
    {
        return self::isOpen($tooling)
            && $tooling->hasSample()
            && $tooling->isSigned('prni')
            && $tooling->isSigned('rni')
            && ! $tooling->isSigned('pi');
    }

    /** Production signs last, after PRNI, RNI and PIE. */
    public static function canProductionSign(Tooling $tooling): bool
    {
        return self::isOpen($tooling)
            && $tooling->isSigned('prni')
            && $tooling->isSigned('rni')
            && $tooling->isSigned('pi')
            && ! $tooling->isSigned('production');
    }

    /** Add working days (Mon-Fri) to a date, like the old app's addWorkingDaysStr(). */
    public static function addWorkingDays(CarbonInterface $date, int $days): Carbon
    {
        $d = Carbon::parse($date->toDateString());
        $left = max(1, $days);
        while ($left > 0) {
            $d->addDay();
            if (! $d->isWeekend()) {
                $left--;
            }
        }

        return $d;
    }

    /** Next NPD number: <max+1>/NPD/PKD/<mm>/<yyyy>, starting after 1000 (old app: nextNpdNo()). */
    public static function nextNpdNo(): string
    {
        $max = 1000;
        foreach (Tooling::query()->pluck('npd_no') as $no) {
            if (preg_match('/^(\d+)\/NPD\//i', trim((string) $no), $m) === 1) {
                $max = max($max, (int) $m[1]);
            }
        }
        $now = Carbon::now();

        return ($max + 1).'/NPD/PKD/'.$now->format('m').'/'.$now->format('Y');
    }

    /** Tube / cushion packaging is always critical (old app: normTooling()). */
    public static function isCriticalPackaging(string $packagingType): bool
    {
        return preg_match('/\b(cushion|tube)\b/i', $packagingType) === 1;
    }
}
