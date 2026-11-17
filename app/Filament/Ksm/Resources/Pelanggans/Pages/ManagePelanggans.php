<?php

namespace App\Filament\Ksm\Resources\Pelanggans\Pages;

use App\Filament\Ksm\Resources\Pelanggans\PelangganResource;
use App\Models\Pelanggan;
use App\Support\AkunWarga;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManagePelanggans extends ManageRecords
{
    protected static string $resource = PelangganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buatAkunWarga')
                ->label('Buat akun warga yang belum ada')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->authorize(fn (): bool => (bool) auth()->user()?->isAdminKsm())
                ->visible(fn (): bool => Pelanggan::query()->whereBelongsTo(Filament::getTenant())->whereNull('user_id')->exists())
                ->requiresConfirmation()
                ->modalDescription('Setiap pelanggan tanpa akun mendapat akun warga. Akun tanpa nomor HP baru bisa login setelah nomornya diisi.')
                ->action(function (): void {
                    $ksm = Filament::getTenant();
                    $jumlah = 0;

                    Pelanggan::query()->whereBelongsTo($ksm)->whereNull('user_id')->each(function (Pelanggan $pelanggan) use ($ksm, &$jumlah): void {
                        $pelanggan->akunWarga()->associate(AkunWarga::untukNomor($ksm, null, $pelanggan->nama));
                        $pelanggan->save();
                        $jumlah++;
                    });

                    Notification::make()->title("{$jumlah} akun warga dibuat.")->success()->send();
                }),
            CreateAction::make()
                ->using(fn (array $data): Pelanggan => PelangganResource::simpan(new Pelanggan, $data)),
        ];
    }
}
