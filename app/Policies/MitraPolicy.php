<?php

namespace App\Policies;

use App\Models\Mitra;
use App\Models\User;

/**
 * Partners are KSM master data: admins only. A partner named on a transaction stays — deactivate it.
 */
class MitraPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, Mitra $mitra): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, Mitra $mitra): bool
    {
        return $user->isAdminKsm();
    }

    public function delete(User $user, Mitra $mitra): bool
    {
        return $user->isAdminKsm() && ! $mitra->dipakai();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
