<?php

namespace App\Filament\Ksm\Resources\TarifLayanans;

use App\Filament\Ksm\Resources\TarifLayanans\Pages\ManageTarifLayanans;
use App\Models\TarifLayanan;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class TarifLayananResource extends Resource
{
    protected static ?string $model = TarifLayanan::class;

    protected static ?string $tenantRelationshipName = 'tarifLayanan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $modelLabel = 'tarif layanan';

    protected static ?string $pluralModelLabel = 'tarif layanan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama')
                    ->placeholder('Warga biasa')
                    ->required()
                    ->maxLength(255)
                    ->scopedUnique(ignoreRecord: true),
                TextInput::make('nominal')
                    ->label('Iuran per bulan')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->modifyQueryUsing(fn ($query) => $query->withCount('pelanggan'))
            ->defaultSort('nominal')
            ->columns([
                TextColumn::make('nama')
                    ->searchable(),
                TextColumn::make('nominal')
                    ->label('Iuran per bulan')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('pelanggan_count')
                    ->label('Pelanggan')
                    ->numeric(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTarifLayanans::route('/'),
        ];
    }
}
