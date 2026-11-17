<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How one customer stands for one month. Drives the colours in "Rekap Iuran"
 * and the "belum lunas" count on the dashboard.
 */
enum StatusIuran: string implements HasColor, HasLabel
{
    case Lunas = 'lunas';
    case Kurang = 'kurang';
    case Lebih = 'lebih';
    case Belum = 'belum';
    case Nonaktif = 'nonaktif';

    /**
     * @param  bool  $aktif  is the customer still subscribed?
     * @param  int  $iuranBulanan  agreed monthly fee for this customer (Rp)
     * @param  int|null  $dibayar  amount recorded for the month, null = nothing recorded
     */
    public static function tentukan(bool $aktif, int $iuranBulanan, ?int $dibayar): self
    {
        // TODO(pemilik KSM): this is a provisional policy — decide the real rules.
        // Cases from a real ledger to think about:
        //   - a customer paid Rp15.000 against a Rp25.000 fee: "Kurang", or fine?
        //   - an inactive customer who still pays: count it as "Lunas" or keep "Nonaktif"?
        //   - paying more than the fee (e.g. two months at once): "Lebih", or "Lunas"?
        if ($dibayar === null || $dibayar === 0) {
            return $aktif ? self::Belum : self::Nonaktif;
        }

        return match ($dibayar <=> $iuranBulanan) {
            -1 => self::Kurang,
            0 => self::Lunas,
            1 => self::Lebih,
        };
    }

    /** Statuses that still need follow-up from the collector. */
    public function perluDitagih(): bool
    {
        return in_array($this, [self::Belum, self::Kurang], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Lunas => 'Lunas',
            self::Kurang => 'Kurang bayar',
            self::Lebih => 'Lebih bayar',
            self::Belum => 'Belum bayar',
            self::Nonaktif => 'Nonaktif',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Lunas => 'success',
            self::Kurang => 'warning',
            self::Lebih => 'info',
            self::Belum => 'danger',
            self::Nonaktif => 'gray',
        };
    }
}
