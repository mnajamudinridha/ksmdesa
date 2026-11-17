<?php

namespace App\Filament\Ksm\Resources\Pelanggans\Pages;

use App\Filament\Ksm\Resources\Pelanggans\PelangganResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePelanggans extends ManageRecords
{
    protected static string $resource = PelangganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
