<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanKemitraan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Kontribusi mitra (Analitik › Kemitraan dan Laporan › Kemitraan). */
class TabelKontribusiMitra extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanKemitraan::kolomKontribusi();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanKemitraan(Filament::getTenant()))->kontribusi($periode);
    }
}
