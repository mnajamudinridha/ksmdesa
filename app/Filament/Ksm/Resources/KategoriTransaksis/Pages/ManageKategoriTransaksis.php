<?php

namespace App\Filament\Ksm\Resources\KategoriTransaksis\Pages;

use App\Filament\Ksm\Resources\KategoriTransaksis\KategoriTransaksiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKategoriTransaksis extends ManageRecords
{
    protected static string $resource = KategoriTransaksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
