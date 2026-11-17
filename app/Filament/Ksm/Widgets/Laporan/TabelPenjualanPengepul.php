<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanLingkungan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Penjualan per pengepul (Laporan › Lingkungan). */
class TabelPenjualanPengepul extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanLingkungan::kolomPerPengepul();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanLingkungan(Filament::getTenant()))->perPengepul($periode);
    }
}
