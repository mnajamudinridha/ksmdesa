<?php

namespace App\Filament\Ksm\Resources\PembayaranIurans;

use App\Filament\Ksm\Resources\PembayaranIurans\Pages\ManagePembayaranIurans;
use App\Filament\Shared\PilihanPengguna;
use App\Models\PembayaranIuran;
use App\Support\BulanIuran;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    /** Admins: every payment. Petugas: the payments they collected ("Iuran diterima"). */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['pelanggan', 'petugas', 'setoran']);
        $user = auth()->user();

        return match (true) {
            (bool) $user?->isAdminKsm() => $query,
            (bool) $user?->isPetugas() => $query->where('petugas_id', $user->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Write under a row lock after re-checking the policy on the fresh row: a deposit made a
     * moment ago (between the page's check and this write) must still lock a petugas out.
     *
     * @param  Closure(PembayaranIuran): bool  $tulis
     */
    public static function tulisTerkunci(PembayaranIuran $record, string $kemampuan, Action $action, Closure $tulis): bool
    {
        return DB::transaction(function () use ($record, $kemampuan, $action, $tulis): bool {
            $terkunci = PembayaranIuran::query()->lockForUpdate()->findOrFail($record->getKey());

            if (! auth()->user()?->can($kemampuan, $terkunci)) {
                Notification::make()->danger()->title('Pembayaran ini baru saja disetor dan tidak bisa diubah lagi.')->send();
                $action->halt();
            }

            return $tulis($terkunci);
        });
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->isPetugas() ? 'Iuran diterima' : 'Pembayaran Iuran';
    }

    public static function form(Schema $schema): Schema
    {
        $admin = fn (): bool => (bool) auth()->user()?->isAdminKsm();

        return $schema
            ->columns(2)
            ->components([
                Select::make('pelanggan_id')
                    ->label('Pelanggan')
                    ->relationship('pelanggan', 'nama', fn ($query) => $query->orderBy('nomor_urut')->orderBy('nama'))
                    ->searchable()
                    ->preload()
                    ->required()
                    // A petugas corrects amounts/months, never moves a payment to another customer.
                    ->disabled(fn (): bool => ! $admin())
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        // Look up through the tenant explicitly rather than relying on the panel's implicit scope.
                        if ($pelanggan = Filament::getTenant()->pelanggan()->find($state)) {
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
                    ->rule(fn (Get $get, ?PembayaranIuran $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($admin, $get, $record): void {
                        $sudahAda = PembayaranIuran::query()
                            ->whereBelongsTo(Filament::getTenant())
                            // A petugas cannot change the customer (field disabled): use the saved one, never the raw state.
                            ->where('pelanggan_id', $admin() ? $get('pelanggan_id') : $record?->pelanggan_id)
                            ->whereDate('periode', Carbon::parse($value)->startOfMonth())
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($sudahAda) {
                            $fail('Pembayaran pelanggan ini untuk bulan tersebut sudah dicatat — ubah catatan yang ada.');
                        }
                    })
                    // A petugas stays inside the same window as "Terima iuran" — checked only when the
                    // month is changed, so saving an older record unchanged never fails.
                    ->rule(fn (?PembayaranIuran $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($admin, $record): void {
                        $bulan = Carbon::parse($value)->startOfMonth()->toDateString();

                        if ($admin() || $bulan === $record?->periode?->toDateString()) {
                            return;
                        }

                        $awal = BulanIuran::iniBulan()->subMonths(BulanIuran::MUNDUR)->toDateString();
                        $akhir = BulanIuran::iniBulan()->addMonths(BulanIuran::MAJU)->toDateString();

                        if ($bulan < $awal || $bulan > $akhir) {
                            $fail('Petugas hanya bisa mencatat 6 bulan ke belakang sampai 2 bulan ke depan.');
                        }
                    }),
                TextInput::make('nominal')
                    ->prefix('Rp')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->maxValue(10_000_000)
                    ->required(),
                DatePicker::make('dibayar_pada')
                    ->label('Tanggal diterima')
                    ->native(false)
                    ->default(now())
                    // The day the petugas took the cash is a fact of the deposit trail: admins only.
                    ->disabled(fn (): bool => ! $admin()),
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
                TextColumn::make('petugas.name')
                    ->label('Diterima oleh')
                    ->placeholder('Pengurus')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdminKsm()),
                TextColumn::make('status_setor')
                    ->label('Setoran')
                    ->state(fn (PembayaranIuran $record): string => $record->statusSetor())
                    ->formatStateUsing(fn (string $state, PembayaranIuran $record): string => match ($state) {
                        'langsung' => 'Langsung',
                        'belum' => 'Belum disetor',
                        default => 'Disetor '.$record->setoran?->tanggal?->translatedFormat('d M Y'),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'langsung' => 'gray',
                        'belum' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('catatan')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('periode')
                    ->label('Bulan')
                    ->options(fn (): array => self::opsiBulan()),
                SelectFilter::make('status_setor')
                    ->label('Setoran')
                    ->options(['belum' => 'Belum disetor', 'disetor' => 'Sudah disetor', 'langsung' => 'Langsung ke pengurus'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'belum' => $query->belumDisetor(),
                        'disetor' => $query->whereNotNull('setoran_id'),
                        'langsung' => $query->whereNull('petugas_id'),
                        default => $query,
                    }),
                SelectFilter::make('petugas_id')
                    ->label('Petugas')
                    ->options(fn (): array => PilihanPengguna::petugas())
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdminKsm()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->using(fn (PembayaranIuran $record, array $data, EditAction $action): bool => self::tulisTerkunci($record, 'update', $action, fn (PembayaranIuran $terkunci): bool => $terkunci->update($data))),
                    DeleteAction::make()
                        ->using(fn (PembayaranIuran $record, DeleteAction $action): bool => self::tulisTerkunci($record, 'delete', $action, fn (PembayaranIuran $terkunci): bool => (bool) $terkunci->delete())),
                ]),
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
