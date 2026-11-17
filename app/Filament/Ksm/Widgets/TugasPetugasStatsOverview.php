<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Hari;
use App\Support\RingkasanPengangkutan;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Petugas: how much of today's round is recorded, and their own month so far. */
class TugasPetugasStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isPetugas();
    }

    protected function getStats(): array
    {
        $petugas = auth()->user();
        $ringkasan = new RingkasanPengangkutan(Filament::getTenant(), $petugas);
        $hariIni = Hari::tanggal();
        $tugas = $petugas->pelangganTugas()->where('aktif', true)->count();
        $dicatat = $ringkasan->rumahDicatat($hariIni);

        return [
            Stat::make('Rumah tugas saya', $tugas)
                ->description('pelanggan aktif yang ditetapkan admin')
                ->descriptionIcon(Heroicon::Home)
                ->color('primary'),
            Stat::make('Tercatat hari ini', "{$dicatat} / {$tugas}")
                ->description($ringkasan->rumahDiangkut($hariIni).' rumah diangkut')
                ->descriptionIcon(Heroicon::ClipboardDocumentCheck)
                ->color($tugas > 0 && $dicatat >= $tugas ? 'success' : 'warning'),
            Stat::make('Perkiraan saya '.Hari::ini()->translatedFormat('F'), RingkasanPengangkutan::kg($ringkasan->berat(Hari::ini()->startOfMonth()->toDateString(), $hariIni)))
                ->descriptionIcon(Heroicon::Scale)
                ->chart(array_values($ringkasan->beratPerHari(14)))
                ->color('gray'),
        ];
    }
}
