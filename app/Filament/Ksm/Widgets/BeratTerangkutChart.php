<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Periode;
use App\Support\RingkasanPengangkutan;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

/** Analitik › Operasional: kg per day of the month, or per month of the year. */
class BeratTerangkutChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Perkiraan sampah terangkut';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getDescription(): string|Htmlable|null
    {
        return Periode::dariFilter($this->pageFilters)->label().' · kg dari catatan petugas';
    }

    protected function getData(): array
    {
        $periode = Periode::dariFilter($this->pageFilters);
        $ringkasan = new RingkasanPengangkutan(Filament::getTenant());

        [$data, $label] = $periode->bulan === null
            ? [$ringkasan->beratPerBulan($periode), fn (string $kunci): string => Carbon::createFromFormat('Y-m', $kunci)->translatedFormat('M')]
            : [$ringkasan->beratPerTanggal($periode), fn (string $kunci): string => Carbon::parse($kunci)->translatedFormat('d M')];

        return [
            'datasets' => [
                [
                    'label' => 'Perkiraan (kg)',
                    'data' => array_values($data),
                ],
            ],
            'labels' => array_map($label, array_keys($data)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
