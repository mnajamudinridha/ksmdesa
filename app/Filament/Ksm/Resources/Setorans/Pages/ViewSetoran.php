<?php

namespace App\Filament\Ksm\Resources\Setorans\Pages;

use App\Filament\Ksm\Resources\Setorans\SetoranResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewSetoran extends ViewRecord
{
    protected static string $resource = SetoranResource::class;

    /** The payment list shows each customer's name: load them in one query. */
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['petugas', 'penerima', 'pembayaranIuran.pelanggan']);
    }

    protected function getHeaderActions(): array
    {
        return [
            SetoranResource::aksiBatal(DeleteAction::make()),
        ];
    }
}
