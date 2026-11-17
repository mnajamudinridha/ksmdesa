<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Ksms\KsmResource;
use App\Models\Ksm;
use App\Models\PengangkutanRincian;
use App\Support\Hari;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Master dashboard: one row per KSM — customers, this month's fees, tonnage, cash still with
 * petugas, and whether the KSM is actually using the app.
 */
class RingkasanPerKsmTable extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Per KSM';

    /** @var list<int>|null */
    private ?array $ksmAktif = null;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public function table(Table $table): Table
    {
        $bulan = Hari::ini()->startOfMonth();

        return $table
            ->query(fn (): Builder => Ksm::query()
                ->withCount(['pelanggan as pelanggan_aktif_count' => fn (Builder $query) => $query->where('aktif', true)])
                ->withSum(['pembayaranIuran as iuran_bulan_ini' => fn (Builder $query) => $query->whereDate('periode', $bulan->toDateString())], 'nominal')
                ->withSum(['pelanggan as target' => fn (Builder $query) => $query->where('aktif', true)], 'iuran_bulanan')
                ->withSum(['pembayaranIuran as di_tangan_petugas' => fn (Builder $query) => $query->belumDisetor()], 'nominal')
                ->addSelect(['kg_30_hari' => PengangkutanRincian::query()
                    ->selectRaw('coalesce(sum(pengangkutan_rincian.perkiraan_kg), 0)')
                    ->join('pengangkutan', 'pengangkutan.id', '=', 'pengangkutan_rincian.pengangkutan_id')
                    ->whereColumn('pengangkutan.ksm_id', 'ksm.id')
                    ->whereDate('pengangkutan.tanggal', '>=', Hari::tanggal(29))])
                ->withMax('pengangkutan', 'created_at')
                ->withMax('pembayaranIuran', 'created_at')
                ->withMax('setoran', 'created_at')
                ->withMax('transaksiKas', 'created_at'))
            ->defaultSort('nama')
            ->paginated(false)
            ->columns([
                TextColumn::make('nama')
                    ->description(fn (Ksm $record): ?string => filled($record->desa) ? "Desa {$record->desa}" : null)
                    ->url(fn (Ksm $record): string => $record->url(), shouldOpenInNewTab: true)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pelanggan_aktif_count')
                    ->label('Pelanggan aktif')
                    ->numeric(),
                TextColumn::make('iuran_bulan_ini')
                    ->label('Iuran '.$bulan->translatedFormat('F'))
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->default(0)
                    ->description(fn (Ksm $record): ?string => $record->target > 0 ? round($record->iuran_bulan_ini / $record->target * 100).'% target' : null),
                TextColumn::make('kg_30_hari')
                    ->label('Sampah 30 hari')
                    ->numeric(decimalPlaces: 1, locale: 'id')
                    ->suffix(' kg'),
                TextColumn::make('di_tangan_petugas')
                    ->label('Di tangan petugas')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->default(0)
                    ->color(fn ($state): ?string => $state > 0 ? 'warning' : null),
                TextColumn::make('catatan_terakhir')
                    ->label('Catatan terakhir')
                    ->state(fn (Ksm $record): ?string => collect([
                        $record->pengangkutan_max_created_at,
                        $record->pembayaran_iuran_max_created_at,
                        $record->setoran_max_created_at,
                        $record->transaksi_kas_max_created_at,
                    ])->filter()->max())
                    ->dateTime('d M Y H:i', timezone: (string) config('app.zona_waktu_lokal'))
                    ->placeholder('belum pernah'),
                TextColumn::make('status')
                    ->label('Status')
                    ->state(fn (Ksm $record): string => in_array($record->getKey(), $this->ksmAktif(), true) ? 'Aktif mencatat' : 'Belum aktif')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Aktif mencatat' ? 'success' : 'danger'),
            ])
            ->filters([
                Filter::make('belum_aktif')
                    ->label('Belum aktif mencatat ('.Ksm::HARI_AKTIF_MENCATAT.' hari)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNot(fn (Builder $tidak) => $tidak->aktifMencatat(Ksm::batasAktifMencatat()))),
            ])
            ->recordActions([
                Action::make('ubah')
                    ->label('Ubah')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Ksm $record): string => KsmResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    /**
     * Ids of KSMs that recorded something recently — the same scope as the stat and the filter.
     *
     * @return list<int>
     */
    private function ksmAktif(): array
    {
        return $this->ksmAktif ??= Ksm::query()->aktifMencatat(Ksm::batasAktifMencatat())->pluck('id')->all();
    }
}
