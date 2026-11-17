<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Periode;
use App\Support\RingkasanMaterial;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/** Analitik › Lingkungan: recovered material per month, stacked by group, the 12 months ending with the period. */
class MaterialPerBulanChart extends ChartWidget
{
    use InteractsWithPageFilters;

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Material terpulihkan per bulan';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getDescription(): string|Htmlable|null
    {
        $bulan = $this->bulan();

        return '12 bulan sampai '.end($bulan)->translatedFormat('F Y').' · kg per kelompok';
    }

    protected function getData(): array
    {
        $bulan = $this->bulan();
        $perKelompok = (new RingkasanMaterial(Filament::getTenant()))->perKelompokPerBulan($bulan);

        return [
            'datasets' => array_map(fn (string $label, array $data): array => [
                'label' => $label,
                'data' => $data,
                'backgroundColor' => KomposisiMaterialChart::warna($label),
                'borderColor' => KomposisiMaterialChart::warna($label),
            ], array_keys($perKelompok), array_values($perKelompok)),
            'labels' => array_map(fn (CarbonImmutable $awal): string => $awal->translatedFormat('M Y'), $bulan),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return list<CarbonImmutable> */
    private function bulan(): array
    {
        $bulan = Periode::dariFilter($this->pageFilters)->bulanBulan();

        return Periode::duaBelasBulanSampai(end($bulan));
    }
}
