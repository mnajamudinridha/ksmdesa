<?php

namespace App\Filament\Ksm\Resources\Pengangkutans;

use App\Enums\StatusPengangkutan;
use App\Filament\Ksm\Resources\Pengangkutans\Pages\CreatePengangkutan;
use App\Filament\Ksm\Resources\Pengangkutans\Pages\EditPengangkutan;
use App\Filament\Ksm\Resources\Pengangkutans\Pages\ListPengangkutans;
use App\Filament\Shared\PilihanPengguna;
use App\Models\Pelanggan;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Support\Hari;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Daily collection rounds. A petugas records their own round, one row per house, with an
 * estimated weight; they may correct it until the end of the next day (village time).
 * Admins see and correct every round in the KSM.
 */
class PengangkutanResource extends Resource
{
    protected static ?string $model = Pengangkutan::class;

    protected static ?string $tenantRelationshipName = 'pengangkutan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Data Input';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'pengangkutan';

    protected static ?string $pluralModelLabel = 'pengangkutan';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('petugas');
        $user = auth()->user();

        return match (true) {
            (bool) $user?->isAdminKsm() => $query,
            (bool) $user?->isPetugas() => $query->where('petugas_id', $user->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public static function form(Schema $schema): Schema
    {
        $admin = fn (): bool => (bool) auth()->user()?->isAdminKsm();

        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('petugas_id')
                            ->label('Petugas')
                            ->options(fn (?Pengangkutan $record): array => PilihanPengguna::petugas($record?->petugas_id))
                            ->required()
                            ->visible($admin)
                            // The round belongs to whoever did it; on edit only its houses change.
                            ->disabledOn('edit')
                            ->live()
                            ->afterStateUpdated(function (?string $state, Select $component): void {
                                $rincian = $component->getContainer()->getComponent(fn ($c): bool => $c instanceof Repeater);
                                $rincian?->state(self::itemAwal($rincian, filled($state) ? (int) $state : null));
                            }),
                        DatePicker::make('tanggal')
                            ->native(false)
                            ->displayFormat('l, d M Y')
                            ->default(fn (): string => Hari::tanggal())
                            ->maxDate(fn (): string => Hari::tanggal())
                            // A petugas may back-fill yesterday at most; admins may correct any day.
                            ->minDate(fn (): ?string => $admin() ? null : Hari::tanggal(1))
                            ->disabled(fn (string $operation): bool => $operation === 'edit' && ! $admin())
                            ->required()
                            ->rule(fn (Get $get, ?Pengangkutan $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                $sudahAda = Pengangkutan::query()
                                    ->where('petugas_id', self::petugasEfektif($get('petugas_id'), $record))
                                    // The picker hands over 'Y-m-d H:i:s'; compare calendar days.
                                    ->whereDate('tanggal', Carbon::parse($value)->toDateString())
                                    ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                                    ->exists();

                                if ($sudahAda) {
                                    $fail('Pengangkutan petugas ini pada tanggal tersebut sudah dicatat — ubah catatan yang ada.');
                                }
                            }),
                        Repeater::make('rincian')
                            ->label('Rumah yang dikunjungi')
                            ->relationship('rincian')
                            // Not defaultItems(): that writes the same default slot and would wipe this prefill.
                            ->default(fn (Repeater $component): array => $admin() ? [] : self::itemAwal($component, auth()->id()))
                            ->minItems(1)
                            ->validationMessages(['min' => 'Catat minimal satu rumah.'])
                            ->addActionLabel('Tambah rumah')
                            ->reorderable(false)
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->columnSpanFull()
                            ->schema([
                                Select::make('pelanggan_id')
                                    ->label('Pelanggan')
                                    // $record here is the row; the round's petugas only narrows a petugas's own options.
                                    ->options(fn (Get $get, ?PengangkutanRincian $record): array => self::opsiPelanggan(
                                        self::petugasEfektif($get('../../petugas_id'), null),
                                        $record?->pelanggan_id,
                                    ))
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                ToggleButtons::make('status')
                                    ->options(StatusPengangkutan::class)
                                    ->default(StatusPengangkutan::Diangkut)
                                    ->inline()
                                    ->required()
                                    ->live()
                                    ->columnSpan(['default' => 1, 'sm' => 2]),
                                TextInput::make('perkiraan_kg')
                                    ->label('Perkiraan berat')
                                    ->numeric()
                                    ->step(0.1)
                                    ->minValue(0.1)
                                    ->maxValue(500)
                                    ->suffix('kg')
                                    ->visible(fn (Get $get): bool => self::status($get('status'))?->butuhBerat() ?? false)
                                    ->required(fn (Get $get): bool => self::status($get('status'))?->butuhBerat() ?? false),
                                TextInput::make('catatan')
                                    ->maxLength(255)
                                    ->columnSpan(['default' => 1, 'lg' => 3]),
                            ]),
                        Textarea::make('catatan')
                            ->label('Catatan hari ini')
                            ->rows(2)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $admin = fn (): bool => (bool) auth()->user()?->isAdminKsm();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['rincian', 'rincian as rumah_diangkut' => fn (Builder $rincian) => $rincian->where('status', StatusPengangkutan::Diangkut)])
                ->withSum('rincian as total_kg', 'perkiraan_kg'))
            ->defaultSort('tanggal', 'desc')
            ->columns([
                TextColumn::make('tanggal')
                    ->date('D, d M Y')
                    ->sortable(),
                TextColumn::make('petugas.name')
                    ->label('Petugas')
                    ->visible($admin)
                    ->searchable(),
                TextColumn::make('rumah_diangkut')
                    ->label('Rumah diangkut')
                    ->description(fn (Pengangkutan $record): string => "dari {$record->rincian_count} dikunjungi")
                    ->numeric(),
                TextColumn::make('total_kg')
                    ->label('Perkiraan berat')
                    ->numeric(decimalPlaces: 1, locale: 'id')
                    ->suffix(' kg')
                    ->placeholder('—')
                    ->summarize(Sum::make('total')->label('Total')->numeric(decimalPlaces: 1, locale: 'id')->suffix(' kg')),
                TextColumn::make('catatan')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('petugas_id')
                    ->label('Petugas')
                    ->options(fn (): array => PilihanPengguna::petugas())
                    ->visible($admin),
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
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /** The petugas a round is for: the saved one, else the admin's choice, else the petugas themself. */
    public static function petugasEfektif(mixed $dipilih, ?Pengangkutan $record): ?int
    {
        if ($record?->petugas_id !== null) {
            return $record->petugas_id;
        }

        if (auth()->user()?->isAdminKsm()) {
            return filled($dipilih) ? (int) $dipilih : null;
        }

        return auth()->id();
    }

    /**
     * Houses that can be put on a round: a petugas only their own active customers, an admin
     * every active customer in the KSM — plus the value already saved on the row, so an edit
     * after a customer was reassigned or deactivated still passes the Select's option check.
     *
     * @return array<int, string>
     */
    public static function opsiPelanggan(?int $petugasId, ?int $tersimpan = null): array
    {
        $admin = (bool) auth()->user()?->isAdminKsm();

        return Filament::getTenant()->pelanggan()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $boleh) => $boleh
                    ->where('aktif', true)
                    ->when(! $admin, fn (Builder $q) => $q->where('petugas_id', $petugasId ?? 0)))
                ->when($tersimpan, fn (Builder $q) => $q->orWhereKey($tersimpan)))
            ->orderBy('nomor_urut')
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Pelanggan $pelanggan): array => [
                $pelanggan->getKey() => $pelanggan->nama.(filled($pelanggan->alamat) ? " — {$pelanggan->alamat}" : ''),
            ])
            ->all();
    }

    /**
     * One "diangkut" row per active customer assigned to the petugas, ready for weights.
     *
     * @return array<string|int, array<string, mixed>>
     */
    private static function itemAwal(Repeater $repeater, ?int $petugasId): array
    {
        if ($petugasId === null) {
            return [];
        }

        $items = [];

        $pelanggan = Filament::getTenant()->pelanggan()
            ->where('aktif', true)
            ->where('petugas_id', $petugasId)
            ->orderBy('nomor_urut')
            ->orderBy('nama')
            ->pluck('id');

        foreach ($pelanggan as $pelangganId) {
            $item = ['pelanggan_id' => $pelangganId, 'status' => StatusPengangkutan::Diangkut->value, 'perkiraan_kg' => null, 'catatan' => null];

            if ($uuid = $repeater->generateUuid()) {
                $items[$uuid] = $item;
            } else {
                $items[] = $item;
            }
        }

        return $items;
    }

    private static function status(mixed $state): ?StatusPengangkutan
    {
        return $state instanceof StatusPengangkutan ? $state : StatusPengangkutan::tryFrom((string) $state);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPengangkutans::route('/'),
            'create' => CreatePengangkutan::route('/create'),
            'edit' => EditPengangkutan::route('/{record}/edit'),
        ];
    }
}
