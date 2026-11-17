<?php

namespace App\Filament\Ksm\Resources\TransaksiKas;

use App\Enums\JenisMitra;
use App\Enums\JenisTransaksi;
use App\Filament\Ksm\Resources\TransaksiKas\Pages\ManageTransaksiKas;
use App\Filament\Shared\PilihanMaster;
use App\Models\Mitra;
use App\Models\TransaksiKas;
use App\Support\Hari;
use App\Support\RingkasanIuran;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * "Buku Kas": the committee's manual income and spending, with receipts. Fees and material
 * sales are not entered here — the cash summary counts them from their own records.
 */
class TransaksiKasResource extends Resource
{
    protected static ?string $model = TransaksiKas::class;

    protected static ?string $tenantRelationshipName = 'transaksiKas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Data Input';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Buku Kas';

    protected static ?string $modelLabel = 'transaksi kas';

    protected static ?string $pluralModelLabel = 'transaksi kas';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return auth()->user()?->isAdminKsm()
            ? $query->with(['kategori', 'mitra', 'dicatatOleh'])
            : $query->whereRaw('1 = 0');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                ToggleButtons::make('jenis')
                    ->options(JenisTransaksi::class)
                    ->inline()
                    ->required()
                    ->default(JenisTransaksi::Keluar->value)
                    ->live()
                    // A category or partner picked for the other kind would not be valid any more.
                    ->afterStateUpdated(function (Set $set): void {
                        $set('kategori_transaksi_id', null);
                        $set('mitra_id', null);
                    })
                    ->columnSpanFull(),
                DatePicker::make('tanggal')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->default(fn (): string => Hari::tanggal())
                    ->maxDate(fn (): string => Hari::tanggal())
                    ->required(),
                TextInput::make('nominal')
                    ->numeric()
                    ->integer()
                    ->prefix('Rp')
                    ->minValue(1)
                    ->maxValue(1_000_000_000)
                    ->required(),
                Select::make('kategori_transaksi_id')
                    ->label('Kategori')
                    // Options = active categories of the chosen kind (+ the saved one): the "in" rule
                    // rejects a category of the other kind or of another KSM.
                    ->options(fn (Get $get, ?TransaksiKas $record): array => PilihanMaster::kategori(JenisTransaksi::dari($get('jenis')), $record?->kategori_transaksi_id))
                    ->required()
                    ->helperText(fn (Get $get): ?string => PilihanMaster::kategori(JenisTransaksi::dari($get('jenis'))) === []
                        ? 'Belum ada kategori — tambahkan di Data Master → Kategori Transaksi.'
                        : null),
                Select::make('mitra_id')
                    ->label('Dari mitra (opsional)')
                    ->options(fn (?TransaksiKas $record): array => PilihanMaster::mitra($record?->mitra_id))
                    ->searchable()
                    ->live()
                    ->helperText(fn (Get $get): ?string => self::peringatanPengepul($get('mitra_id')))
                    // Hidden for spending: not validated or saved; the model also clears it (second layer).
                    ->visible(fn (Get $get): bool => JenisTransaksi::dari($get('jenis'))?->bolehMitra() ?? false),
                TextInput::make('uraian')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('mis. Beli bensin motor angkut')
                    ->columnSpanFull(),
                FileUpload::make('bukti')
                    ->label('Bukti (foto/PDF)')
                    ->disk('local')
                    // One folder per KSM, removed with the KSM (Ksm::deleted) — the database cascade
                    // deletes the rows without model events, so the per-entry hook never runs then.
                    ->directory(fn (): string => 'bukti-kas/'.Filament::getTenant()->getKey())
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                    ->maxSize(4096)
                    // Download, not "open": Laravel serves private files with a sandbox CSP, in which
                    // Chrome's PDF viewer does not run.
                    ->downloadable()
                    ->preventFilePathTampering()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * A sale to a buyer is already cash income from Catatan Material: entering it here as well
     * would count it twice. Warn when the chosen partner (of this KSM) is a buyer.
     */
    public static function peringatanPengepul(mixed $mitraId): ?string
    {
        $pengepul = filled($mitraId) && is_scalar($mitraId) && Mitra::query()
            ->whereBelongsTo(Filament::getTenant())
            ->whereKey($mitraId)
            ->where('jenis', JenisMitra::Pengepul)
            ->exists();

        return $pengepul ? 'Mitra ini pengepul. Penjualan material sudah masuk kas dari Catatan Material — jangan dicatat lagi di sini.' : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal', 'desc')
            ->emptyStateHeading('Belum ada transaksi kas')
            ->emptyStateDescription('Catat pemasukan lain dan pengeluaran di sini. Iuran tidak perlu dicatat ulang — dihitung otomatis.')
            ->columns([
                TextColumn::make('tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('jenis')
                    ->badge(),
                TextColumn::make('kategori.nama')
                    ->label('Kategori')
                    ->placeholder('Tanpa kategori'),
                TextColumn::make('uraian')
                    ->limit(50)
                    ->description(fn (TransaksiKas $record): ?string => $record->mitra?->nama)
                    ->searchable(),
                TextColumn::make('nominal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->color(fn (TransaksiKas $record): string => $record->jenis->getColor())
                    ->sortable()
                    ->summarize([
                        Summarizer::make()
                            ->label('Pemasukan')
                            ->using(fn (QueryBuilder $query): int => (int) (clone $query)->where('jenis', JenisTransaksi::Masuk->value)->sum('nominal'))
                            ->formatStateUsing(fn ($state): string => RingkasanIuran::rupiah((int) $state)),
                        Summarizer::make()
                            ->label('Pengeluaran')
                            ->using(fn (QueryBuilder $query): int => (int) (clone $query)->where('jenis', JenisTransaksi::Keluar->value)->sum('nominal'))
                            ->formatStateUsing(fn ($state): string => RingkasanIuran::rupiah((int) $state)),
                    ]),
                IconColumn::make('bukti')
                    ->label('Bukti')
                    ->state(fn (TransaksiKas $record): bool => filled($record->bukti))
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedPaperClip)
                    ->falseIcon(Heroicon::OutlinedMinus),
                TextColumn::make('dicatatOleh.name')
                    ->label('Dicatat oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('jenis')
                    ->options(JenisTransaksi::class),
                // Explicit options: ->relationship() would load every KSM's categories / partners.
                SelectFilter::make('kategori_transaksi_id')
                    ->label('Kategori')
                    ->options(fn (): array => PilihanMaster::semuaKategori()),
                SelectFilter::make('mitra_id')
                    ->label('Mitra')
                    ->options(fn (): array => PilihanMaster::mitra()),
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
                    // Through the action (policy + the KSM-scoped table record), not a shareable link.
                    Action::make('unduhBukti')
                        ->label('Unduh bukti')
                        ->icon(Heroicon::OutlinedPaperClip)
                        ->visible(fn (TransaksiKas $record): bool => filled($record->bukti))
                        ->authorize('view')
                        ->action(fn (TransaksiKas $record): StreamedResponse => Storage::disk('local')->download(
                            $record->bukti,
                            'bukti-kas-'.$record->tanggal->toDateString().'-'.$record->getKey().'.'.pathinfo($record->bukti, PATHINFO_EXTENSION),
                        )),
                    EditAction::make(),
                    DeleteAction::make()
                        ->modalDescription('Transaksi dan berkas buktinya akan dihapus.'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransaksiKas::route('/'),
        ];
    }
}
