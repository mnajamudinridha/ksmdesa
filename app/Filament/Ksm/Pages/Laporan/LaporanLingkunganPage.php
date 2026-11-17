<?php

namespace App\Filament\Ksm\Pages\Laporan;

use App\Filament\Ksm\Widgets\Laporan\TabelMaterialPerBulan;
use App\Filament\Ksm\Widgets\Laporan\TabelMaterialPerJenis;
use App\Filament\Ksm\Widgets\Laporan\TabelPenjualanPengepul;
use App\Support\Laporan\LaporanLingkungan;
use App\Support\Periode;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

class LaporanLingkunganPage extends HalamanLaporan
{
    protected static string $routePath = 'laporan/lingkungan';

    protected static ?string $slug = 'laporan/lingkungan';

    protected static ?string $title = 'Laporan Lingkungan';

    protected static ?string $navigationLabel = 'Lingkungan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAsiaAustralia;

    protected static ?int $navigationSort = 30;

    protected static function jenisLaporan(): string
    {
        return 'lingkungan';
    }

    protected function tabelLaporan(Periode $periode): array
    {
        return (new LaporanLingkungan(Filament::getTenant()))->semua($periode);
    }

    public function getWidgets(): array
    {
        return [
            TabelMaterialPerJenis::class,
            TabelPenjualanPengepul::class,
            TabelMaterialPerBulan::class,
        ];
    }
}
