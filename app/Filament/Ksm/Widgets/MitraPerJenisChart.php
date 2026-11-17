<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\RingkasanMitra;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/** Analitik › Kemitraan: active partners per kind. */
class MitraPerJenisChart extends ChartWidget
{
    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Mitra aktif per jenis';

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->perJenis() === [] ? 'Belum ada mitra aktif.' : 'Saat ini';
    }

    protected function getData(): array
    {
        $perJenis = $this->perJenis();

        return [
            'datasets' => [
                [
                    'label' => 'Mitra',
                    'data' => array_values($perJenis),
                    'backgroundColor' => array_map(fn (int $urutan): string => KomposisiPemasukanChart::WARNA[$urutan % count(KomposisiPemasukanChart::WARNA)], array_keys(array_values($perJenis))),
                ],
            ],
            'labels' => array_map('strval', array_keys($perJenis)),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** @return array<string, int> */
    private function perJenis(): array
    {
        return (new RingkasanMitra(Filament::getTenant()))->aktifPerJenis();
    }
}
