<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanLingkungan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Material per bulan (Laporan › Lingkungan). */
class TabelMaterialPerBulan extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanLingkungan::kolomPerBulan();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanLingkungan(Filament::getTenant()))->perBulan($periode);
    }
}
