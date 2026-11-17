<?php

namespace App\Filament\Ksm\Pages\Analitik;

use App\Filament\Ksm\Widgets\IuranStatsOverview;
use App\Filament\Ksm\Widgets\KeuanganStats;
use App\Filament\Ksm\Widgets\KomposisiPemasukanChart;
use App\Filament\Ksm\Widgets\Laporan\TabelArusKas;
use App\Filament\Ksm\Widgets\PengeluaranPerKategoriChart;
use App\Filament\Ksm\Widgets\TrenKasChart;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class AnalitikKeuangan extends HalamanAnalitik
{
    protected static string $routePath = 'analitik/keuangan';

    protected static ?string $slug = 'analitik/keuangan';

    protected static ?string $title = 'Analitik Keuangan';

    protected static ?string $navigationLabel = 'Keuangan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 10;

    public function getWidgets(): array
    {
        return [
            KeuanganStats::class,
            TrenKasChart::class,
            KomposisiPemasukanChart::class,
            PengeluaranPerKategoriChart::class,
            IuranStatsOverview::class,
            TabelArusKas::class,
        ];
    }
}
