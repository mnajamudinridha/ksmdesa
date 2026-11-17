<?php

namespace App\Policies;

use App\Models\Pengangkutan;
use App\Models\User;

/**
 * Admins manage every round; a petugas records their own and may change or remove it only
 * until the end of the next day (village time). Warga never see rounds as records — only
 * their own houses' history on the dashboard.
 */
class PengangkutanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminKsm() || $user->isPetugas();
    }

    public function view(User $user, Pengangkutan $pengangkutan): bool
    {
        return $user->isAdminKsm() || $this->milikSendiri($user, $pengangkutan);
    }

    public function create(User $user): bool
    {
        return $user->isAdminKsm() || $user->isPetugas();
    }

    public function update(User $user, Pengangkutan $pengangkutan): bool
    {
        return $user->isAdminKsm()
            || ($this->milikSendiri($user, $pengangkutan) && $pengangkutan->bisaDiubahPetugas());
    }

    public function delete(User $user, Pengangkutan $pengangkutan): bool
    {
        return $this->update($user, $pengangkutan);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function milikSendiri(User $user, Pengangkutan $pengangkutan): bool
    {
        return $user->isPetugas() && $pengangkutan->petugas_id === $user->getKey();
    }
}
