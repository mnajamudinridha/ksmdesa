<?php

namespace App\Filament\Ksm\Resources\CatatanMaterials\Pages;

use App\Filament\Ksm\Resources\CatatanMaterials\CatatanMaterialResource;
use App\Models\CatatanMaterial;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ManageRecords;

class ManageCatatanMaterials extends ManageRecords
{
    protected static string $resource = CatatanMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                // Who recorded it is never taken from input (not fillable).
                ->using(function (array $data): CatatanMaterial {
                    $catatan = new CatatanMaterial($data);
                    $catatan->forceFill(['dicatat_oleh_id' => auth()->id()]);
                    Filament::getTenant()->catatanMaterial()->save($catatan);

                    return $catatan;
                }),
        ];
    }
}
