<?php

namespace App\Filament\Ksm\Resources\Pengangkutans\Pages;

use App\Filament\Ksm\Resources\Pengangkutans\PengangkutanResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePengangkutan extends CreateRecord
{
    protected static string $resource = PengangkutanResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * A petugas never sees the petugas field; whatever arrives, the round is theirs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->isAdminKsm()) {
            $data['petugas_id'] = auth()->id();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
