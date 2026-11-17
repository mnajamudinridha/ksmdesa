<?php

namespace App\Filament\Shared;

use Filament\Forms\Components\FileUpload;

/**
 * Account photo, shared by the admin user forms and "Profil saya". Residents' faces are
 * personal data: stored on the private "local" disk and only reachable through
 * short-lived signed URLs (Laravel serves the disk with signature checks).
 */
final class AvatarField
{
    public static function make(): FileUpload
    {
        return FileUpload::make('avatar')
            ->label('Foto')
            ->avatar()
            ->image()
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
            ->maxSize(2048)
            ->disk('local')
            ->directory('avatar')
            ->visibility('private')
            ->preventFilePathTampering();
    }
}
