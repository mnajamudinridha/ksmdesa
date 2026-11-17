<?php

namespace App\Filament\Ksm\Resources\PembayaranIurans\Pages;

use App\Filament\Ksm\Resources\PembayaranIurans\PembayaranIuranResource;
use App\Filament\Shared\TerimaIuranAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePembayaranIurans extends ManageRecords
{
    protected static string $resource = PembayaranIuranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Petugas: record cash taken at one of their houses (same form as in "Tugas hari ini").
            TerimaIuranAction::denganPilihanPelanggan(),
            CreateAction::make(),
        ];
    }
}
