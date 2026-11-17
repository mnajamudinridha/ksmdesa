<?php

namespace App\Filament\Shared;

use Filament\Forms\Components\FileUpload;

/**
 * The KSM logo upload, shared by the master KSM form and the village "Profil KSM" page.
 * Public disk (it is shown on the login page); random stored names; images only.
 * storage/app/public/.htaccess refuses to execute scripts regardless.
 */
final class LogoKsmField
{
    public static function make(): FileUpload
    {
        return FileUpload::make('logo')
            ->label('Logo desa')
            ->image()
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
            ->maxSize(1024)
            ->disk('public')
            ->directory('logo-ksm')
            ->visibility('public')
            ->preventFilePathTampering()
            ->helperText('PNG, JPG atau WebP, maks. 1 MB. Tampil di halaman login dan di aplikasi desa.');
    }
}
