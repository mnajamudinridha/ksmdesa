<?php

namespace App\Filament\Ksm\Resources\Pengangkutans\Pages;

use App\Filament\Ksm\Resources\Pengangkutans\PengangkutanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPengangkutan extends EditRecord
{
    protected static string $resource = PengangkutanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
