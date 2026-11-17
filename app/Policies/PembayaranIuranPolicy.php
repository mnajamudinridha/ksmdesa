<?php

namespace App\Policies;

use App\Models\PembayaranIuran;
use App\Models\User;

/**
 * Financial records: KSM admins only (petugas and warga never reach this resource;
 * warga will see their own payments on their dashboard in Phase 3).
 */
class PembayaranIuranPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, PembayaranIuran $pembayaranIuran): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, PembayaranIuran $pembayaranIuran): bool
    {
        return $user->isAdminKsm();
    }

    public function delete(User $user, PembayaranIuran $pembayaranIuran): bool
    {
        return $user->isAdminKsm();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
