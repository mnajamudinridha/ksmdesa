<?php

namespace App\Filament\Ksm\Widgets;

use App\Enums\StatusPengangkutan;
use App\Filament\Ksm\Resources\Pengangkutans\PengangkutanResource;
use App\Models\Pengangkutan;
use App\Support\Periode;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Analitik › Operasional: the period's latest rounds, linking to the round itself. */
class PengangkutanTerakhirTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Kegiatan terakhir';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $periode = Periode::dariFilter($this->pageFilters);

                return Pengangkutan::query()
                    ->whereBelongsTo(Filament::getTenant())
                    ->whereDate('tanggal', '>=', $periode->dariString())
                    ->whereDate('tanggal', '<=', $periode->sampaiString())
                    ->with('petugas')
                    ->withCount(['rincian', 'rincian as diangkut_count' => fn (Builder $query) => $query->where('status', StatusPengangkutan::Diangkut)])
                    ->withSum('rincian as kg', 'perkiraan_kg');
            })
            ->defaultSort('tanggal', 'desc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Belum ada pengangkutan pada periode ini')
            ->recordUrl(fn (Pengangkutan $record): string => PengangkutanResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('tanggal')
                    ->date('D, d M Y')
                    ->sortable(),
                TextColumn::make('petugas.name')
                    ->label('Petugas'),
                TextColumn::make('rincian_count')
                    ->label('Rumah dicatat')
                    ->numeric(),
                TextColumn::make('diangkut_count')
                    ->label('Diangkut')
                    ->numeric(),
                TextColumn::make('kg')
                    ->label('Perkiraan')
                    ->numeric(decimalPlaces: 1, locale: 'id')
                    ->suffix(' kg')
                    ->placeholder('—'),
            ]);
    }
}
