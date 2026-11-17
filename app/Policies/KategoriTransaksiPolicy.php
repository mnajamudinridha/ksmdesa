<?php

namespace App\Policies;

use App\Models\KategoriTransaksi;
use App\Models\User;

/**
 * Cash-book categories are KSM master data: admins only. A category with transactions stays — deactivate it.
 */
class KategoriTransaksiPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, KategoriTransaksi $kategoriTransaksi): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, KategoriTransaksi $kategoriTransaksi): bool
    {
        return $user->isAdminKsm();
    }

    public function delete(User $user, KategoriTransaksi $kategoriTransaksi): bool
    {
        return $user->isAdminKsm() && ! $kategoriTransaksi->dipakai();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
