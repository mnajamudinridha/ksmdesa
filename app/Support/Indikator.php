<?php

namespace App\Support;

/**
 * Colour of a ratio indicator (0.73 = 73 %) on the dashboards: green from $batasHijau,
 * yellow from 50 %, red below, grey when there is nothing to measure yet.
 */
final class Indikator
{
    public static function warna(?float $nilai, float $batasHijau = 0.8): string
    {
        return match (true) {
            $nilai === null => 'gray',
            $nilai >= $batasHijau => 'success',
            $nilai >= 0.5 => 'warning',
            default => 'danger',
        };
    }
}
