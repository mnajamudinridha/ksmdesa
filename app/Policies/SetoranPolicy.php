<?php

namespace App\Policies;

use App\Models\Setoran;
use App\Models\User;

/**
 * Deposits are the committee's books: admins only. A deposit is not edited — cancelling
 * (delete) returns its payments to "belum disetor", then it can be made again.
 */
class SetoranPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, Setoran $setoran): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, Setoran $setoran): bool
    {
        return false;
    }

    public function delete(User $user, Setoran $setoran): bool
    {
        return $user->isAdminKsm();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
