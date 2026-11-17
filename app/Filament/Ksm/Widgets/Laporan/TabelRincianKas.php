<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanKeuangan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Rincian transaksi Buku Kas (Laporan › Keuangan). */
class TabelRincianKas extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanKeuangan::kolomRincian();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanKeuangan(Filament::getTenant()))->rincian($periode);
    }
}
