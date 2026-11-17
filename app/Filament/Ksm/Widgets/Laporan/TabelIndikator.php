<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanKinerja;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Indikator ekonomi sirkular (Laporan › Kinerja). */
class TabelIndikator extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanKinerja::kolomIndikator();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanKinerja(Filament::getTenant()))->indikator($periode);
    }
}
