<?php

namespace App\Filament\Ksm\Resources\Pelanggans;

use App\Enums\PeranPengguna;
use App\Filament\AvatarProviders\InisialAvatar;
use App\Filament\Ksm\Resources\Pelanggans\Pages\ManagePelanggans;
use App\Filament\Shared\AvatarField;
use App\Filament\Shared\PenggunaForm;
use App\Filament\Shared\PilihanMaster;
use App\Filament\Shared\PilihanPengguna;
use App\Models\Pelanggan;
use App\Models\TarifLayanan;
use App\Models\User;
use App\Support\AkunWarga;
use App\Support\NomorHp;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
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
        $query = parent::getEloquentQuery()->with(['akunWarga', 'petugas', 'tarifLayanan', 'wilayah']);
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
                Section::make('Akun login warga')
                    ->description(fn (?Pelanggan $record): ?string => self::keteranganBersama($record))
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        AvatarField::make('akun_avatar')
                            // afterStateHydrated is a single slot: set the state and redo the
                            // upload's own file hydration (formatStateUsing would skip it).
                            ->afterStateHydrated(function (FileUpload $component, ?Pelanggan $record): void {
                                $component->state(self::akunBisaDiubah($record) ? $record?->akunWarga?->avatar : null);
                                $component->hydrateFiles();
                            })
                            ->visible(fn (?Pelanggan $record): bool => self::akunBisaDiubah($record))
                            ->columnSpanFull(),
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
                        TextInput::make('akun_email')
                            ->label('Email')
                            ->email()
                            ->maxLength(255)
                            ->formatStateUsing(fn (?Pelanggan $record): ?string => $record?->akunWarga?->email)
                            ->rule(fn (?Pelanggan $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                if (filled($value) && PenggunaForm::pemilikLain(Filament::getTenant(), 'email', mb_strtolower(trim((string) $value)), $record?->akunWarga) !== null) {
                                    $fail('Email sudah dipakai pengguna lain di KSM ini.');
                                }
                            })
                            ->helperText('Opsional.')
                            ->visible(fn (?Pelanggan $record): bool => self::akunBisaDiubah($record)),
                        TextInput::make('akun_sandi')
                            ->label('Kata sandi')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->maxLength(255)
                            ->autocomplete('new-password')
                            ->helperText(fn (?Pelanggan $record): string => $record?->akunWarga
                                ? 'Kosongkan bila tidak diganti.'
                                : 'Kosongkan untuk memakai sandi default desa.')
                            ->visible(fn (?Pelanggan $record): bool => self::akunBisaDiubah($record)),
                        Toggle::make('akun_aktif')
                            ->label('Akun bisa login')
                            ->formatStateUsing(fn (?Pelanggan $record): bool => $record?->akunWarga?->aktif ?? true)
                            ->helperText('Matikan untuk memblokir login akun ini; data pelanggan tetap.')
                            ->visible(fn (?Pelanggan $record): bool => self::akunBisaDiubah($record)),
                    ]),
                Section::make('Petugas')
                    ->columnSpanFull()
                    ->schema([
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
                        Select::make('wilayah_id')
                            ->label('RT / RW')
                            // Options = this KSM's RTs: the built-in "in" rule rejects any other id.
                            ->options(fn (?Pelanggan $record): array => PilihanMaster::wilayah($record?->wilayah_id))
                            ->searchable()
                            ->preload()
                            ->placeholder('Belum ditetapkan')
                            ->helperText(fn (): ?string => PilihanMaster::wilayah() === [] ? 'Isi daftar RT/RW di Data Master → Wilayah (RT/RW).' : null)
                            ->columnSpanFull(),
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

    /**
     * The account fields (photo, email, password, login) edit the customer's own warga
     * account. An account of a petugas/admin who is also a customer is theirs to manage in
     * Pengguna — otherwise this form could give staff the village default password.
     */
    private static function akunBisaDiubah(?Pelanggan $record): bool
    {
        $akun = $record?->akunWarga;

        return $akun === null || $akun->peran === PeranPengguna::Warga;
    }

    /** One person can own several customers (house + shop): say whose login this also is. */
    private static function keteranganBersama(?Pelanggan $record): ?string
    {
        $akun = $record?->akunWarga;

        if ($akun === null) {
            return 'Setiap pelanggan otomatis punya akun warga untuk login.';
        }

        if (! self::akunBisaDiubah($record)) {
            return "Akun ini milik {$akun->peran->getLabel()} {$akun->name}; foto, email, dan sandinya diubah di menu Pengguna.";
        }

        $lain = $akun->pelangganMilik()->whereKeyNot($record->getKey())->pluck('nama');

        return $lain->isEmpty() ? null : 'Akun ini juga dipakai pelanggan: '.$lain->join(', ').'.';
    }

    /** Admin: save the record and apply the typed login number and account fields. */
    public static function simpan(Pelanggan $pelanggan, array $data): Pelanggan
    {
        $nomor = Arr::pull($data, 'no_hp_akun');
        $akunData = Arr::only($data, self::KOLOM_AKUN);
        $data = Arr::except($data, self::KOLOM_AKUN);

        return DB::transaction(function () use ($pelanggan, $data, $nomor, $akunData): Pelanggan {
            $akunSebelum = $pelanggan->user_id;
            $pelanggan->fill($data);

            if ($pelanggan->exists) {
                AkunWarga::sinkronkanNomor($pelanggan, $nomor);
            } else {
                $ksm = Filament::getTenant();
                $pelanggan->ksm()->associate($ksm);
                $pelanggan->akunWarga()->associate(AkunWarga::untukNomor($ksm, $nomor, $pelanggan->nama));
            }

            $pelanggan->save();

            // The fields were filled from the account that was shown, so they only go back to
            // that account — or, on create, to a brand-new one. Never to someone else's existing
            // account the number now links to, nor to a new account that replaced the shown one
            // (it would inherit the old person's email/photo), nor to staff.
            $akun = $pelanggan->akunWarga;
            $akunSama = $akun !== null && ($akunSebelum === null ? $akun->wasRecentlyCreated : $akun->getKey() === $akunSebelum);

            if ($akunSama && $akun->peran === PeranPengguna::Warga && $akunData !== []) {
                self::simpanAkun($akun, $akunData);
            }

            return $pelanggan;
        });
    }

    /** @var list<string> */
    private const KOLOM_AKUN = ['akun_avatar', 'akun_email', 'akun_sandi', 'akun_aktif'];

    /** @param  array<string, mixed>  $akunData */
    private static function simpanAkun(User $akun, array $akunData): void
    {
        if (array_key_exists('akun_avatar', $akunData)) {
            $akun->avatar = $akunData['akun_avatar'];
        }

        if (array_key_exists('akun_email', $akunData)) {
            $akun->email = filled($akunData['akun_email']) ? mb_strtolower(trim($akunData['akun_email'])) : null;
        }

        if (filled($akunData['akun_sandi'] ?? null)) {
            // A password of their own: no longer follows the village default (User saving hook).
            $akun->password = $akunData['akun_sandi'];
        }

        if (array_key_exists('akun_aktif', $akunData)) {
            $akun->aktif = (bool) $akunData['akun_aktif'];
        }

        $akun->save();
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
                TextEntry::make('wilayah')
                    ->label('RT / RW')
                    ->state(fn (Pelanggan $record): ?string => $record->wilayah?->label())
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
                TextColumn::make('wilayah_label')
                    ->label('RT / RW')
                    ->state(fn (Pelanggan $record): ?string => $record->wilayah?->label())
                    ->placeholder('—')
                    ->toggleable(),
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
                SelectFilter::make('wilayah_id')
                    ->label('RT / RW')
                    // Explicit options: ->relationship() would load every KSM's RTs.
                    ->options(fn (): array => PilihanMaster::wilayah()),
                Filter::make('tanpa_rt')
                    ->label('Tanpa RT')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNull('wilayah_id')),
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
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn (): bool => ! auth()->user()?->isAdminKsm()),
                    EditAction::make()
                        ->using(fn (Pelanggan $record, array $data): Pelanggan => self::simpan($record, $data)),
                    Action::make('bukaPeta')
                        ->label('Buka peta')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->url(fn (Pelanggan $record): ?string => $record->mapsUrl(), shouldOpenInNewTab: true)
                        ->visible(fn (Pelanggan $record): bool => $record->mapsUrl() !== null),
                ]),
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
