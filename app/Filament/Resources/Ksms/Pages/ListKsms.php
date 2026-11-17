<?php

namespace App\Filament\Resources\Ksms\Pages;

use App\Filament\Resources\Ksms\KsmResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKsms extends ListRecords
{
    protected static string $resource = KsmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
