<?php

namespace App\Filament\Ksm\Widgets;

use App\Enums\KelompokMaterial;
use App\Support\Periode;
use App\Support\RingkasanMaterial;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/** Analitik › Lingkungan: recovered material per group (plastic, paper, metal…). */
class KomposisiMaterialChart extends ChartWidget
{
    use InteractsWithPageFilters;

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Komposisi material terpulihkan';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->perKelompok() === [] ? 'Belum ada material dipilah/diolah pada periode ini.' : Periode::dariFilter($this->pageFilters)->label().' · kg';
    }

    protected function getData(): array
    {
        $perKelompok = $this->perKelompok();

        return [
            'datasets' => [
                [
                    'label' => 'Terpulihkan (kg)',
                    'data' => array_values($perKelompok),
                    'backgroundColor' => array_map(fn (string $label): string => self::warna($label), array_keys($perKelompok)),
                ],
            ],
            'labels' => array_map('strval', array_keys($perKelompok)),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    public static function warna(string $label): string
    {
        foreach (KelompokMaterial::cases() as $kelompok) {
            if ($kelompok->getLabel() === $label) {
                return $kelompok->warnaGrafik();
            }
        }

        return '#9ca3af';
    }

    /** @return array<string, float> */
    private function perKelompok(): array
    {
        $periode = Periode::dariFilter($this->pageFilters);

        return (new RingkasanMaterial(Filament::getTenant()))->perKelompok($periode->dariString(), $periode->sampaiString());
    }
}
