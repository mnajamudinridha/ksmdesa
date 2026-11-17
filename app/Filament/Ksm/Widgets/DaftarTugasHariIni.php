<?php

namespace App\Filament\Ksm\Widgets;

use App\Enums\StatusIuran;
use App\Enums\StatusPengangkutan;
use App\Filament\Ksm\Resources\Pengangkutans\PengangkutanResource;
use App\Filament\Shared\TerimaIuranAction;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Support\BulanIuran;
use App\Support\Hari;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Petugas: today's houses in route order, with what has been recorded for each so far, this
 * month's fee status, and "Terima iuran" to record cash taken at the door.
 */
class DaftarTugasHariIni extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Tugas hari ini';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isPetugas();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Pelanggan::query()
                ->whereBelongsTo(Filament::getTenant())
                ->where('petugas_id', auth()->id())
                ->where('aktif', true)
                ->with(['ksm', 'akunWarga'])
                ->addSelect(['dibayar_bulan_ini' => PembayaranIuran::query()
                    ->selectRaw('sum(nominal)')
                    ->whereColumn('pembayaran_iuran.pelanggan_id', 'pelanggan.id')
                    ->whereDate('periode', BulanIuran::iniBulan()->toDateString())])
                ->addSelect(['status_hari_ini' => PengangkutanRincian::query()
                    ->select('status')
                    ->whereColumn('pengangkutan_rincian.pelanggan_id', 'pelanggan.id')
                    ->whereIn('pengangkutan_id', $this->rondeHariIniQuery()->select('id'))
                    ->limit(1)])
                ->orderBy('nomor_urut')
                ->orderBy('nama'))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Belum ada rumah yang ditugaskan')
            ->emptyStateDescription('Admin KSM menetapkan petugas di menu Pelanggan.')
            ->columns([
                TextColumn::make('nomor_urut')
                    ->label('No')
                    ->placeholder('—'),
                TextColumn::make('nama')
                    ->description(fn (Pelanggan $record): ?string => $record->patokan)
                    ->searchable(),
                TextColumn::make('alamat')
                    ->description(fn (Pelanggan $record): ?string => filled($record->rt) ? "RT {$record->rt}".(filled($record->rw) ? " / RW {$record->rw}" : '') : null)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('akunWarga.no_hp')
                    ->label('HP')
                    ->url(fn (Pelanggan $record): ?string => filled($record->akunWarga?->no_hp) ? "tel:{$record->akunWarga->no_hp}" : null)
                    ->placeholder('—'),
                TextColumn::make('status_hari_ini')
                    ->label('Hari ini')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): ?string => StatusPengangkutan::tryFrom((string) $state)?->getLabel())
                    ->color(fn (?string $state): string => StatusPengangkutan::tryFrom((string) $state)?->getColor() ?? 'gray')
                    ->placeholder('Belum dicatat'),
                TextColumn::make('status_iuran')
                    ->label('Iuran '.BulanIuran::iniBulan()->translatedFormat('F'))
                    ->state(fn (Pelanggan $record): StatusIuran => StatusIuran::tentukan(
                        $record->aktif,
                        $record->iuran_bulanan,
                        $record->dibayar_bulan_ini !== null ? (int) $record->dibayar_bulan_ini : null,
                    ))
                    ->badge(),
                TextColumn::make('catatan_pengangkutan')
                    ->label('Catatan')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
            ])
            ->headerActions([
                Action::make('catatHariIni')
                    ->label(fn (): string => $this->rondeHariIni() ? 'Lanjutkan catatan hari ini' : 'Catat pengangkutan hari ini')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (): string => ($ronde = $this->rondeHariIni())
                        ? PengangkutanResource::getUrl('edit', ['record' => $ronde])
                        : PengangkutanResource::getUrl('create')),
            ])
            ->recordActions([
                TerimaIuranAction::untukBaris(),
                Action::make('bukaPeta')
                    ->label('Peta')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->color('gray')
                    ->url(fn (Pelanggan $record): ?string => $record->mapsUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (Pelanggan $record): bool => $record->mapsUrl() !== null),
            ]);
    }

    /** @return Builder<Pengangkutan> */
    private function rondeHariIniQuery(): Builder
    {
        return Pengangkutan::query()
            ->whereBelongsTo(Filament::getTenant())
            ->where('petugas_id', auth()->id())
            ->whereDate('tanggal', Hari::tanggal());
    }

    private function rondeHariIni(): ?Pengangkutan
    {
        return $this->rondeHariIniQuery()->first();
    }
}
