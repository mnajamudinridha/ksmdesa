<?php

namespace App\Filament\Ksm\Resources\CatatanMaterials;

use App\Enums\AktivitasMaterial;
use App\Filament\Ksm\Resources\CatatanMaterials\Pages\ManageCatatanMaterials;
use App\Filament\Shared\PilihanMaster;
use App\Models\CatatanMaterial;
use App\Support\Hari;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The KSM's material log: sorting, sales to buyers, processing. A sale's value is cash income
 * on its date (counted by RingkasanKas from this record — never re-entered in the cash book).
 */
class CatatanMaterialResource extends Resource
{
    protected static ?string $model = CatatanMaterial::class;

    protected static ?string $tenantRelationshipName = 'catatanMaterial';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Data Input';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'catatan material';

    protected static ?string $pluralModelLabel = 'catatan material';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->isAdminKsm()
            ? $query->with(['jenisMaterial', 'pengepul'])
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        $jual = fn (Get $get): bool => AktivitasMaterial::dari($get('aktivitas'))?->butuhPembeli() ?? false;

        return $schema
            ->columns(2)
            ->components([
                ToggleButtons::make('aktivitas')
                    ->options(AktivitasMaterial::class)
                    ->inline()
                    ->required()
                    ->default(AktivitasMaterial::Pilah->value)
                    ->live()
                    ->columnSpanFull(),
                DatePicker::make('tanggal')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->default(fn (): string => Hari::tanggal())
                    ->maxDate(fn (): string => Hari::tanggal())
                    ->required(),
                Select::make('jenis_material_id')
                    ->label('Jenis material')
                    // Options = this KSM's active kinds (+ the saved one): the "in" rule rejects any other.
                    ->options(fn (?CatatanMaterial $record): array => PilihanMaster::jenisMaterial($record?->jenis_material_id))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText(fn (): ?string => PilihanMaster::jenisMaterial() === [] ? 'Belum ada jenis material — tambahkan di Data Master → Jenis Material.' : null),
                TextInput::make('berat_kg')
                    ->label('Berat')
                    ->numeric()
                    ->step(0.1)
                    ->minValue(0.1)
                    ->maxValue(100_000)
                    ->suffix('kg')
                    ->required(),
                Select::make('mitra_id')
                    ->label('Pengepul')
                    // Only active partners of kind "pengepul" (+ the saved one) of this KSM.
                    ->options(fn (?CatatanMaterial $record): array => PilihanMaster::pengepul($record?->mitra_id))
                    ->required($jual)
                    ->visible($jual)
                    ->helperText(fn (): ?string => PilihanMaster::pengepul() === [] ? 'Tambahkan mitra berjenis Pengepul di Data Master → Mitra.' : null),
                TextInput::make('nilai_jual')
                    ->label('Nilai penjualan')
                    ->numeric()
                    ->integer()
                    ->prefix('Rp')
                    ->minValue(0)
                    ->maxValue(1_000_000_000)
                    ->required($jual)
                    ->visible($jual)
                    ->helperText('Masuk buku kas otomatis sebagai "Penjualan material" — jangan dicatat ulang di Buku Kas.'),
                TextInput::make('hasil_olah')
                    ->label('Hasil olah')
                    ->placeholder('mis. kompos 12 kg')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => AktivitasMaterial::dari($get('aktivitas'))?->butuhHasilOlah() ?? false),
                Textarea::make('catatan')
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->emptyStateHeading('Belum ada catatan material')
            ->emptyStateDescription('Catat material yang dipilah, dijual ke pengepul, atau diolah (mis. kompos).')
            ->columns([
                TextColumn::make('tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('aktivitas')
                    ->badge(),
                TextColumn::make('jenisMaterial.nama')
                    ->label('Jenis')
                    ->description(fn (CatatanMaterial $record): ?string => $record->jenisMaterial?->kelompok->getLabel())
                    ->placeholder('Tanpa jenis'),
                TextColumn::make('berat_kg')
                    ->label('Berat')
                    ->numeric(decimalPlaces: 1, locale: 'id')
                    ->suffix(' kg')
                    ->sortable(),
                TextColumn::make('pengepul.nama')
                    ->label('Pengepul')
                    ->placeholder('—'),
                TextColumn::make('nilai_jual')
                    ->label('Nilai jual')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->placeholder('—')
                    ->summarize(Sum::make()->money('IDR', locale: 'id', decimalPlaces: 0)),
                TextColumn::make('hasil_olah')
                    ->label('Hasil olah')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('aktivitas')
                    ->options(AktivitasMaterial::class),
                // Explicit options: ->relationship() would load every KSM's kinds / partners.
                SelectFilter::make('jenis_material_id')
                    ->label('Jenis material')
                    ->options(fn (): array => PilihanMaster::semuaJenisMaterial()),
                SelectFilter::make('mitra_id')
                    ->label('Pengepul')
                    // Also past buyers (deactivated or kind changed), so their sales stay findable.
                    ->options(fn (): array => PilihanMaster::semuaPengepul()),
                Filter::make('tanggal')
                    ->form([
                        DatePicker::make('dari')
                            ->native(false),
                        DatePicker::make('sampai')
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $dari) => $q->whereDate('tanggal', '>=', $dari))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $sampai) => $q->whereDate('tanggal', '<=', $sampai))),
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
            'index' => ManageCatatanMaterials::route('/'),
        ];
    }
}
