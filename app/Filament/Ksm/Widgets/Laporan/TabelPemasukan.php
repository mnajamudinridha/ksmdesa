<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanKeuangan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Pemasukan per sumber (Laporan › Keuangan). */
class TabelPemasukan extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanKeuangan::kolomPemasukan();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanKeuangan(Filament::getTenant()))->pemasukan($periode);
    }
}
