<?php

namespace App\Filament\Shared;

use App\Enums\PeranPengguna;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Select options for picking a petugas in the current KSM.
 * Active petugas only — plus the value already saved on the record, even if that person
 * is now inactive or changed role, so saving an unrelated edit never fails the Select's
 * built-in "value must be one of the options" validation.
 */
final class PilihanPengguna
{
    /** @return array<int, string> */
    public static function petugas(?int $tersimpan = null): array
    {
        return User::query()
            ->whereBelongsTo(Filament::getTenant())
            ->where(fn ($query) => $query
                ->where(fn ($aktif) => $aktif->where('peran', PeranPengguna::Petugas)->where('aktif', true))
                ->when($tersimpan, fn ($q) => $q->orWhereKey($tersimpan)))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->getKey() => $user->name.(($user->aktif && $user->peran === PeranPengguna::Petugas) ? '' : ' (nonaktif)'),
            ])
            ->all();
    }
}
