<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Periode;
use App\Support\RingkasanKas;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/** Analitik › Keuangan: the period's spending per category. */
class PengeluaranPerKategoriChart extends ChartWidget
{
    use InteractsWithPageFilters;

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Pengeluaran per kategori';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getDescription(): string|Htmlable|null
    {
        return Periode::dariFilter($this->pageFilters)->label();
    }

    protected function getData(): array
    {
        $periode = Periode::dariFilter($this->pageFilters);
        $perKategori = (new RingkasanKas(Filament::getTenant()))->pengeluaranPerKategori($periode->dariString(), $periode->sampaiString());

        return [
            'datasets' => [
                [
                    'label' => 'Pengeluaran (Rp)',
                    'data' => array_values($perKategori),
                    'backgroundColor' => '#dc2626',
                    'borderColor' => '#dc2626',
                ],
            ],
            'labels' => array_map('strval', array_keys($perKategori)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
