<?php

namespace App\Filament\Ksm\Resources\JenisMaterials\Pages;

use App\Filament\Ksm\Resources\JenisMaterials\JenisMaterialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageJenisMaterials extends ManageRecords
{
    protected static string $resource = JenisMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
