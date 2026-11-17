<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * "Today" for the villages: the calendar day in APP_ZONA_WAKTU (WITA by default), not in UTC.
 * At 07:00 WITA it is still yesterday in UTC, so a pickup recorded in the morning would land
 * on the wrong day if the app's UTC clock decided it.
 *
 * Compare date-only columns with these as Y-m-d strings: a `date` cast is midnight UTC and
 * this is midnight WITA, so comparing them as instants is off by eight hours.
 */
final class Hari
{
    public static function ini(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('app.zona_waktu_lokal'))->startOfDay();
    }

    /** Y-m-d of today minus $mundur days, in village time. */
    public static function tanggal(int $mundur = 0): string
    {
        return self::ini()->subDays($mundur)->toDateString();
    }
}
