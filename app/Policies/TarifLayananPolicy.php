<?php

namespace App\Policies;

use App\Models\TarifLayanan;
use App\Models\User;

/**
 * Tariffs are KSM financial settings: admins only.
 */
class TarifLayananPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, TarifLayanan $tarifLayanan): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, TarifLayanan $tarifLayanan): bool
    {
        return $user->isAdminKsm();
    }

    public function delete(User $user, TarifLayanan $tarifLayanan): bool
    {
        return $user->isAdminKsm();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
