<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Periode;
use App\Support\RingkasanIuran;
use App\Support\RingkasanMitra;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Partners, their support and the material bought by buyers — dashboard (this month) and Analitik › Kemitraan. */
class KemitraanStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function getHeading(): ?string
    {
        return 'Kemitraan · '.Periode::dariFilter($this->pageFilters)->label();
    }

    protected function getStats(): array
    {
        $periode = Periode::dariFilter($this->pageFilters);
        $mitra = new RingkasanMitra(Filament::getTenant());
        $perJenis = $mitra->aktifPerJenis();
        $kontribusi = $mitra->kontribusi($periode);

        return [
            Stat::make('Mitra aktif', array_sum($perJenis))
                ->description($perJenis === [] ? 'tambahkan di Data Master → Mitra' : collect($perJenis)->map(fn (int $jumlah, string $jenis): string => "{$jumlah} {$jenis}")->implode(' · '))
                ->descriptionIcon(Heroicon::HandRaised)
                ->color('primary'),
            Stat::make('Dukungan diterima', RingkasanIuran::rupiah((int) array_sum(array_column($kontribusi, 'dukungan'))))
                ->description('pemasukan Buku Kas dari mitra')
                ->color('success'),
            Stat::make('Penjualan ke pengepul', RingkasanIuran::rupiah((int) array_sum(array_column($kontribusi, 'nilai'))))
                ->description('dari Catatan Material')
                ->color('success'),
            Stat::make('Mitra berkontribusi', $mitra->jumlahBerkontribusi($periode))
                ->description('memberi dukungan atau membeli material')
                ->color('gray'),
        ];
    }
}
