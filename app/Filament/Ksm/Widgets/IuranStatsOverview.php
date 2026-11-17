<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\RingkasanIuran;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class IuranStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $ringkasan = new RingkasanIuran(Filament::getTenant());
        $bulan = $ringkasan->bulanTerakhir();

        $stats = [
            Stat::make('Pelanggan aktif', $ringkasan->jumlahPelangganAktif())
                ->description('dari '.$ringkasan->jumlahPelanggan().' terdaftar')
                ->descriptionIcon('heroicon-m-home-modern')
                ->color('primary'),
        ];

        if ($bulan === null) {
            return [...$stats, Stat::make('Iuran', 'Belum ada catatan')->description('Catat lewat menu Pembayaran Iuran')];
        }

        $terkumpul = $ringkasan->terkumpul($bulan);
        $target = $ringkasan->target();
        $label = $bulan->translatedFormat('F Y');

        return [
            ...$stats,
            Stat::make("Iuran {$label}", RingkasanIuran::rupiah($terkumpul))
                ->description('target '.RingkasanIuran::rupiah($target).($target > 0 ? ' · '.round($terkumpul / $target * 100).'%' : ''))
                ->descriptionIcon('heroicon-m-banknotes')
                ->chart(array_values($ringkasan->perBulan($bulan)))
                ->color($terkumpul >= $target ? 'success' : 'warning'),
            Stat::make("Perlu ditagih ({$label})", $ringkasan->jumlahPerluDitagih($bulan))
                ->description('pelanggan aktif belum/kurang bayar')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
