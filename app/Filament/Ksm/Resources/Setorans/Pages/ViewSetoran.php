<?php

namespace App\Filament\Ksm\Resources\Setorans\Pages;

use App\Filament\Ksm\Resources\Setorans\SetoranResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSetoran extends ViewRecord
{
    protected static string $resource = SetoranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SetoranResource::aksiBatal(DeleteAction::make()),
        ];
    }
}
