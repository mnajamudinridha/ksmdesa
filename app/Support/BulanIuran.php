<?php

namespace App\Support;

use App\Models\Pelanggan;
use Carbon\CarbonImmutable;

/**
 * The months a petugas may record a fee for: 6 months back to 2 ahead (village time),
 * minus the months already recorded for this customer. Used as CheckboxList options, so
 * Filament's "in" rule rejects anything else server-side; the (pelanggan_id, periode)
 * unique index is the last guard against two people recording at once.
 */
final class BulanIuran
{
    public const MUNDUR = 6;

    public const MAJU = 2;

    /** @return array<string, string> Y-m-d (first of month) => "Agustus 2026", oldest first */
    public static function pilihan(Pelanggan $pelanggan): array
    {
        $awal = Hari::ini()->startOfMonth()->subMonths(self::MUNDUR);

        $sudah = $pelanggan->pembayaranIuran()
            ->whereDate('periode', '>=', $awal->toDateString())
            ->pluck('periode')
            ->map(fn ($periode): string => $periode->toDateString())
            ->all();

        $pilihan = [];

        for ($i = 0; $i <= self::MUNDUR + self::MAJU; $i++) {
            $bulan = $awal->addMonths($i);

            if (! in_array($bulan->toDateString(), $sudah, true)) {
                $pilihan[$bulan->toDateString()] = $bulan->translatedFormat('F Y');
            }
        }

        return $pilihan;
    }

    public static function iniBulan(): CarbonImmutable
    {
        return Hari::ini()->startOfMonth();
    }
}
