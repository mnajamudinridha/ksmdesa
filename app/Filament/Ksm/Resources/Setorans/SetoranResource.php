<?php

namespace App\Filament\Ksm\Resources\Setorans;

use App\Filament\Ksm\Resources\Setorans\Pages\CreateSetoran;
use App\Filament\Ksm\Resources\Setorans\Pages\ListSetorans;
use App\Filament\Ksm\Resources\Setorans\Pages\ViewSetoran;
use App\Models\PembayaranIuran;
use App\Models\Setoran;
use App\Models\User;
use App\Support\Hari;
use App\Support\RingkasanIuran;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
 * Deposits: a petugas hands the fee cash they collected to the committee. The admin picks
 * the petugas and ticks the payments handed over; the total is the sum of those payments.
 * Not edited — cancelling (delete) returns its payments to "belum disetor".
 */
class SetoranResource extends Resource
{
    protected static ?string $model = Setoran::class;

    protected static ?string $tenantRelationshipName = 'setoran';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Data Input';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'setoran petugas';

    protected static ?string $pluralModelLabel = 'setoran petugas';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('petugas_id')
                            ->label('Petugas')
                            ->options(fn (): array => self::opsiPetugas())
                            ->required()
                            ->live()
                            // Default: everything the petugas still holds is ticked.
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('pembayaran', array_map('strval', array_keys(self::opsiPembayaran(filled($state) ? (int) $state : null))))),
                        DatePicker::make('tanggal')
                            ->label('Tanggal diterima')
                            ->native(false)
                            ->default(fn (): string => Hari::tanggal())
                            ->maxDate(fn (): string => Hari::tanggal())
                            ->required(),
                        CheckboxList::make('pembayaran')
                            ->label('Pembayaran yang diserahkan')
                            // Only this petugas's undeposited payments in this KSM: the "in" rule rejects any other id.
                            ->options(fn (Get $get): array => self::opsiPembayaran(filled($get('petugas_id')) ? (int) $get('petugas_id') : null))
                            ->required()
                            ->minItems(1)
                            ->bulkToggleable()
                            ->live()
                            ->visible(fn (Get $get): bool => filled($get('petugas_id')))
                            ->columnSpanFull(),
                        TextEntry::make('total')
                            ->label('Total setoran')
                            ->state(fn (Get $get): string => RingkasanIuran::rupiah(self::totalTerpilih(
                                filled($get('petugas_id')) ? (int) $get('petugas_id') : null,
                                $get('pembayaran') ?? [],
                            )))
                            ->visible(fn (Get $get): bool => filled($get('petugas_id'))),
                        Textarea::make('catatan')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('tanggal')->date('l, d M Y'),
                        TextEntry::make('petugas.name')->label('Petugas'),
                        TextEntry::make('penerima.name')->label('Diterima oleh')->placeholder('—'),
                        TextEntry::make('total')
                            ->label('Total')
                            ->state(fn (Setoran $record): string => RingkasanIuran::rupiah((int) $record->pembayaranIuran()->sum('nominal'))),
                        TextEntry::make('catatan')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Pembayaran')
                    ->schema([
                        RepeatableEntry::make('pembayaranIuran')
                            ->hiddenLabel()
                            ->columns(['default' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('pelanggan.nama')->label('Pelanggan'),
                                TextEntry::make('periode')->label('Bulan')->date('F Y'),
                                TextEntry::make('nominal')->money('IDR', locale: 'id', decimalPlaces: 0),
                                TextEntry::make('dibayar_pada')->label('Diterima petugas')->date('d M Y')->placeholder('—'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['petugas', 'penerima'])
                ->withCount('pembayaranIuran')
                ->withSum('pembayaranIuran as total', 'nominal'))
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->date('D, d M Y')
                    ->sortable(),
                TextColumn::make('petugas.name')
                    ->label('Petugas')
                    ->searchable(),
                TextColumn::make('pembayaran_iuran_count')
                    ->label('Pembayaran')
                    ->numeric(),
                TextColumn::make('total')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->summarize(Sum::make('total')->label('Total')->money('IDR', locale: 'id', decimalPlaces: 0)),
                TextColumn::make('penerima.name')
                    ->label('Diterima oleh')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('petugas_id')
                    ->label('Petugas')
                    ->relationship('petugas', 'name'),
                Filter::make('tanggal')
                    ->schema([
                        DatePicker::make('dari')->native(false),
                        DatePicker::make('sampai')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn (Builder $q, string $dari) => $q->whereDate('tanggal', '>=', $dari))
                        ->when($data['sampai'] ?? null, fn (Builder $q, string $sampai) => $q->whereDate('tanggal', '<=', $sampai))),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    self::aksiBatal(DeleteAction::make()),
                ]),
            ]);
    }

    /** "Batalkan setoran": deleting frees its payments (Setoran deleting hook), none is lost. */
    public static function aksiBatal(DeleteAction $action): DeleteAction
    {
        return $action
            ->label('Batalkan setoran')
            ->icon(Heroicon::OutlinedXCircle)
            ->modalHeading('Batalkan setoran?')
            ->modalDescription('Pembayaran di dalamnya kembali berstatus belum disetor. Tidak ada pembayaran yang terhapus.')
            ->successNotificationTitle('Setoran dibatalkan');
    }

    /**
     * Users of this KSM who still hold collected cash (also a petugas deactivated since).
     *
     * @return array<int, string>
     */
    public static function opsiPetugas(): array
    {
        return User::query()
            ->whereBelongsTo(Filament::getTenant())
            ->whereHas('iuranDiterima', fn (Builder $query) => $query->belumDisetor())
            ->withSum(['iuranDiterima as belum_disetor' => fn (Builder $query) => $query->belumDisetor()], 'nominal')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => $user->name.' — '.RingkasanIuran::rupiah((int) $user->belum_disetor)])
            ->all();
    }

    /**
     * This petugas's undeposited payments in the current KSM.
     *
     * @return array<int, string>
     */
    public static function opsiPembayaran(?int $petugasId): array
    {
        if ($petugasId === null) {
            return [];
        }

        return self::belumDisetor($petugasId)
            ->with('pelanggan')
            ->orderBy('dibayar_pada')
            ->orderBy('periode')
            ->get()
            ->mapWithKeys(fn (PembayaranIuran $pembayaran): array => [
                $pembayaran->getKey() => "{$pembayaran->pelanggan->nama} · {$pembayaran->periode->translatedFormat('F Y')} · ".RingkasanIuran::rupiah($pembayaran->nominal),
            ])
            ->all();
    }

    /** @param  array<int, int|string>  $ids */
    public static function totalTerpilih(?int $petugasId, array $ids): int
    {
        return $petugasId === null || $ids === [] ? 0 : (int) self::belumDisetor($petugasId)->whereKey($ids)->sum('nominal');
    }

    /** @return Builder<PembayaranIuran> */
    public static function belumDisetor(int $petugasId): Builder
    {
        return PembayaranIuran::query()
            ->whereBelongsTo(Filament::getTenant())
            ->where('petugas_id', $petugasId)
            ->belumDisetor();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSetorans::route('/'),
            'create' => CreateSetoran::route('/create'),
            'view' => ViewSetoran::route('/{record}'),
        ];
    }
}
