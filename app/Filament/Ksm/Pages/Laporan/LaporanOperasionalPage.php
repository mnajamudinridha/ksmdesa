<?php

namespace App\Filament\Ksm\Pages\Laporan;

use App\Filament\Ksm\Widgets\Laporan\TabelKinerjaPetugas;
use App\Filament\Ksm\Widgets\Laporan\TabelRealisasiPerRt;
use App\Filament\Ksm\Widgets\Laporan\TabelRekapHarian;
use App\Support\Laporan\LaporanOperasional;
use App\Support\Periode;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

class LaporanOperasionalPage extends HalamanLaporan
{
    protected static string $routePath = 'laporan/operasional';

    protected static ?string $slug = 'laporan/operasional';

    protected static ?string $title = 'Laporan Operasional';

    protected static ?string $navigationLabel = 'Operasional';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 20;

    protected static function jenisLaporan(): string
    {
        return 'operasional';
    }

    protected function tabelLaporan(Periode $periode): array
    {
        return (new LaporanOperasional(Filament::getTenant()))->semua($periode);
    }

    public function getWidgets(): array
    {
        return [
            TabelRealisasiPerRt::class,
            TabelKinerjaPetugas::class,
            TabelRekapHarian::class,
        ];
    }
}
