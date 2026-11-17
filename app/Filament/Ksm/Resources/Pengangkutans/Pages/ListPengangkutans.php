<?php

namespace App\Filament\Ksm\Resources\Pengangkutans\Pages;

use App\Filament\Ksm\Resources\Pengangkutans\PengangkutanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPengangkutans extends ListRecords
{
    protected static string $resource = PengangkutanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat pengangkutan'),
        ];
    }
}
