<?php

namespace App\Filament\Ksm\Resources\Setorans\Pages;

use App\Filament\Ksm\Resources\Setorans\SetoranResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSetorans extends ListRecords
{
    protected static string $resource = SetoranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Terima setoran'),
        ];
    }
}
