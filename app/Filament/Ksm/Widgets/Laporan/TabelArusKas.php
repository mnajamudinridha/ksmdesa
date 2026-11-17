<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\LaporanKeuangan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Facades\Filament;

/** Arus kas bulanan (Analitik › Keuangan dan Laporan › Keuangan). */
class TabelArusKas extends TabelLaporanWidget
{
    protected static function kolom(): array
    {
        return LaporanKeuangan::kolomArusKas();
    }

    protected function buatTabel(Periode $periode): TabelLaporan
    {
        return (new LaporanKeuangan(Filament::getTenant()))->arusKasBulanan($periode);
    }
}
