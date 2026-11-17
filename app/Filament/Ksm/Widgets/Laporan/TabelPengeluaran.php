<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanKeuangan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Pengeluaran per kategori (Laporan › Keuangan). */
class TabelPengeluaran extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanKeuangan::kolomPengeluaran();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanKeuangan(Filament::getTenant()))->pengeluaran($periode);
    }
}
