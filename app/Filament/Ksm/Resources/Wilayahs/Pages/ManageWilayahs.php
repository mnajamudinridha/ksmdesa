<?php

namespace App\Filament\Ksm\Resources\Wilayahs\Pages;

use App\Filament\Ksm\Resources\Wilayahs\WilayahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWilayahs extends ManageRecords
{
    protected static string $resource = WilayahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
