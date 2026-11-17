<?php

namespace App\Filament\Resources\Ksms\Pages;

use App\Filament\Resources\Ksms\KsmResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKsm extends EditRecord
{
    protected static string $resource = KsmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
