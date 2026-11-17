<?php

namespace App\Filament\Shared;

use Closure;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * "Profil saya" for every signed-in user (both panels): change your own photo and password.
 * Name, phone number, email and role stay under the admin's control, so they are shown
 * read-only and never saved from here.
 */
class ProfilSaya extends EditProfile
{
    // Both profile pages default to "profile"; the user profile route is registered first and
    // without a domain, so it would shadow the KSM profile on every subdomain.
    protected static ?string $slug = 'profil-saya';

    public static function getLabel(): string
    {
        return 'Profil saya';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                AvatarField::make(),
                TextInput::make('name')
                    ->label('Nama')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('no_hp')
                    ->label('Nomor HP')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (): bool => filled($this->getUser()->getAttributeValue('no_hp'))),
                TextInput::make('email')
                    ->disabled()
                    ->dehydrated(false)
                    ->visible(fn (): bool => filled($this->getUser()->getAttributeValue('email'))),
                $this->getPasswordFormComponent()
                    ->label('Kata sandi baru')
                    ->helperText('Kosongkan bila tidak diganti.')
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $user = $this->getUser();

                        if (! $user->isWarga() && $user->ksm?->cocokSandiDefault((string) $value)) {
                            $fail(PenggunaForm::PESAN_SANDI_DEFAULT);
                        }
                    }),
                $this->getPasswordConfirmationFormComponent(),
                // Base version also asks for it when the email changes; email is read-only here,
                // so only a password change needs the current password.
                TextInput::make('currentPassword')
                    ->label('Kata sandi saat ini')
                    ->password()
                    ->revealable()
                    ->autocomplete('current-password')
                    ->currentPassword(guard: Filament::getAuthGuard())
                    ->required()
                    ->visible(fn (Get $get): bool => filled($get('password')))
                    ->dehydrated(false),
            ]);
    }
}
