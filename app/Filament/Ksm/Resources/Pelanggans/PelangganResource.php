<?php

namespace App\Filament\Ksm\Resources\Pelanggans;

use App\Filament\Ksm\Resources\Pelanggans\Pages\ManagePelanggans;
use App\Models\Pelanggan;
use App\Models\TarifLayanan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class PelangganResource extends Resource
{
    protected static ?string $model = Pelanggan::class;

    protected static ?string $tenantRelationshipName = 'pelanggan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $modelLabel = 'pelanggan';

    protected static ?string $pluralModelLabel = 'pelanggan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('tarif_layanan_id')
                    ->label('Tarif')
                    ->relationship('tarifLayanan', 'nama')
                    ->getOptionLabelFromRecordUsing(fn (TarifLayanan $record): string => $record->nama.' — Rp'.number_format($record->nominal, 0, ',', '.'))
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($tarif = TarifLayanan::find($state)) {
                            $set('iuran_bulanan', $tarif->nominal);
                        }
                    }),
                TextInput::make('iuran_bulanan')
                    ->label('Iuran per bulan')
                    ->helperText('Terisi dari tarif; ubah bila pelanggan ini punya kesepakatan lain.')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('nomor_urut')
                    ->label('No. di buku')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('rt')
                    ->label('RT')
                    ->maxLength(20),
                Toggle::make('aktif')
                    ->default(true)
                    ->helperText('Matikan bila pelanggan berhenti berlangganan.'),
                Textarea::make('catatan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama')
            ->defaultSort('nomor_urut')
            ->columns([
                TextColumn::make('nomor_urut')
                    ->label('No.')
                    ->sortable(),
                TextColumn::make('nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tarifLayanan.nama')
                    ->label('Tarif')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('iuran_bulanan')
                    ->label('Iuran/bulan')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('rt')
                    ->label('RT')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('aktif')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('aktif')
                    ->default(true),
                SelectFilter::make('tarif_layanan_id')
                    ->label('Tarif')
                    ->relationship('tarifLayanan', 'nama'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePelanggans::route('/'),
        ];
    }
}
