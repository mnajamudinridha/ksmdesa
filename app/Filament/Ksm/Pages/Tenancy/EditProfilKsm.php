<?php

namespace App\Filament\Ksm\Pages\Tenancy;

use App\Filament\Shared\LatarLoginField;
use App\Filament\Shared\LogoKsmField;
use App\Filament\Shared\SandiDefaultField;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * Village admins edit their KSM's name and logo here. The subdomain (slug) is
 * deliberately absent: changing it moves the whole panel, so only master may.
 */
class EditProfilKsm extends EditTenantProfile
{
    // Both profile pages default to "profile"; the user profile route is registered first and
    // without a domain, so it would shadow the KSM profile on every subdomain.
    protected static ?string $slug = 'profil-ksm';

    public static function getLabel(): string
    {
        return 'Profil KSM';
    }

    public static function canView(Model $tenant): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identitas KSM')
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nama KSM')
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make('Logo & halaman login')
                    ->description('Logo tampil di halaman login dan di samping nama KSM di aplikasi; latar menghiasi halaman login.')
                    ->schema([
                        LogoKsmField::make(),
                        LatarLoginField::make(),
                    ]),
                Section::make('Sandi default warga')
                    ->description('Akun warga dibuat otomatis dari data pelanggan dan login dengan sandi ini sampai warga menggantinya sendiri.')
                    ->schema([
                        SandiDefaultField::make(),
                    ]),
            ]);
    }
}
