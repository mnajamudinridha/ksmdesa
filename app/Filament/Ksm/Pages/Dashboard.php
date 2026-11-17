<?php

namespace App\Filament\Ksm\Pages;

use App\Filament\Ksm\Widgets\BeratPerHariChart;
use App\Filament\Ksm\Widgets\DaftarTugasHariIni;
use App\Filament\Ksm\Widgets\IuranPerBulanChart;
use App\Filament\Ksm\Widgets\IuranStatsOverview;
use App\Filament\Ksm\Widgets\KelengkapanDataStats;
use App\Filament\Ksm\Widgets\KemitraanStats;
use App\Filament\Ksm\Widgets\KeuanganStats;
use App\Filament\Ksm\Widgets\KomposisiPemasukanChart;
use App\Filament\Ksm\Widgets\KomposisiPengeluaranChart;
use App\Filament\Ksm\Widgets\LingkunganStats;
use App\Filament\Ksm\Widgets\OperasionalStatsOverview;
use App\Filament\Ksm\Widgets\ProgresEkonomiSirkularStats;
use App\Filament\Ksm\Widgets\RiwayatIuranSaya;
use App\Filament\Ksm\Widgets\RiwayatPengangkutanSaya;
use App\Filament\Ksm\Widgets\SorotanKsm;
use App\Filament\Ksm\Widgets\TrenKasChart;
use App\Filament\Ksm\Widgets\TugasPetugasStatsOverview;
use App\Filament\Ksm\Widgets\WargaStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

/**
 * The village dashboard for every role. The widget list is explicit: the default (every
 * widget of the panel) would also put the analytics and report widgets here. Each widget's
 * canView() keeps the list per role; the order below is the order on screen.
 *
 * The grid has two columns and no row may be left half empty (docs/README.md, "Grid dasbor"):
 * half-width widgets come in pairs for every role that sees them, everything else is full width.
 */
class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            SorotanKsm::class,
            // Admin
            KeuanganStats::class,
            ProgresEkonomiSirkularStats::class,
            IuranStatsOverview::class,
            IuranPerBulanChart::class,
            TrenKasChart::class,
            KomposisiPemasukanChart::class,
            KomposisiPengeluaranChart::class,
            OperasionalStatsOverview::class,
            LingkunganStats::class,
            KemitraanStats::class,
            BeratPerHariChart::class,
            KelengkapanDataStats::class,
            // Petugas
            TugasPetugasStatsOverview::class,
            DaftarTugasHariIni::class,
            // Warga
            WargaStatsOverview::class,
            RiwayatIuranSaya::class,
            RiwayatPengangkutanSaya::class,
        ];
    }
}
