<?php

namespace App\Policies;

use App\Models\JenisMaterial;
use App\Models\User;

/**
 * Material kinds are KSM master data: admins only. A kind with records stays — deactivate it.
 */
class JenisMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, JenisMaterial $jenisMaterial): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, JenisMaterial $jenisMaterial): bool
    {
        return $user->isAdminKsm();
    }

    public function delete(User $user, JenisMaterial $jenisMaterial): bool
    {
        return $user->isAdminKsm() && ! $jenisMaterial->dipakai();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
