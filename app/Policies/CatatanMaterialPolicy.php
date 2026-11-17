<?php

namespace App\Policies;

use App\Models\CatatanMaterial;
use App\Models\User;

/**
 * Material records are the committee's: admins only, and they may correct or remove any record.
 */
class CatatanMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, CatatanMaterial $catatanMaterial): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, CatatanMaterial $catatanMaterial): bool
    {
        return $user->isAdminKsm();
    }

    public function delete(User $user, CatatanMaterial $catatanMaterial): bool
    {
        return $user->isAdminKsm();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
