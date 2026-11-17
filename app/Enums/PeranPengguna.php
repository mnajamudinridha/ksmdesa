<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * What a village (KSM) user may do inside their KSM's panel.
 * Master administrators are not a role: they have users.is_super_admin and no KSM.
 */
enum PeranPengguna: string implements HasColor, HasIcon, HasLabel
{
    case Admin = 'admin';
    case Petugas = 'petugas';
    case Warga = 'warga';

    /**
     * Roles managed in the Pengguna menus. Warga accounts come only from customers (Pelanggan).
     *
     * @return array<int, self>
     */
    public static function staf(): array
    {
        return [self::Admin, self::Petugas];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Petugas => 'Petugas pengangkut',
            self::Warga => 'Warga',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'primary',
            self::Petugas => 'warning',
            self::Warga => 'gray',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Admin => Heroicon::OutlinedShieldCheck,
            self::Petugas => Heroicon::OutlinedTruck,
            self::Warga => Heroicon::OutlinedHome,
        };
    }
}
