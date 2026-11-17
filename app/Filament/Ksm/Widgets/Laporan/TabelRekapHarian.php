<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanOperasional;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Rekap harian pengangkutan (Laporan › Operasional). */
class TabelRekapHarian extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanOperasional::kolomPerHari();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanOperasional(Filament::getTenant()))->perHari($periode);
    }
}
