<?php

namespace App\Filament\Resources\Ksms\RelationManagers;

use App\Enums\PeranPengguna;
use App\Filament\AvatarProviders\InisialAvatar;
use App\Filament\Shared\AlihAktifAction;
use App\Filament\Shared\PenggunaForm;
use App\Models\Ksm;
use App\Models\User;
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
 * Master manages any KSM's users here (admin, petugas, warga). Users belong to exactly
 * one KSM, so there is no attach/detach, and they are deactivated rather than deleted.
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
                    ->options(PeranPengguna::class),
            ])
            ->headerActions([
                CreateAction::make()->label('Tambah pengguna'),
            ])
            ->recordActions([
                EditAction::make(),
                AlihAktifAction::make(),
            ]);
    }
}
