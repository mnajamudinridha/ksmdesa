<?php

namespace App\Filament\Ksm\Widgets;

use App\Models\PembayaranIuran;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Warga: fee payments recorded for their own houses. */
class RiwayatIuranSaya extends TableWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Riwayat iuran';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isWarga();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PembayaranIuran::query()
                ->whereBelongsTo(Filament::getTenant())
                ->whereHas('pelanggan', fn (Builder $pelanggan) => $pelanggan->where('user_id', auth()->id()))
                ->with('pelanggan')
                ->orderByDesc('periode'))
            ->paginated([6, 12, 24])
            ->emptyStateHeading('Belum ada pembayaran tercatat')
            ->columns([
                TextColumn::make('periode')
                    ->label('Bulan')
                    ->date('F Y'),
                TextColumn::make('pelanggan.nama')
                    ->label('Rumah'),
                TextColumn::make('nominal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0),
                TextColumn::make('dibayar_pada')
                    ->label('Diterima')
                    ->date('d M Y')
                    ->placeholder('—'),
            ]);
    }
}
