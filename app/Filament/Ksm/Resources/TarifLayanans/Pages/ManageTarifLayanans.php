<?php

namespace App\Filament\Ksm\Resources\TarifLayanans\Pages;

use App\Filament\Ksm\Resources\TarifLayanans\TarifLayananResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTarifLayanans extends ManageRecords
{
    protected static string $resource = TarifLayananResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
