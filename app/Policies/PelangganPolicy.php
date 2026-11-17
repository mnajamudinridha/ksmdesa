<?php

namespace App\Policies;

use App\Models\Pelanggan;
use App\Models\User;

/**
 * Phase 1: admins only (petugas/warga accounts already exist, their rights come in Phase 2:
 * petugas will see the customers assigned to them). Customers are deactivated, never deleted.
 */
class PelangganPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function view(User $user, Pelanggan $pelanggan): bool
    {
        return $user->isAdminKsm();
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, Pelanggan $pelanggan): bool
    {
        return $user->isAdminKsm();
    }

    public function delete(User $user, Pelanggan $pelanggan): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
