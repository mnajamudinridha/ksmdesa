<?php

namespace App\Filament\Ksm\Pages\Analitik;

use App\Filament\Ksm\Widgets\BeratTerangkutChart;
use App\Filament\Ksm\Widgets\Laporan\TabelRealisasiPerRt;
use App\Filament\Ksm\Widgets\OperasionalPeriodeStats;
use App\Filament\Ksm\Widgets\PengangkutanTerakhirTable;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class AnalitikOperasional extends HalamanAnalitik
{
    protected static string $routePath = 'analitik/operasional';

    protected static ?string $slug = 'analitik/operasional';

    protected static ?string $title = 'Analitik Operasional';

    protected static ?string $navigationLabel = 'Operasional';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?int $navigationSort = 20;

    public function getWidgets(): array
    {
        return [
            OperasionalPeriodeStats::class,
            BeratTerangkutChart::class,
            TabelRealisasiPerRt::class,
            PengangkutanTerakhirTable::class,
        ];
    }
}
