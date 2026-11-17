<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Periode;
use App\Support\RingkasanKas;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Where the period's money went, per spending category — the dashboard partner of "Komposisi
 * pemasukan": same doughnut and height, so the row has no empty half.
 */
class KomposisiPengeluaranChart extends ChartWidget
{
    use InteractsWithPageFilters;

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Komposisi pengeluaran';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->perKategori() === [] ? 'Belum ada pengeluaran pada periode ini.' : Periode::dariFilter($this->pageFilters)->label();
    }

    protected function getData(): array
    {
        $perKategori = $this->perKategori();

        return [
            'datasets' => [
                [
                    'label' => 'Pengeluaran (Rp)',
                    'data' => array_values($perKategori),
                    'backgroundColor' => array_map(
                        fn (int $urutan): string => KomposisiPemasukanChart::WARNA[$urutan % count(KomposisiPemasukanChart::WARNA)],
                        array_keys(array_values($perKategori)),
                    ),
                ],
            ],
            'labels' => array_map('strval', array_keys($perKategori)),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** @return array<string, int> */
    private function perKategori(): array
    {
        $periode = Periode::dariFilter($this->pageFilters);

        return (new RingkasanKas(Filament::getTenant()))->pengeluaranPerKategori($periode->dariString(), $periode->sampaiString());
    }
}
