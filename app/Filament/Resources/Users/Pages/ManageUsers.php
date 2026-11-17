<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah administrator')
                // is_super_admin is not fillable: set it here, server-side, never from form data.
                ->using(function (array $data): User {
                    $user = new User($data);
                    $user->forceFill(['is_super_admin' => true])->save();

                    return $user;
                }),
        ];
    }
}
