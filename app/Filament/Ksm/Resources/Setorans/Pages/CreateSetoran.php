<?php

namespace App\Filament\Ksm\Resources\Setorans\Pages;

use App\Filament\Ksm\Resources\Setorans\SetoranResource;
use App\Models\Setoran;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSetoran extends CreateRecord
{
    protected static string $resource = SetoranResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * The deposit and its payments in one transaction. The update re-checks every ticked
     * payment (this KSM, this petugas, still undeposited); if any was deposited meanwhile
     * the counts differ and nothing is saved.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $ids = array_map('intval', (array) Arr::pull($data, 'pembayaran', []));

        return DB::transaction(function () use ($data, $ids): Setoran {
            $setoran = new Setoran($data);
            $setoran->forceFill(['diterima_oleh_id' => auth()->id()]);
            $setoran->ksm()->associate(Filament::getTenant());
            $setoran->save();

            $terhubung = SetoranResource::belumDisetor($setoran->petugas_id)
                ->whereKey($ids)
                ->update(['setoran_id' => $setoran->getKey()]);

            if ($terhubung !== count($ids)) {
                throw ValidationException::withMessages([
                    'data.pembayaran' => 'Sebagian pembayaran sudah disetor di setoran lain. Muat ulang halaman ini.',
                ]);
            }

            return $setoran;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
