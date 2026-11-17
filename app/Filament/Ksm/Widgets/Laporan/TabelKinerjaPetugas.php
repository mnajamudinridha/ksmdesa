<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanOperasional;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Kinerja petugas (Laporan › Operasional). */
class TabelKinerjaPetugas extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanOperasional::kolomPerPetugas();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanOperasional(Filament::getTenant()))->perPetugas($periode);
    }
}
