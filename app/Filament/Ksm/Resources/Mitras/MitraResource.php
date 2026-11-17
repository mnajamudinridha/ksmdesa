<?php

namespace App\Filament\Ksm\Resources\Mitras;

use App\Enums\JenisMitra;
use App\Enums\JenisTransaksi;
use App\Filament\Ksm\Resources\Mitras\Pages\ManageMitras;
use App\Models\Mitra;
use App\Support\NomorHp;
use BackedEnum;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The KSM's partners. Income entered in the cash book can name the partner it came from; the
 * total of those entries is shown as the partner's support.
 */
class MitraResource extends Resource
{
    protected static ?string $model = Mitra::class;

    protected static ?string $tenantRelationshipName = 'mitra';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $modelLabel = 'mitra';

    protected static ?string $pluralModelLabel = 'mitra';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->isAdminKsm()
            ? $query->withSum(['transaksiKas as dukungan' => fn (Builder $transaksi) => $transaksi->where('jenis', JenisTransaksi::Masuk)], 'nominal')
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('nama')
                    ->required()
                    ->maxLength(150)
                    ->scopedUnique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'Mitra dengan nama ini sudah ada.'])
                    ->columnSpanFull(),
                Select::make('jenis')
                    ->options(JenisMitra::class)
                    ->required()
                    ->native(false),
                Toggle::make('aktif')
                    ->default(true)
                    ->inline(false),
                TextInput::make('kontak')
                    ->label('Narahubung')
                    ->maxLength(100),
                TextInput::make('no_hp')
                    ->label('Nomor HP')
                    ->tel()
                    ->maxLength(20)
                    // Validated and saved in the same normalised form (README §4).
                    ->dehydrateStateUsing(fn (?string $state): ?string => NomorHp::normalisasi($state))
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ! NomorHp::valid(NomorHp::normalisasi((string) $value))) {
                            $fail('Nomor HP tidak valid (contoh 081234567890).');
                        }
                    }),
                Textarea::make('alamat')
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Textarea::make('keterangan')
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->emptyStateHeading('Belum ada mitra')
            ->columns([
                TextColumn::make('nama')
                    ->description(fn (Mitra $record): ?string => collect([$record->kontak, $record->no_hp])->filter()->implode(' · ') ?: null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('dukungan')
                    ->label('Dukungan diterima')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->placeholder('—'),
                IconColumn::make('aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->options(JenisMitra::class),
                TernaryFilter::make('aktif')
                    ->default(true),
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
            'index' => ManageMitras::route('/'),
        ];
    }
}
