<?php

namespace App\Filament\Ksm\Resources\PembayaranIurans;

use App\Filament\Ksm\Resources\PembayaranIurans\Pages\ManagePembayaranIurans;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use UnitEnum;

class PembayaranIuranResource extends Resource
{
    protected static ?string $model = PembayaranIuran::class;

    protected static ?string $tenantRelationshipName = 'pembayaranIuran';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Data Input';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'pembayaran iuran';

    protected static ?string $pluralModelLabel = 'pembayaran iuran';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('pelanggan_id')
                    ->label('Pelanggan')
                    ->relationship('pelanggan', 'nama', fn ($query) => $query->orderBy('nomor_urut')->orderBy('nama'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($pelanggan = Pelanggan::find($state)) {
                            $set('nominal', $pelanggan->iuran_bulanan);
                        }
                    })
                    ->columnSpanFull(),
                DatePicker::make('periode')
                    ->label('Untuk bulan')
                    ->native(false)
                    ->displayFormat('F Y')
                    ->default(now()->startOfMonth())
                    ->helperText('Tanggal mana pun dihitung sebagai bulan itu.')
                    ->required()
                    ->rule(fn (Get $get, ?PembayaranIuran $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                        $sudahAda = PembayaranIuran::query()
                            ->where('pelanggan_id', $get('pelanggan_id'))
                            ->whereDate('periode', Carbon::parse($value)->startOfMonth())
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($sudahAda) {
                            $fail('Pembayaran pelanggan ini untuk bulan tersebut sudah dicatat — ubah catatan yang ada.');
                        }
                    }),
                TextInput::make('nominal')
                    ->prefix('Rp')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                DatePicker::make('dibayar_pada')
                    ->label('Tanggal diterima')
                    ->native(false)
                    ->default(now()),
                TextInput::make('catatan')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('periode', 'desc')
            ->columns([
                TextColumn::make('periode')
                    ->label('Bulan')
                    ->date('F Y')
                    ->sortable(),
                TextColumn::make('pelanggan.nama')
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nominal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable()
                    ->summarize(Sum::make()->label('Total')->money('IDR', locale: 'id', decimalPlaces: 0)),
                TextColumn::make('dibayar_pada')
                    ->label('Diterima')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('catatan')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('periode')
                    ->label('Bulan')
                    ->options(fn (): array => self::opsiBulan()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * Months that have at least one payment in the current KSM, newest first.
     *
     * @return array<string, string>
     */
    public static function opsiBulan(): array
    {
        return PembayaranIuran::query()
            ->whereBelongsTo(Filament::getTenant())
            ->distinct()
            ->orderByDesc('periode')
            ->pluck('periode')
            ->mapWithKeys(fn (Carbon $periode): array => [$periode->toDateString() => $periode->translatedFormat('F Y')])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePembayaranIurans::route('/'),
        ];
    }
}
