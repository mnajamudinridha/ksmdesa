<?php

namespace App\Filament\Ksm\Resources\Mitras\Pages;

use App\Filament\Ksm\Resources\Mitras\MitraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMitras extends ManageRecords
{
    protected static string $resource = MitraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
