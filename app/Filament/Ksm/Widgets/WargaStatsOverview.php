<?php

namespace App\Filament\Ksm\Widgets;

use App\Enums\StatusIuran;
use App\Models\Pelanggan;
use App\Support\Hari;
use App\Support\RingkasanIuran;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Warga: one card per house/shop on their account — this month's fee status and who
 * collects there (name and phone, so they can reach them).
 */
class WargaStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isWarga();
    }

    protected function getStats(): array
    {
        $bulan = Hari::ini()->startOfMonth();

        $pelanggan = auth()->user()->pelangganMilik()
            ->whereBelongsTo(Filament::getTenant())
            ->with(['petugas', 'pembayaranIuran' => fn ($query) => $query->whereDate('periode', $bulan->toDateString())])
            ->orderBy('nomor_urut')
            ->orderBy('nama')
            ->get();

        if ($pelanggan->isEmpty()) {
            return [
                Stat::make('Pelanggan', 'Belum terhubung')
                    ->description('Hubungi admin KSM untuk menautkan rumah Anda.'),
            ];
        }

        return $pelanggan->map(function (Pelanggan $rumah) use ($bulan): Stat {
            $dibayar = $rumah->pembayaranIuran->first()?->nominal;
            $status = StatusIuran::tentukan($rumah->aktif, $rumah->iuran_bulanan, $dibayar);

            return Stat::make("{$rumah->nama} · iuran {$bulan->translatedFormat('F Y')}", $status->getLabel())
                ->description($this->petugas($rumah).($dibayar ? ' · dibayar '.RingkasanIuran::rupiah($dibayar) : ''))
                ->descriptionIcon(Heroicon::Truck)
                ->color($status->getColor());
        })->all();
    }

    /**
     * One card per house, so the count varies: as many columns as cards up to four (Filament's
     * default puts one or two cards in a three-column row and leaves the rest empty), then rows
     * of four or three.
     *
     * @return array<string, int>
     */
    protected function getColumns(): array
    {
        $jumlah = count($this->getCachedStats());

        $kolom = match (true) {
            $jumlah <= 4 => max($jumlah, 1),
            $jumlah % 4 === 0 => 4,
            default => 3,
        };

        return ['@xl' => $kolom, '!@lg' => $kolom];
    }

    private function petugas(Pelanggan $rumah): string
    {
        if (! $rumah->punyaPetugasAktif()) {
            return 'Petugas belum ditetapkan';
        }

        return 'Petugas: '.$rumah->petugas->name.(filled($rumah->petugas->no_hp) ? " ({$rumah->petugas->no_hp})" : '');
    }
}
