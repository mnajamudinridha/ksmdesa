<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Cash in or out. Only income may name the partner it came from. */
enum JenisTransaksi: string implements HasColor, HasIcon, HasLabel
{
    case Masuk = 'masuk';
    case Keluar = 'keluar';

    /**
     * From form state: an enum instance (hydrated from a cast model) or a string (from the
     * client). Never cast the state to string yourself — a backed enum cannot be.
     */
    public static function dari(mixed $nilai): ?self
    {
        return $nilai instanceof self ? $nilai : self::tryFrom(is_scalar($nilai) ? (string) $nilai : '');
    }

    public function bolehMitra(): bool
    {
        return $this === self::Masuk;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Masuk => 'Pemasukan',
            self::Keluar => 'Pengeluaran',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Masuk => 'success',
            self::Keluar => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Masuk => Heroicon::OutlinedArrowDownCircle,
            self::Keluar => Heroicon::OutlinedArrowUpCircle,
        };
    }
}
