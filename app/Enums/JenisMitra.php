<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/** Kind of partner. Pengepul (buyers of sorted material) are picked on material sales (5C). */
enum JenisMitra: string implements HasColor, HasIcon, HasLabel
{
    case Pemerintah = 'pemerintah';
    case Swasta = 'swasta';
    case Pengepul = 'pengepul';
    case Komunitas = 'komunitas';
    case Lainnya = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pemerintah => 'Pemerintah',
            self::Swasta => 'Swasta / CSR',
            self::Pengepul => 'Pengepul / bank sampah',
            self::Komunitas => 'Komunitas / LSM / sekolah',
            self::Lainnya => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pemerintah => 'info',
            self::Swasta => 'primary',
            self::Pengepul => 'warning',
            self::Komunitas => 'success',
            self::Lainnya => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Pemerintah => Heroicon::OutlinedBuildingLibrary,
            self::Swasta => Heroicon::OutlinedBuildingOffice2,
            self::Pengepul => Heroicon::OutlinedArrowPathRoundedSquare,
            self::Komunitas => Heroicon::OutlinedUserGroup,
            self::Lainnya => Heroicon::OutlinedEllipsisHorizontalCircle,
        };
    }
}
