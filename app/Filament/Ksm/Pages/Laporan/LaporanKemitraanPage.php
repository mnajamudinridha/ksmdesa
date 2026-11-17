<?php

namespace App\Filament\Ksm\Pages\Laporan;

use App\Filament\Ksm\Widgets\Laporan\TabelDaftarMitra;
use App\Filament\Ksm\Widgets\Laporan\TabelKontribusiMitra;
use App\Support\Laporan\LaporanKemitraan;
use App\Support\Periode;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

class LaporanKemitraanPage extends HalamanLaporan
{
    protected static string $routePath = 'laporan/kemitraan';

    protected static ?string $slug = 'laporan/kemitraan';

    protected static ?string $title = 'Laporan Kemitraan';

    protected static ?string $navigationLabel = 'Kemitraan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?int $navigationSort = 40;

    protected static function jenisLaporan(): string
    {
        return 'kemitraan';
    }

    protected function tabelLaporan(Periode $periode): array
    {
        return (new LaporanKemitraan(Filament::getTenant()))->semua($periode);
    }

    public function getWidgets(): array
    {
        return [
            TabelKontribusiMitra::class,
            TabelDaftarMitra::class,
        ];
    }
}
