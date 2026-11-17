<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanKemitraan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Daftar mitra (Laporan › Kemitraan). */
class TabelDaftarMitra extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanKemitraan::kolomDaftar();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanKemitraan(Filament::getTenant()))->daftar($periode);
    }
}
