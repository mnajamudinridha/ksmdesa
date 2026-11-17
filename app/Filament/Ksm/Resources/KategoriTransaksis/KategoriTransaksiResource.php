<?php

namespace App\Filament\Ksm\Resources\KategoriTransaksis;

use App\Enums\JenisTransaksi;
use App\Filament\Ksm\Resources\KategoriTransaksis\Pages\ManageKategoriTransaksis;
use App\Models\KategoriTransaksi;
use BackedEnum;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
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
 * Categories for the cash book's manual entries (income and spending). Fees and material sales
 * are counted automatically, so those names are refused here.
 */
class KategoriTransaksiResource extends Resource
{
    protected static ?string $model = KategoriTransaksi::class;

    protected static ?string $tenantRelationshipName = 'kategoriTransaksi';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'kategori transaksi';

    protected static ?string $pluralModelLabel = 'kategori transaksi';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->isAdminKsm()
            ? $query->withCount('transaksiKas')
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                ToggleButtons::make('jenis')
                    ->options(JenisTransaksi::class)
                    ->inline()
                    ->required()
                    ->default(JenisTransaksi::Keluar->value)
                    // Disabled fields are not saved: a used category's kind cannot be flipped from the
                    // client, which would move its transactions to the other side of the report.
                    ->disabled(fn (?KategoriTransaksi $record): bool => (bool) $record?->dipakai())
                    ->helperText(fn (?KategoriTransaksi $record): ?string => $record?->dipakai() ? 'Sudah dipakai transaksi — jenis tidak bisa diubah.' : null),
                TextInput::make('nama')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('mis. BBM & perawatan kendaraan')
                    ->scopedUnique(
                        ignoreRecord: true,
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query->where('jenis', JenisTransaksi::dari($get('jenis'))),
                    )
                    ->validationMessages(['unique' => 'Kategori dengan nama ini sudah ada untuk jenis yang sama.'])
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (in_array(mb_strtolower(trim((string) $value)), KategoriTransaksi::NAMA_SISTEM, true)) {
                            $fail('"Iuran" dan "Penjualan material" sudah dihitung otomatis — pakai nama lain.');
                        }
                    }),
                Toggle::make('aktif')
                    ->default(true)
                    ->helperText('Kategori nonaktif tidak bisa dipilih untuk transaksi baru.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nama')
            ->emptyStateHeading('Belum ada kategori')
            ->emptyStateDescription('Contoh pengeluaran: BBM & perawatan kendaraan, insentif petugas, peralatan. Contoh pemasukan: dukungan mitra, hibah.')
            ->columns([
                TextColumn::make('nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('transaksi_kas_count')
                    ->label('Transaksi')
                    ->numeric(),
                IconColumn::make('aktif')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->options(JenisTransaksi::class),
                TernaryFilter::make('aktif'),
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
            'index' => ManageKategoriTransaksis::route('/'),
        ];
    }
}
