<?php

namespace App\Policies;

use App\Models\Pelanggan;
use App\Models\User;

/**
 * Admins manage customers; a petugas may view (never change) the customers assigned to them.
 * Customers are deactivated, never deleted.
 */
class PelangganPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm() || $user->isPetugas();
    }

    public function view(User $user, Pelanggan $pelanggan): bool
    {
        return $user->isAdminKsm()
            || ($user->isPetugas() && $pelanggan->petugas_id !== null && $pelanggan->petugas_id === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, Pelanggan $pelanggan): bool
    {
        return $user->isAdminKsm();
    }

    /** A petugas takes fee payments only at their own active customers. */
    public function terimaIuran(User $user, Pelanggan $pelanggan): bool
    {
        return $user->isPetugas() && $pelanggan->aktif && $pelanggan->petugas_id !== null && $pelanggan->petugas_id === $user->getKey();
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
