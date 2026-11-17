<?php

namespace App\Policies;

use App\Models\PembayaranIuran;
use App\Models\User;

/**
 * Admins manage every fee payment. A petugas sees the payments they collected ("Iuran
 * diterima") and may correct them until the cash is handed over in a deposit (Setoran);
 * they record new ones only through "Terima iuran" at their own customers. A payment
 * inside a deposit is never deleted — cancel the deposit first.
 */
class PembayaranIuranPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm() || $user->isPetugas();
    }

    public function view(User $user, PembayaranIuran $pembayaranIuran): bool
    {
        return $user->isAdminKsm() || $this->dipegangPetugas($user, $pembayaranIuran, adaSetoran: true);
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm();
    }

    public function update(User $user, PembayaranIuran $pembayaranIuran): bool
    {
        return $user->isAdminKsm() || $this->dipegangPetugas($user, $pembayaranIuran);
    }

    public function delete(User $user, PembayaranIuran $pembayaranIuran): bool
    {
        return ($user->isAdminKsm() && $pembayaranIuran->setoran_id === null)
            || $this->dipegangPetugas($user, $pembayaranIuran);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /** Collected by this petugas and — unless $adaSetoran — not yet deposited. */
    private function dipegangPetugas(User $user, PembayaranIuran $pembayaranIuran, bool $adaSetoran = false): bool
    {
        return $user->isPetugas()
            && $pembayaranIuran->petugas_id !== null
            && $pembayaranIuran->petugas_id === $user->getKey()
            && ($adaSetoran || $pembayaranIuran->setoran_id === null);
    }
}
