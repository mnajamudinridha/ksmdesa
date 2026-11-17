<?php

namespace App\Filament\Resources\Ksms\Tables;

use App\Models\Ksm;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KsmsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['pelanggan', 'users']))
            ->defaultSort('nama')
            ->columns([
                TextColumn::make('nama')
                    ->label('KSM')
                    ->description(fn (Ksm $record): string => 'Desa '.$record->desa)
                    ->searchable(['nama', 'desa'])
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Alamat')
                    ->formatStateUsing(fn (string $state): string => $state.'.'.config('app.domain'))
                    ->url(fn (Ksm $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('pelanggan_count')
                    ->label('Pelanggan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Pengurus')
                    ->numeric(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
