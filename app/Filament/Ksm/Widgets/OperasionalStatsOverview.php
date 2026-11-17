<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Hari;
use App\Support\RingkasanPengangkutan;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Admin: today's collection, this month's tonnage, and customers nobody is collecting from. */
class OperasionalStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Operasional pengangkutan';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function getStats(): array
    {
        $ksm = Filament::getTenant();
        $ringkasan = new RingkasanPengangkutan($ksm);
        $hariIni = Hari::tanggal();
        $aktif = $ksm->pelanggan()->where('aktif', true)->count();
        $tanpaPetugas = $ksm->pelanggan()->where('aktif', true)->tanpaPetugasAktif()->count();

        return [
            Stat::make('Diangkut hari ini', $ringkasan->rumahDiangkut($hariIni))
                ->description("dari {$aktif} pelanggan aktif")
                ->descriptionIcon(Heroicon::Truck)
                ->color('success'),
            Stat::make('Perkiraan sampah '.Hari::ini()->translatedFormat('F'), RingkasanPengangkutan::kg($ringkasan->berat(Hari::ini()->startOfMonth()->toDateString(), $hariIni)))
                ->description('14 hari terakhir pada grafik kecil')
                ->descriptionIcon(Heroicon::Scale)
                ->chart(array_values($ringkasan->beratPerHari(14)))
                ->color('primary'),
            Stat::make('Tanpa petugas aktif', $tanpaPetugas)
                ->description($tanpaPetugas > 0 ? 'tetapkan petugas di menu Pelanggan' : 'semua pelanggan aktif punya petugas')
                ->descriptionIcon(Heroicon::UserMinus)
                ->color($tanpaPetugas > 0 ? 'danger' : 'success'),
        ];
    }
}
