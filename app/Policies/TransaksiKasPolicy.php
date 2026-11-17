<?php

namespace App\Policies;

use App\Models\TransaksiKas;
use App\Models\User;

/**
 * The cash book is the committee's money: admins only, and they may correct or remove any entry.
 */
class TransaksiKasPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, TransaksiKas $transaksiKas): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, TransaksiKas $transaksiKas): bool
    {
        return $user->isAdminKsm();
    }

    public function delete(User $user, TransaksiKas $transaksiKas): bool
    {
        return $user->isAdminKsm();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
