<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Projects\ToolingWorkflow;

/**
 * Purchase Orders (decision 2026-11-24): everyone who can open a project may
 * read and print a PO; creating, changing and deleting one is limited to Admin
 * and to PIE/PI accounts (the old app's admin + user roles), never Viewers.
 * The PO template is Admin only (`manage-projects` Gate).
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return $user->isAdmin() || ToolingWorkflow::hasRole($user, 'pi');
    }
}
