<?php

namespace App\Filament\Shared;

use App\Models\Pelanggan;
use App\Support\BulanIuran;
use App\Support\Hari;
use App\Support\RingkasanIuran;
use App\Support\TerimaIuran;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * "Terima iuran": a petugas records fee cash taken at one of their own active houses —
 * several months at once, amount per month editable. Two entry points, one form and one
 * rule set: per house in "Tugas hari ini", and on "Iuran diterima" (pick the house first).
 */
final class TerimaIuranAction
{
    /** Per house: the table row is the customer. */
    public static function untukBaris(): Action
    {
        return self::dasar()
            ->authorize(fn (Pelanggan $record): bool => (bool) auth()->user()?->can('terimaIuran', $record))
            ->modalHeading(fn (Pelanggan $record): string => "Terima iuran — {$record->nama}")
            ->schema(self::komponen(fn (Get $get, ?Pelanggan $record): ?Pelanggan => $record))
            ->action(fn (array $data, Pelanggan $record, Action $action) => self::simpan($record, $data, $action));
    }

    /** On "Iuran diterima": choose one of your own active houses first. */
    public static function denganPilihanPelanggan(): Action
    {
        return self::dasar()
            ->authorize(fn (): bool => (bool) auth()->user()?->isPetugas())
            ->modalHeading('Terima iuran')
            ->schema([
                Select::make('pelanggan_id')
                    ->label('Pelanggan')
                    // Only own active houses: the Select's "in" rule rejects any other id.
                    ->options(fn (): array => self::rumahSendiri()->pluck('nama', 'id')->all())
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $pelanggan = self::pelangganDipilih($state);
                        $set('bulan', $pelanggan ? self::bulanAwal($pelanggan) : []);
                        $set('nominal', $pelanggan?->iuran_bulanan);
                    }),
                ...self::komponen(fn (Get $get, ?Pelanggan $record): ?Pelanggan => self::pelangganDipilih($get('pelanggan_id'))),
            ])
            ->action(function (array $data, Action $action): void {
                $pelanggan = self::pelangganDipilih($data['pelanggan_id'] ?? null);

                if ($pelanggan === null || ! auth()->user()->can('terimaIuran', $pelanggan)) {
                    $action->halt();
                }

                self::simpan($pelanggan, $data, $action);
            });
    }

    private static function dasar(): Action
    {
        return Action::make('terimaIuran')
            ->label('Terima iuran')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->modalSubmitActionLabel('Simpan');
    }

    /**
     * @param  Closure(Get, ?Pelanggan): ?Pelanggan  $pelanggan  resolves the customer from the action context
     * @return array<int, mixed>
     */
    private static function komponen(Closure $pelanggan): array
    {
        return [
            CheckboxList::make('bulan')
                ->label('Bulan yang dibayar')
                // Options = the allowed, still unpaid months: Filament's "in" rule rejects any other.
                ->options(fn (Get $get, ?Pelanggan $record): array => ($p = self::panggil($pelanggan, $get, $record)) ? BulanIuran::pilihan($p) : [])
                ->default(fn (Get $get, ?Pelanggan $record): array => ($p = self::panggil($pelanggan, $get, $record)) ? self::bulanAwal($p) : [])
                ->required()
                ->minItems(1)
                ->columns(3)
                ->bulkToggleable()
                ->live(),
            TextInput::make('nominal')
                ->label('Nominal per bulan')
                ->numeric()
                ->integer()
                ->prefix('Rp')
                ->minValue(1)
                ->maxValue(10_000_000)
                ->default(fn (Get $get, ?Pelanggan $record): ?int => self::panggil($pelanggan, $get, $record)?->iuran_bulanan)
                ->required()
                ->live(onBlur: true),
            DatePicker::make('dibayar_pada')
                ->label('Tanggal diterima')
                ->native(false)
                ->default(fn (): string => Hari::tanggal())
                ->maxDate(fn (): string => Hari::tanggal())
                ->minDate(fn (): string => Hari::tanggal(1))
                ->required(),
            TextInput::make('catatan')
                ->maxLength(255),
            TextEntry::make('total')
                ->label('Total diterima')
                ->state(fn (Get $get): string => RingkasanIuran::rupiah(count($get('bulan') ?? []) * (int) $get('nominal'))),
        ];
    }

    private static function panggil(Closure $pelanggan, Get $get, ?Pelanggan $record): ?Pelanggan
    {
        return $pelanggan($get, $record);
    }

    /** This month, when it is still unpaid. @return list<string> */
    private static function bulanAwal(Pelanggan $pelanggan): array
    {
        $ini = BulanIuran::iniBulan()->toDateString();

        return array_key_exists($ini, BulanIuran::pilihan($pelanggan)) ? [$ini] : [];
    }

    /** @return Builder<Pelanggan> */
    private static function rumahSendiri(): Builder
    {
        return Pelanggan::query()
            ->whereBelongsTo(Filament::getTenant())
            ->where('aktif', true)
            ->where('petugas_id', auth()->id())
            ->orderBy('nomor_urut')
            ->orderBy('nama');
    }

    private static function pelangganDipilih(mixed $id): ?Pelanggan
    {
        return filled($id) ? self::rumahSendiri()->find($id) : null;
    }

    /** @param  array<string, mixed>  $data */
    private static function simpan(Pelanggan $pelanggan, array $data, Action $action): void
    {
        try {
            $jumlah = TerimaIuran::catat($pelanggan, auth()->user(), array_values($data['bulan']), (int) $data['nominal'], (string) $data['dibayar_pada'], $data['catatan'] ?? null);
        } catch (UniqueConstraintViolationException) {
            Notification::make()->danger()->title('Sebagian bulan baru saja dicatat orang lain. Tutup dan buka lagi formnya.')->send();
            $action->halt();
        }

        Notification::make()
            ->success()
            ->title("{$jumlah} bulan dicatat untuk {$pelanggan->nama} — total ".RingkasanIuran::rupiah($jumlah * (int) $data['nominal']))
            ->send();
    }
}
