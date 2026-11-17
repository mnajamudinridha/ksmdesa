<?php

namespace App\Filament\Ksm\Widgets;

use App\Filament\Ksm\Resources\CatatanMaterials\CatatanMaterialResource;
use App\Models\CatatanMaterial;
use App\Support\Periode;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Analitik › Lingkungan: the period's latest material records. */
class CatatanMaterialTerakhirTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Kegiatan material terakhir';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $periode = Periode::dariFilter($this->pageFilters);

                return CatatanMaterial::query()
                    ->whereBelongsTo(Filament::getTenant())
                    ->whereDate('tanggal', '>=', $periode->dariString())
                    ->whereDate('tanggal', '<=', $periode->sampaiString())
                    ->with(['jenisMaterial', 'pengepul']);
            })
            ->defaultSort('tanggal', 'desc')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Belum ada catatan material pada periode ini')
            ->recordUrl(fn (): string => CatatanMaterialResource::getUrl('index'))
            ->columns([
                TextColumn::make('tanggal')
                    ->date('D, d M Y'),
                TextColumn::make('aktivitas')
                    ->badge(),
                TextColumn::make('jenisMaterial.nama')
                    ->label('Jenis')
                    ->placeholder('Tanpa jenis'),
                TextColumn::make('berat_kg')
                    ->label('Berat')
                    ->numeric(decimalPlaces: 1, locale: 'id')
                    ->suffix(' kg'),
                TextColumn::make('pengepul.nama')
                    ->label('Pengepul')
                    ->placeholder('—'),
                TextColumn::make('nilai_jual')
                    ->label('Nilai')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->placeholder('—'),
            ]);
    }
}
