<?php

namespace App\Filament\Ksm\Resources\Pelanggans;

use App\Filament\AvatarProviders\InisialAvatar;
use App\Filament\Ksm\Resources\Pelanggans\Pages\ManagePelanggans;
use App\Filament\Shared\PilihanPengguna;
use App\Models\Pelanggan;
use App\Models\TarifLayanan;
use App\Support\AkunWarga;
use App\Support\NomorHp;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Customers of the current KSM. Admins manage everything; a petugas sees, read-only,
 * the customers assigned to them (address, phone, map) and none of the money.
 */
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

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['akunWarga', 'petugas', 'tarifLayanan']);
        $user = auth()->user();

        return match (true) {
            (bool) $user?->isAdminKsm() => $query,
            (bool) $user?->isPetugas() => $query->where('petugas_id', $user->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Pelanggan')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
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
                                // Look up through the tenant explicitly rather than relying on the panel's implicit scope.
                                if ($tarif = Filament::getTenant()->tarifLayanan()->find($state)) {
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
                        Toggle::make('aktif')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Matikan bila berhenti berlangganan. Akun warganya tetap bisa login.'),
                    ]),
                Section::make('Akun warga & petugas')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('no_hp_akun')
                            ->label('Nomor HP (login warga)')
                            ->tel()
                            ->maxLength(20)
                            ->formatStateUsing(fn (?Pelanggan $record): ?string => $record?->akunWarga?->no_hp)
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! NomorHp::valid(NomorHp::normalisasi((string) $value))) {
                                    $fail('Nomor HP tidak valid (contoh: 081234567890).');
                                }
                            })
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get, ?Pelanggan $record): string => self::keteranganAkun($get('no_hp_akun'), $record)),
                        Select::make('petugas_id')
                            ->label('Petugas pengangkut')
                            ->options(fn (?Pelanggan $record): array => PilihanPengguna::petugas($record?->petugas_id))
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make('Lokasi')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Textarea::make('alamat')
                            ->rows(2)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('rt')
                            ->label('RT')
                            ->maxLength(20),
                        TextInput::make('rw')
                            ->label('RW')
                            ->maxLength(10),
                        TextInput::make('patokan')
                            ->placeholder('mis. depan masjid, pagar hijau')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('latitude')
                            ->numeric()
                            ->step('any')
                            ->minValue(-90)
                            ->maxValue(90),
                        TextInput::make('longitude')
                            ->numeric()
                            ->step('any')
                            ->minValue(-180)
                            ->maxValue(180)
                            ->helperText('Dari Google Maps: tekan lama titik rumah, salin angka koordinatnya.'),
                    ]),
                Section::make('Catatan')
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('catatan_pengangkutan')
                            ->label('Catatan untuk petugas')
                            ->rows(2)
                            ->maxLength(1000),
                        Textarea::make('catatan')
                            ->label('Catatan internal (admin)')
                            ->rows(2),
                    ]),
            ]);
    }

    /** What happens to the account with the typed number — warns before linking to someone else. */
    private static function keteranganAkun(?string $ketikan, ?Pelanggan $record): string
    {
        $nomor = NomorHp::normalisasi($ketikan);

        if ($nomor === null) {
            return $record?->akunWarga
                ? 'Akun warga sudah ada. Isi nomor HP agar warga bisa login.'
                : 'Akun warga dibuat otomatis. Isi nomor HP agar warga bisa login.';
        }

        $pemilik = AkunWarga::pemilik(Filament::getTenant(), $nomor);

        if ($pemilik !== null && ! $pemilik->is($record?->akunWarga)) {
            return "Nomor ini milik akun {$pemilik->name} — pelanggan akan dihubungkan ke akun tersebut.";
        }

        return 'Warga login dengan nomor ini dan sandi default desa (bisa diganti di Profil saya).';
    }

    /** Admin: save the record and apply the typed login number through AkunWarga. */
    public static function simpan(Pelanggan $pelanggan, array $data): Pelanggan
    {
        $nomor = Arr::pull($data, 'no_hp_akun');

        return DB::transaction(function () use ($pelanggan, $data, $nomor): Pelanggan {
            $pelanggan->fill($data);

            if ($pelanggan->exists) {
                AkunWarga::sinkronkanNomor($pelanggan, $nomor);
            } else {
                $ksm = Filament::getTenant();
                $pelanggan->ksm()->associate($ksm);
                $pelanggan->akunWarga()->associate(AkunWarga::untukNomor($ksm, $nomor, $pelanggan->nama));
            }

            $pelanggan->save();

            return $pelanggan;
        });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextEntry::make('nama')
                    ->columnSpanFull(),
                TextEntry::make('akunWarga.no_hp')
                    ->label('Nomor HP')
                    ->placeholder('—'),
                TextEntry::make('patokan')
                    ->placeholder('—'),
                TextEntry::make('alamat')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('rt')
                    ->label('RT')
                    ->placeholder('—'),
                TextEntry::make('rw')
                    ->label('RW')
                    ->placeholder('—'),
                TextEntry::make('catatan_pengangkutan')
                    ->label('Catatan untuk petugas')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $admin = fn (): bool => (bool) auth()->user()?->isAdminKsm();

        return $table
            ->recordTitleAttribute('nama')
            ->defaultSort('nomor_urut')
            ->columns([
                ImageColumn::make('akunWarga.avatar')
                    ->label('')
                    ->disk('local')
                    ->visibility('private')
                    ->circular()
                    ->defaultImageUrl(fn (Pelanggan $record): string => InisialAvatar::untukNama($record->nama)),
                TextColumn::make('nomor_urut')
                    ->label('No.')
                    ->sortable(),
                TextColumn::make('nama')
                    ->description(fn (Pelanggan $record): ?string => $record->patokan)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('akunWarga.no_hp')
                    ->label('Nomor HP')
                    ->placeholder('belum ada')
                    ->searchable(),
                TextColumn::make('alamat')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('rt')
                    ->label('RT')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('petugas.name')
                    ->label('Petugas')
                    ->placeholder('— belum ada —')
                    ->visible($admin),
                TextColumn::make('tarifLayanan.nama')
                    ->label('Tarif')
                    ->badge()
                    ->placeholder('—')
                    ->visible($admin),
                TextColumn::make('iuran_bulanan')
                    ->label('Iuran/bulan')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable()
                    ->visible($admin),
                IconColumn::make('aktif')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('aktif')
                    ->default(true),
                SelectFilter::make('petugas_id')
                    ->label('Petugas')
                    ->options(fn (): array => PilihanPengguna::petugas())
                    ->visible($admin),
                Filter::make('tanpa_petugas')
                    ->label('Tanpa petugas aktif')
                    ->query(fn (Builder $query): Builder => $query->tanpaPetugasAktif())
                    ->toggle()
                    ->visible($admin),
                SelectFilter::make('tarif_layanan_id')
                    ->label('Tarif')
                    ->relationship('tarifLayanan', 'nama')
                    ->visible($admin),
            ])
            ->recordActions([
                Action::make('bukaPeta')
                    ->label('Peta')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->color('gray')
                    ->url(fn (Pelanggan $record): ?string => $record->mapsUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (Pelanggan $record): bool => $record->mapsUrl() !== null),
                ViewAction::make()
                    ->visible(fn (): bool => ! auth()->user()?->isAdminKsm()),
                EditAction::make()
                    ->using(fn (Pelanggan $record, array $data): Pelanggan => self::simpan($record, $data)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('tetapkanPetugas')
                        ->label('Tetapkan petugas')
                        ->icon(Heroicon::OutlinedTruck)
                        ->authorize(fn (): bool => (bool) auth()->user()?->isAdminKsm())
                        ->schema([
                            Select::make('petugas_id')
                                ->label('Petugas pengangkut')
                                ->options(fn (): array => PilihanPengguna::petugas())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['petugas_id' => $data['petugas_id']]);

                            Notification::make()
                                ->title($records->count().' pelanggan ditugaskan.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePelanggans::route('/'),
        ];
    }
}
