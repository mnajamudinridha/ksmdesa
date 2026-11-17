<?php

namespace App\Filament\Shared;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Hash;

/**
 * The KSM's default password for warga accounts (Profil KSM and the master KSM form).
 * Write-only: never filled from the record (the hash is #[Hidden]), stored as a hash,
 * left unchanged when blank. Saving a new one also moves every account still on the
 * default to it (Ksm::updated).
 */
final class SandiDefaultField
{
    public static function make(): TextInput
    {
        return TextInput::make('sandi_default_hash')
            ->label('Kata sandi default warga')
            ->password()
            ->revealable()
            ->minLength(8)
            ->maxLength(255)
            ->autocomplete('new-password')
            ->formatStateUsing(fn (): ?string => null)
            ->dehydrated(fn (?string $state): bool => filled($state))
            ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
            ->helperText('Dipakai akun warga baru dan akun yang belum mengganti sandinya sendiri. Kosongkan bila tidak diganti.');
    }
}
