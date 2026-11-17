<?php

namespace App\Filament\Ksm\Resources\PembayaranIurans\Pages;

use App\Filament\Ksm\Resources\PembayaranIurans\PembayaranIuranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePembayaranIurans extends ManageRecords
{
    protected static string $resource = PembayaranIuranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
