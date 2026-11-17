<?php

namespace App\Policies;

use App\Models\User;

/**
 * Village admins manage users of their own KSM; master administrators manage everyone.
 * Nobody deletes users — they are deactivated so their history stays intact.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdminKsm()
            && ($user->is_super_admin || ($model->ksm_id !== null && $model->ksm_id === $user->ksm_id));
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, User $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
