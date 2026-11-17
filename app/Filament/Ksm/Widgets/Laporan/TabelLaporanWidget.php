<?php

namespace App\Filament\Ksm\Widgets\Laporan;

use App\Support\Laporan\KolomLaporan;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/**
 * Renders one report section (a TabelLaporan) as a Filament custom-data table. Rows are
 * computed when the table renders — after the page's reactive filters have arrived — never
 * while the table is being configured. Custom data skips Eloquent scopes and policies, so the
 * section builders limit every query to the KSM themselves.
 */
abstract class TabelLaporanWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    /** Rendered with the page, so "Cetak / PDF" never prints placeholders of tables still loading. */
    protected static bool $isLazy = false;

    /**
     * One request only (not a public Livewire property); keyed by period so a filter change
     * within the same request never shows a stale table.
     *
     * @var array<string, TabelLaporan>
     */
    private array $cacheTabel = [];

    /** @return list<KolomLaporan> the same definition the section builder uses */
    abstract protected static function kolom(): array;

    abstract protected function buatTabel(Periode $periode): TabelLaporan;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function tabelTerkini(): TabelLaporan
    {
        $periode = Periode::dariFilter($this->pageFilters);

        return $this->cacheTabel[$periode->kunci()] ??= $this->buatTabel($periode);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(fn (): string => $this->tabelTerkini()->judul)
            ->description(fn (): ?string => $this->tabelTerkini()->catatan)
            ->records(fn (): array => $this->tabelTerkini()->rekamanTabel())
            ->columns(array_map(
                fn (KolomLaporan $kolom): TextColumn => TextColumn::make($kolom->kunci)
                    ->label($kolom->label)
                    ->formatStateUsing(fn (mixed $state, array $record): string => $kolom->formatUntuk($record)->tampil($state))
                    ->placeholder('—')
                    ->alignEnd($kolom->format->rataKanan())
                    ->weight(fn (array $record): ?FontWeight => ($record['__total'] ?? false) ? FontWeight::Bold : null),
                static::kolom(),
            ))
            ->paginated(false)
            ->emptyStateHeading('Belum ada data pada periode ini');
    }
}
