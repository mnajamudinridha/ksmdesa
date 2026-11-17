<?php

namespace App\Filament\Ksm\Pages\Analitik;

use App\Filament\Ksm\Widgets\KemitraanStats;
use App\Filament\Ksm\Widgets\Laporan\TabelKontribusiMitra;
use App\Filament\Ksm\Widgets\MitraPerJenisChart;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class AnalitikKemitraan extends HalamanAnalitik
{
    protected static string $routePath = 'analitik/kemitraan';

    protected static ?string $slug = 'analitik/kemitraan';

    protected static ?string $title = 'Analitik Kemitraan';

    protected static ?string $navigationLabel = 'Kemitraan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?int $navigationSort = 40;

    public function getWidgets(): array
    {
        return [
            KemitraanStats::class,
            MitraPerJenisChart::class,
            TabelKontribusiMitra::class,
        ];
    }
}
