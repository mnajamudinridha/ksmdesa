<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanLingkungan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Material per jenis (Analitik › Lingkungan dan Laporan › Lingkungan). */
class TabelMaterialPerJenis extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanLingkungan::kolomPerJenis();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanLingkungan(Filament::getTenant()))->perJenis($periode);
    }
}
