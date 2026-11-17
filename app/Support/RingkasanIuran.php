<?php

namespace App\Support;

use App\Enums\StatusIuran;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use Illuminate\Support\Carbon;

/**
 * Fee figures for one KSM, shared by the dashboard widgets.
 */
final class RingkasanIuran
{
    public function __construct(private readonly Ksm $ksm) {}

    /** Most recent month that has any payment recorded; null when nothing is recorded yet. */
    public function bulanTerakhir(): ?Carbon
    {
        $periode = PembayaranIuran::query()->whereBelongsTo($this->ksm)->max('periode');

        return $periode ? Carbon::parse($periode)->startOfMonth() : null;
    }

    public function terkumpul(Carbon $bulan): int
    {
        return (int) PembayaranIuran::query()
            ->whereBelongsTo($this->ksm)
            ->whereDate('periode', $bulan->copy()->startOfMonth()->toDateString())
            ->sum('nominal');
    }

    /** What the month would bring in if every active customer paid their agreed fee. */
    public function target(): int
    {
        return (int) Pelanggan::query()->whereBelongsTo($this->ksm)->where('aktif', true)->sum('iuran_bulanan');
    }

    public function jumlahPelangganAktif(): int
    {
        return Pelanggan::query()->whereBelongsTo($this->ksm)->where('aktif', true)->count();
    }

    public function jumlahPelanggan(): int
    {
        return Pelanggan::query()->whereBelongsTo($this->ksm)->count();
    }

    /** Active customers who still owe something for the month (unpaid or underpaid). */
    public function jumlahPerluDitagih(Carbon $bulan): int
    {
        $dibayar = PembayaranIuran::query()
            ->whereBelongsTo($this->ksm)
            ->whereDate('periode', $bulan->copy()->startOfMonth()->toDateString())
            ->pluck('nominal', 'pelanggan_id');

        return Pelanggan::query()
            ->whereBelongsTo($this->ksm)
            ->where('aktif', true)
            ->get(['id', 'aktif', 'iuran_bulanan'])
            ->filter(fn (Pelanggan $p): bool => StatusIuran::tentukan($p->aktif, $p->iuran_bulanan, $dibayar[$p->id] ?? null)->perluDitagih())
            ->count();
    }

    /**
     * Monthly totals for the 12 months ending at $sampai.
     *
     * @return array<string, int> 'Y-m' => total, oldest first
     */
    public function perBulan(Carbon $sampai): array
    {
        $awal = $sampai->copy()->startOfMonth()->subMonths(11);

        $total = PembayaranIuran::query()
            ->whereBelongsTo($this->ksm)
            ->whereBetween('periode', [$awal->toDateString(), $sampai->copy()->endOfMonth()->toDateString()])
            ->get(['periode', 'nominal'])
            ->groupBy(fn (PembayaranIuran $p): string => $p->periode->format('Y-m'))
            ->map(fn ($baris): int => (int) $baris->sum('nominal'));

        $hasil = [];
        for ($b = $awal->copy(); $b->lessThanOrEqualTo($sampai); $b->addMonth()) {
            $hasil[$b->format('Y-m')] = $total[$b->format('Y-m')] ?? 0;
        }

        return $hasil;
    }

    public static function rupiah(int $nominal): string
    {
        return 'Rp'.number_format($nominal, 0, ',', '.');
    }
}
