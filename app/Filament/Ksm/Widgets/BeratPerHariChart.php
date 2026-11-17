<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\RingkasanPengangkutan;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Admin: estimated kg collected per day across the KSM. */
class BeratPerHariChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Perkiraan sampah terangkut per hari';

    protected ?string $description = '30 hari terakhir, dari catatan petugas (kg).';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function getData(): array
    {
        $perHari = (new RingkasanPengangkutan(Filament::getTenant()))->beratPerHari(30);

        return [
            'datasets' => [
                [
                    'label' => 'Perkiraan (kg)',
                    'data' => array_values($perHari),
                ],
            ],
            'labels' => array_map(fn (string $hari): string => Carbon::parse($hari)->translatedFormat('d M'), array_keys($perHari)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
