<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanOperasional;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Realisasi pengangkutan per RT (Analitik › Operasional dan Laporan › Operasional). */
class TabelRealisasiPerRt extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanOperasional::kolomPerWilayah();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanOperasional(Filament::getTenant()))->perWilayah($periode);
    }
}
