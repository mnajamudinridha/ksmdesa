<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\RingkasanIuran;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class IuranPerBulanChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Iuran terkumpul per bulan';

    protected ?string $description = '12 bulan terakhir sampai bulan yang terakhir dicatat.';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function getData(): array
    {
        $ringkasan = new RingkasanIuran(Filament::getTenant());
        $perBulan = $ringkasan->perBulan($ringkasan->bulanTerakhir() ?? now()->startOfMonth());

        return [
            'datasets' => [
                [
                    'label' => 'Iuran (Rp)',
                    'data' => array_values($perBulan),
                ],
            ],
            'labels' => array_map(fn (string $ym): string => Carbon::createFromFormat('Y-m', $ym)->translatedFormat('M Y'), array_keys($perBulan)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
