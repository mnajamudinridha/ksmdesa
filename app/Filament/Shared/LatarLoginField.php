<?php

namespace App\Filament\Shared;

use Filament\Forms\Components\FileUpload;

/**
 * Background photos of the KSM's login page, shared by the master KSM form and the village
 * "Profil KSM" page. Public disk like the logo (the login page is public); random stored
 * names; images only. One of them is picked at random on each visit.
 */
final class LatarLoginField
{
    public static function make(): FileUpload
    {
        return FileUpload::make('latar_login')
            ->label('Latar halaman login')
            ->multiple()
            ->maxFiles(3)
            ->reorderable()
            ->image()
            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
            ->maxSize(3072)
            ->disk('public')
            ->directory('latar-login')
            ->visibility('public')
            ->preventFilePathTampering()
            ->panelLayout('grid')
            ->helperText('Foto lanskap (mis. 1600×900), maks. 3 berkas @ 3 MB. Bila lebih dari satu, dipilih acak setiap kali halaman login dibuka.');
    }
}
