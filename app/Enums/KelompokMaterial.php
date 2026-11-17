<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Group of a material kind — the composition charts and reports are per group. */
enum KelompokMaterial: string implements HasColor, HasLabel
{
    case Plastik = 'plastik';
    case Kertas = 'kertas';
    case Logam = 'logam';
    case Kaca = 'kaca';
    case Organik = 'organik';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Plastik => 'Plastik',
            self::Kertas => 'Kertas & kardus',
            self::Logam => 'Logam',
            self::Kaca => 'Kaca',
            self::Organik => 'Organik',
            self::Lainnya => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Plastik => 'info',
            self::Kertas => 'warning',
            self::Logam => 'gray',
            self::Kaca => 'info',
            self::Organik => 'success',
            self::Lainnya => 'gray',
        };
    }

    /** Chart colour (hex) for this group. */
    public function warnaGrafik(): string
    {
        return match ($this) {
            self::Plastik => '#2563eb',
            self::Kertas => '#d97706',
            self::Logam => '#6b7280',
            self::Kaca => '#0891b2',
            self::Organik => '#16a34a',
            self::Lainnya => '#a855f7',
        };
    }
}
