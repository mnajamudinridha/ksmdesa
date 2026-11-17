<?php

namespace App\Filament\Ksm\Widgets;

use App\Enums\FormatAngka;
use App\Support\Indikator;
use App\Support\Periode;
use App\Support\RingkasanIuran;
use App\Support\RingkasanMaterial;
use App\Support\RingkasanPengangkutan;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Recovered, sold and processed material, and the recovery rate — dashboard (this month) and Analitik › Lingkungan. */
class LingkunganStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function getHeading(): ?string
    {
        return 'Lingkungan · '.Periode::dariFilter($this->pageFilters)->label();
    }

    protected function getStats(): array
    {
        $periode = Periode::dariFilter($this->pageFilters);
        [$dari, $sampai] = [$periode->dariString(), $periode->sampaiString()];
        $ksm = Filament::getTenant();
        $material = new RingkasanMaterial($ksm);

        $terpulihkan = $material->terpulihkan($dari, $sampai);
        $terjual = $material->terjual($dari, $sampai);
        $pemulihan = RingkasanPengangkutan::bagi($terpulihkan, (new RingkasanPengangkutan($ksm))->berat($dari, $sampai));

        return [
            Stat::make('Material terpulihkan', RingkasanPengangkutan::kg($terpulihkan))
                ->description('dipilah + diolah')
                ->descriptionIcon(Heroicon::ArrowPathRoundedSquare)
                ->color('success'),
            Stat::make('Terjual', RingkasanPengangkutan::kg($terjual['kg']))
                ->description('senilai '.RingkasanIuran::rupiah($terjual['nilai']))
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('primary'),
            Stat::make('Diolah', RingkasanPengangkutan::kg($material->diolah($dari, $sampai)))
                ->description('mis. kompos')
                ->color('warning'),
            Stat::make('Tingkat pemulihan', FormatAngka::Persen->tampil($pemulihan))
                ->description($pemulihan === null ? 'belum ada sampah terangkut tercatat' : 'terpulihkan ÷ sampah terangkut')
                ->color(Indikator::warna($pemulihan)),
        ];
    }
}
