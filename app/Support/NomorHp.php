<?php

namespace App\Support;

/**
 * The single normalisation rule for Indonesian mobile numbers, applied before
 * validation, uniqueness checks, storage and login alike: "+62 812-3456-7890",
 * "62812…" and "0812…" are all stored and matched as "081234567890".
 */
final class NomorHp
{
    public static function normalisasi(?string $nomor): ?string
    {
        $digit = preg_replace('/\D+/', '', (string) $nomor);

        if ($digit === '' || $digit === null) {
            return null;
        }

        return match (true) {
            str_starts_with($digit, '62') => '0'.substr($digit, 2),
            str_starts_with($digit, '8') => '0'.$digit,
            default => $digit,
        };
    }

    /** 08 followed by 8–11 digits (10–13 digits in total). Expects a normalised number. */
    public static function valid(?string $normal): bool
    {
        return $normal !== null && preg_match('/^08\d{8,11}$/', $normal) === 1;
    }
}
