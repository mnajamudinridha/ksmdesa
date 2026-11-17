<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * What happened to sorted material. Sorted + processed is what was kept out of the dump
 * ("terpulihkan"); a sale moves material that was already sorted, so it is not counted again.
 */
enum AktivitasMaterial: string implements HasColor, HasIcon, HasLabel
{
    case Pilah = 'pilah';
    case Jual = 'jual';
    case Olah = 'olah';

    /** From form state: an enum instance or a string (see JenisTransaksi::dari). */
    public static function dari(mixed $nilai): ?self
    {
        return $nilai instanceof self ? $nilai : self::tryFrom(is_scalar($nilai) ? (string) $nilai : '');
    }

    /** A sale names its buyer and its value. */
    public function butuhPembeli(): bool
    {
        return $this === self::Jual;
    }

    public function butuhHasilOlah(): bool
    {
        return $this === self::Olah;
    }

    public function terpulihkan(): bool
    {
        return $this === self::Pilah || $this === self::Olah;
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pilah => 'Dipilah',
            self::Jual => 'Dijual',
            self::Olah => 'Diolah',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pilah => 'info',
            self::Jual => 'success',
            self::Olah => 'warning',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pilah => Heroicon::OutlinedFunnel,
            self::Jual => Heroicon::OutlinedBanknotes,
            self::Olah => Heroicon::OutlinedBeaker,
        };
    }
}
