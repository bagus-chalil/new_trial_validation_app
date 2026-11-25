<?php

namespace App\Policies;

use App\Models\Tooling;
use App\Models\User;
use App\Services\Projects\ToolingWorkflow;
use App\Support\ToolingOptions;

/**
 * Check Tooling access. Admin and the departments in the flow (PRNI, RNI,
 * PIE/PI, Production) read every request; other departments and Viewers
 * read nothing except requests they submitted (tightened 2026-11-25). Each step
 * belongs to one department role from ToolingWorkflow::rolesFor(); Viewer holds
 * none; a box may be assigned to one person (Admin can always sign, see
 * ToolingWorkflow::canSign()). Signatures additionally need the user's password
 * (checked in the FormRequest), see PROJECT_MODULE_PLAN.md Fase 4b.
 */
class ToolingPolicy
{
    public function viewAny(User $user): bool
    {
        return ToolingWorkflow::canSeeAll($user);
    }

    /** Participating departments + Admin see all; others only what they submitted. */
    public function view(User $user, Tooling $tooling): bool
    {
        return ToolingWorkflow::canSeeAll($user)
            || ($tooling->submitted_by_user_id !== null && $tooling->submitted_by_user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return ToolingWorkflow::hasRole($user, 'prni') || ToolingWorkflow::canManage($user);
    }

    /** PRNI edits the request (section A) until PIE has signed; also while on hold for revision. */
    public function update(User $user, Tooling $tooling): bool
    {
        return (ToolingWorkflow::hasRole($user, 'prni') || ToolingWorkflow::canManage($user))
            && ! ToolingWorkflow::isFinal($tooling)
            && ! $tooling->isSigned('pi');
    }

    public function signPrni(User $user, Tooling $tooling): bool
    {
        return ToolingWorkflow::canSign($user, $tooling, 'prni')
            && ToolingWorkflow::isOpen($tooling)
            && ! $tooling->isSigned('prni');
    }

    public function sendSample(User $user, Tooling $tooling): bool
    {
        return (ToolingWorkflow::hasRole($user, 'prni') || ToolingWorkflow::canManage($user))
            && ToolingWorkflow::isOpen($tooling)
            && ! $tooling->hasSample();
    }

    public function signRni(User $user, Tooling $tooling): bool
    {
        return ToolingWorkflow::canSign($user, $tooling, 'rni')
            && ToolingWorkflow::isOpen($tooling)
            && ! $tooling->isSigned('rni');
    }

    public function receiveSample(User $user, Tooling $tooling): bool
    {
        return (ToolingWorkflow::hasRole($user, 'pi') || ToolingWorkflow::canManage($user))
            && ToolingWorkflow::isOpen($tooling)
            && ! $tooling->hasSample();
    }

    public function pieCheck(User $user, Tooling $tooling): bool
    {
        return ToolingWorkflow::canSign($user, $tooling, 'pi') && ToolingWorkflow::canPieCheck($tooling);
    }

    public function signProduction(User $user, Tooling $tooling): bool
    {
        return ToolingWorkflow::canSign($user, $tooling, 'production') && ToolingWorkflow::canProductionSign($tooling);
    }

    /** Choose who signs each box (PRNI or Admin), see ToolingWorkflow::canSign(). */
    public function assign(User $user, Tooling $tooling): bool
    {
        return ToolingWorkflow::canAssign($user, $tooling);
    }

    /** Put on "Revisi / Hold" (back to PRNI) or reject; PIE or Admin, like the old app's status select. */
    public function hold(User $user, Tooling $tooling): bool
    {
        return (ToolingWorkflow::hasRole($user, 'pi') || ToolingWorkflow::canManage($user)) && ToolingWorkflow::isOpen($tooling);
    }

    public function reject(User $user, Tooling $tooling): bool
    {
        return (ToolingWorkflow::hasRole($user, 'pi') || ToolingWorkflow::canManage($user)) && ! ToolingWorkflow::isFinal($tooling);
    }

    public function resume(User $user, Tooling $tooling): bool
    {
        return (ToolingWorkflow::hasRole($user, 'pi') || ToolingWorkflow::canManage($user)) && $tooling->status === ToolingOptions::STATUS_HOLD;
    }

    /** Anyone taking part in the flow may add files until the request is rejected. */
    public function attach(User $user, Tooling $tooling): bool
    {
        return (ToolingWorkflow::rolesFor($user) !== [] || ToolingWorkflow::canManage($user)) && $tooling->status !== ToolingOptions::STATUS_REJECTED;
    }

    public function deleteFile(User $user, Tooling $tooling): bool
    {
        return $user->isAdmin()
            || (ToolingWorkflow::hasRole($user, 'prni') && ! ToolingWorkflow::isFinal($tooling))
            || (ToolingWorkflow::hasRole($user, 'pi') && ! ToolingWorkflow::isFinal($tooling));
    }

    /** "Need Invest = YES" results continue as a Tooling Investment project (PIE or Admin). */
    public function promote(User $user, Tooling $tooling): bool
    {
        return (ToolingWorkflow::hasRole($user, 'pi') || ToolingWorkflow::canManage($user))
            && $tooling->isSigned('pi')
            && $tooling->need_invest === 'YES'
            && $tooling->project_id === null
            && $tooling->status !== ToolingOptions::STATUS_REJECTED;
    }

    public function delete(User $user, Tooling $tooling): bool
    {
        return $user->isAdmin();
    }
}
