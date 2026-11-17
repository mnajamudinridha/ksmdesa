<?php

namespace App\Filament\Widgets;

use App\Models\PembayaranIuran;
use App\Support\Hari;
use Filament\Widgets\ChartWidget;

/** Master dashboard: fees of all KSMs per fee month, the 12 months up to this one. */
class IuranSemuaKsmChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Iuran semua KSM per bulan';

    protected ?string $description = '12 bulan terakhir, per bulan iuran.';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    protected function getData(): array
    {
        $akhir = Hari::ini()->startOfMonth();
        $awal = $akhir->subMonths(11);

        $total = PembayaranIuran::query()
            ->whereBetween('periode', [$awal->toDateString(), $akhir->endOfMonth()->toDateString()])
            ->get(['periode', 'nominal'])
            ->groupBy(fn (PembayaranIuran $pembayaran): string => $pembayaran->periode->format('Y-m'))
            ->map(fn ($baris): int => (int) $baris->sum('nominal'));

        $label = [];
        $data = [];

        for ($bulan = $awal; $bulan->lessThanOrEqualTo($akhir); $bulan = $bulan->addMonth()) {
            $label[] = $bulan->translatedFormat('M Y');
            $data[] = $total[$bulan->format('Y-m')] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Iuran (Rp)',
                    'data' => $data,
                ],
            ],
            'labels' => $label,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
