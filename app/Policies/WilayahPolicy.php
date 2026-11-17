<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wilayah;

/**
 * The village's RT list is KSM master data: admins only. An RT that still has customers
 * stays — deleting it would silently drop them out of the per-RT reports.
 */
class WilayahPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, Wilayah $wilayah): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, Wilayah $wilayah): bool
    {
        return $user->isAdminKsm();
    }

    public function delete(User $user, Wilayah $wilayah): bool
    {
        return $user->isAdminKsm() && ! $wilayah->dipakai();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
