<?php

namespace App\Filament\Ksm\Pages\Laporan;

use App\Filament\Ksm\Widgets\Laporan\TabelIndikator;
use App\Filament\Ksm\Widgets\Laporan\TabelRingkasanKinerja;
use App\Filament\Ksm\Widgets\ProgresEkonomiSirkularStats;
use App\Support\Laporan\LaporanKinerja;
use App\Support\Periode;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

class LaporanKinerjaPage extends HalamanLaporan
{
    protected static string $routePath = 'laporan/kinerja';

    protected static ?string $slug = 'laporan/kinerja';

    protected static ?string $title = 'Laporan Kinerja Terintegrasi';

    protected static ?string $navigationLabel = 'Kinerja';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 50;

    protected static function jenisLaporan(): string
    {
        return 'kinerja';
    }

    protected function tabelLaporan(Periode $periode): array
    {
        return (new LaporanKinerja(Filament::getTenant()))->semua($periode);
    }

    public function getWidgets(): array
    {
        return [
            ProgresEkonomiSirkularStats::class,
            TabelIndikator::class,
            TabelRingkasanKinerja::class,
        ];
    }
}
