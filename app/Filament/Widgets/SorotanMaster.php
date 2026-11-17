<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Ksms\KsmResource;
use App\Filament\Shared\SorotanWidget;
use App\Models\Ksm;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/** Master dashboard, beside "Selamat datang": which KSMs have recorded nothing lately. */
class SorotanMaster extends SorotanWidget
{
    /** Right after "Selamat datang" (-3). */
    protected static ?int $sort = -2;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    protected function sorotan(): array
    {
        if (! Ksm::query()->exists()) {
            return [
                'keterangan' => 'Belum ada KSM terdaftar.',
                'tautan' => KsmResource::getUrl('create'),
                'labelTautan' => 'Tambah KSM',
                'ikonTautan' => Heroicon::OutlinedPlus,
            ];
        }

        $hari = Ksm::HARI_AKTIF_MENCATAT;
        $belumMencatat = Ksm::query()
            ->whereNot(fn (Builder $query) => $query->aktifMencatat(Ksm::batasAktifMencatat()))
            ->orderBy('nama')
            ->pluck('nama');

        return [
            'keterangan' => $belumMencatat->isEmpty()
                ? "Semua KSM mencatat dalam {$hari} hari terakhir."
                : "Belum mencatat {$hari} hari: ".self::daftarNama($belumMencatat),
            'tautan' => KsmResource::getUrl('index'),
            'labelTautan' => 'Daftar KSM',
            'ikonTautan' => Heroicon::OutlinedBuildingOffice2,
        ];
    }
}
