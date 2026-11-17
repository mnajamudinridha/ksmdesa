<?php

namespace App\Filament\Ksm\Widgets;

use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Warga: when their houses were collected, by whom, and roughly how much. */
class RiwayatPengangkutanSaya extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Riwayat pengangkutan';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isWarga();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PengangkutanRincian::query()
                ->whereHas('pelanggan', fn (Builder $pelanggan) => $pelanggan
                    ->whereBelongsTo(Filament::getTenant())
                    ->where('user_id', auth()->id()))
                ->with(['pelanggan', 'pengangkutan.petugas'])
                ->orderByDesc(Pengangkutan::query()->select('tanggal')->whereColumn('pengangkutan.id', 'pengangkutan_rincian.pengangkutan_id')))
            ->paginated([10, 25])
            ->emptyStateHeading('Belum ada pengangkutan tercatat')
            ->columns([
                TextColumn::make('pengangkutan.tanggal')
                    ->label('Tanggal')
                    ->date('D, d M Y'),
                TextColumn::make('pelanggan.nama')
                    ->label('Rumah'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('perkiraan_kg')
                    ->label('Perkiraan')
                    ->numeric(decimalPlaces: 1, locale: 'id')
                    ->suffix(' kg')
                    ->placeholder('—'),
                TextColumn::make('pengangkutan.petugas.name')
                    ->label('Petugas'),
            ]);
    }
}
