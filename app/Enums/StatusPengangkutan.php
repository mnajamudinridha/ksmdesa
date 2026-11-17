<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * What happened at one house on one collection round. Only an actual collection has a weight.
 */
enum StatusPengangkutan: string implements HasColor, HasIcon, HasLabel
{
    case Diangkut = 'diangkut';
    case TidakAdaSampah = 'tidak_ada_sampah';
    case TidakBisaDiakses = 'tidak_bisa_diakses';

    public function getLabel(): string
    {
        return match ($this) {
            self::Diangkut => 'Diangkut',
            self::TidakAdaSampah => 'Tidak ada sampah',
            self::TidakBisaDiakses => 'Tidak bisa diakses',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Diangkut => 'success',
            self::TidakAdaSampah => 'gray',
            self::TidakBisaDiakses => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Diangkut => Heroicon::OutlinedCheckCircle,
            self::TidakAdaSampah => Heroicon::OutlinedMinusCircle,
            self::TidakBisaDiakses => Heroicon::OutlinedExclamationTriangle,
        };
    }

    public function butuhBerat(): bool
    {
        return $this === self::Diangkut;
    }
}
