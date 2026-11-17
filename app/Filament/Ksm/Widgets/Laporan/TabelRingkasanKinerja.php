<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanKinerja;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Ringkasan kinerja lintas bidang (Laporan › Kinerja). */
class TabelRingkasanKinerja extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanKinerja::kolomRingkasan();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanKinerja(Filament::getTenant()))->ringkasan($periode);
    }
}
