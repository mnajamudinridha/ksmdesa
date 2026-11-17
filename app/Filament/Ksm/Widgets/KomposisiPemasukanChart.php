<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Periode;
use App\Support\RingkasanKas;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

/** Where the period's income came from: fees, then each income category. */
class KomposisiPemasukanChart extends ChartWidget
{
    use InteractsWithPageFilters;

    /** One colour per slice, repeated when there are more sources. */
    public const array WARNA = ['#16a34a', '#2563eb', '#d97706', '#9333ea', '#0891b2', '#db2777', '#65a30d', '#4b5563'];

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Komposisi pemasukan';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->perSumber() === [] ? 'Belum ada pemasukan pada periode ini.' : Periode::dariFilter($this->pageFilters)->label();
    }

    protected function getData(): array
    {
        $perSumber = $this->perSumber();

        return [
            'datasets' => [
                [
                    'label' => 'Pemasukan (Rp)',
                    'data' => array_values($perSumber),
                    'backgroundColor' => array_map(fn (int $urutan): string => self::WARNA[$urutan % count(self::WARNA)], array_keys(array_values($perSumber))),
                ],
            ],
            'labels' => array_map('strval', array_keys($perSumber)),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** @return array<string, int> */
    private function perSumber(): array
    {
        $periode = Periode::dariFilter($this->pageFilters);

        return (new RingkasanKas(Filament::getTenant()))->pemasukanPerSumber($periode->dariString(), $periode->sampaiString());
    }
}
