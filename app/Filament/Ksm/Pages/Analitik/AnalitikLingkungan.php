<?php

namespace App\Filament\Ksm\Pages\Analitik;

use App\Filament\Ksm\Widgets\CatatanMaterialTerakhirTable;
use App\Filament\Ksm\Widgets\KomposisiMaterialChart;
use App\Filament\Ksm\Widgets\Laporan\TabelMaterialPerJenis;
use App\Filament\Ksm\Widgets\LingkunganStats;
use App\Filament\Ksm\Widgets\MaterialPerBulanChart;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class AnalitikLingkungan extends HalamanAnalitik
{
    protected static string $routePath = 'analitik/lingkungan';

    protected static ?string $slug = 'analitik/lingkungan';

    protected static ?string $title = 'Analitik Lingkungan';

    protected static ?string $navigationLabel = 'Lingkungan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;

    protected static ?int $navigationSort = 30;

    public function getWidgets(): array
    {
        return [
            LingkunganStats::class,
            KomposisiMaterialChart::class,
            MaterialPerBulanChart::class,
            TabelMaterialPerJenis::class,
            CatatanMaterialTerakhirTable::class,
        ];
    }
}
