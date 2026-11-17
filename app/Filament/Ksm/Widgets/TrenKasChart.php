<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Periode;
use App\Support\RingkasanKas;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

/** Income, spending and closing balance over the 12 months ending with the period. */
class TrenKasChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Tren kas';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getDescription(): string|Htmlable|null
    {
        return '12 bulan sampai '.$this->bulanTerakhir()->translatedFormat('F Y').'. Saldo kosong = sebelum mulai pembukuan.';
    }

    protected function getData(): array
    {
        $bulan = (new RingkasanKas(Filament::getTenant()))->perBulan(Periode::duaBelasBulanSampai($this->bulanTerakhir()));

        return [
            'datasets' => [
                [
                    'label' => 'Pemasukan',
                    'data' => array_map(fn (array $satu): int => $satu['iuran'] + $satu['material'] + $satu['masuk_lain'], $bulan),
                    'borderColor' => '#16a34a',
                    'backgroundColor' => '#16a34a',
                ],
                [
                    'label' => 'Pengeluaran',
                    'data' => array_column($bulan, 'keluar'),
                    'borderColor' => '#dc2626',
                    'backgroundColor' => '#dc2626',
                ],
                [
                    'label' => 'Saldo akhir',
                    'data' => array_column($bulan, 'saldo_akhir'),
                    'borderColor' => '#2563eb',
                    'backgroundColor' => '#2563eb',
                ],
            ],
            'labels' => array_map(fn (array $satu): string => Carbon::createFromFormat('Y-m', $satu['bulan'])->translatedFormat('M Y'), $bulan),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function bulanTerakhir(): CarbonImmutable
    {
        $bulan = Periode::dariFilter($this->pageFilters)->bulanBulan();

        return end($bulan);
    }
}
