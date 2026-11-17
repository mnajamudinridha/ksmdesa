<?php

namespace App\Filament\Ksm\Widgets;

use App\Enums\FormatAngka;
use App\Support\Indikator;
use App\Support\Laporan\LaporanKinerja;
use App\Support\Periode;
use App\Support\RingkasanKinerja;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** The four circular-economy indicators — this month on the dashboard, the period on Laporan › Kinerja. */
class ProgresEkonomiSirkularStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    /** Rendered with the page: Laporan › Kinerja prints it ("Cetak / PDF"), never as a loading placeholder. */
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function getHeading(): ?string
    {
        return 'Progres ekonomi sirkular · '.Periode::dariFilter($this->pageFilters)->label();
    }

    protected function getStats(): array
    {
        $indikator = (new RingkasanKinerja(Filament::getTenant()))->indikator(Periode::dariFilter($this->pageFilters));

        return array_map(function (string $kunci) use ($indikator): Stat {
            [$label, $rumus] = LaporanKinerja::INDIKATOR[$kunci];
            $nilai = $indikator[$kunci];

            return Stat::make($label, FormatAngka::Persen->tampil($nilai))
                ->description($nilai === null ? 'belum ada data untuk dihitung' : $rumus)
                // Covering all spending (100 %) is the goal for cost independence; 80 % for the rest.
                ->color(Indikator::warna($nilai, $kunci === 'kemandirian' ? 1.0 : 0.8));
        }, array_keys(LaporanKinerja::INDIKATOR));
    }
}
