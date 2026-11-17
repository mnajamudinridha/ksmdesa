<?php

namespace App\Filament\Resources\Ksms\RelationManagers;

use App\Enums\PeranPengguna;
use App\Filament\AvatarProviders\InisialAvatar;
use App\Filament\Shared\AlihAktifAction;
use App\Filament\Shared\PenggunaForm;
use App\Models\Ksm;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Master manages any KSM's admins and petugas here; warga accounts come from customers in
 * the KSM panel (menu Pelanggan). Users belong to exactly one KSM, so there is no
 * attach/detach, and they are deactivated rather than deleted.
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Pengguna';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(PenggunaForm::komponen(fn (): Ksm => $this->getOwnerRecord()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->whereIn('peran', PeranPengguna::staf()))
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('avatar')
                    ->label('')
                    ->disk('local')
                    ->visibility('private')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => InisialAvatar::untukNama($record->name)),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('no_hp')
                    ->label('Nomor HP')
                    ->searchable(),
                TextColumn::make('peran')
                    ->badge(),
                IconColumn::make('aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('peran')
                    ->options(collect(PeranPengguna::staf())->mapWithKeys(fn (PeranPengguna $peran): array => [$peran->value => $peran->getLabel()])->all()),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah pengguna'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    AlihAktifAction::make(),
                ]),
            ]);
    }
}
