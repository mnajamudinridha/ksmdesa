<?php

namespace App\Filament\Ksm\Resources\TransaksiKas\Pages;

use App\Filament\Ksm\Resources\TransaksiKas\TransaksiKasResource;
use App\Models\TransaksiKas;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageTransaksiKas extends ManageRecords
{
    protected static string $resource = TransaksiKasResource::class;

    protected static ?string $title = 'Buku Kas';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Who recorded it is never taken from input (not fillable).
                ->using(function (array $data): TransaksiKas {
                    $transaksi = new TransaksiKas($data);
                    $transaksi->forceFill(['dicatat_oleh_id' => auth()->id()]);
                    Filament::getTenant()->transaksiKas()->save($transaksi);

                    return $transaksi;
                }),
        ];
    }
}
