<?php

namespace App\Enums;

use App\Support\RingkasanIuran;
use App\Support\RingkasanPengangkutan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/**
 * How a report value is shown on screen and written to Excel — one place for both, so the
 * printed page and the spreadsheet can never format the same number differently.
 */
enum FormatAngka: string
{
    case Teks = 'teks';
    case Angka = 'angka';
    case Rupiah = 'rupiah';
    case Kg = 'kg';
    case Persen = 'persen';
    case Tanggal = 'tanggal';

    public function tampil(mixed $nilai): string
    {
        if ($nilai === null || $nilai === '') {
            return '—';
        }

        return match ($this) {
            self::Teks => (string) $nilai,
            self::Angka => (string) Number::format((float) $nilai, maxPrecision: 1, locale: 'id'),
            self::Rupiah => RingkasanIuran::rupiah((int) $nilai),
            self::Kg => RingkasanPengangkutan::kg((float) $nilai),
            self::Persen => (string) Number::percentage((float) $nilai * 100, precision: 0, locale: 'id'),
            // A label such as "Jumlah" in a total row stays as it is.
            self::Tanggal => self::adalahTanggal($nilai) ? Carbon::parse((string) $nilai)->translatedFormat('d M Y') : (string) $nilai,
        };
    }

    public static function adalahTanggal(mixed $nilai): bool
    {
        return is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}/', $nilai) === 1;
    }

    public function rataKanan(): bool
    {
        return ! in_array($this, [self::Teks, self::Tanggal], true);
    }

    /** Excel number format; null = General. Values are written as numbers (percent as a fraction). */
    public function formatExcel(): ?string
    {
        return match ($this) {
            self::Rupiah => '#,##0',
            self::Kg => '#,##0.0',
            self::Persen => '0%',
            self::Tanggal => 'dd/mm/yyyy',
            default => null,
        };
    }
}
