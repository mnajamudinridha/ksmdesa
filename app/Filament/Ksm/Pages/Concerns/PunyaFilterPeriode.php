<?php

namespace App\Filament\Ksm\Pages\Concerns;

use App\Support\Hari;
use App\Support\Periode;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Year + month (or the whole year) filter shared by the analytics and report pages. The raw
 * values are never trusted: everything reads the period through Periode::dariFilter().
 */
trait PunyaFilterPeriode
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->columnSpanFull()
                ->extraAttributes(['class' => 'ksm-filter-periode'])
                ->schema([
                    Select::make('tahun')
                        ->label('Tahun')
                        ->options(fn (): array => Periode::pilihanTahun())
                        ->default((string) Hari::ini()->year)
                        ->selectablePlaceholder(false),
                    Select::make('bulan')
                        ->label('Bulan')
                        ->options(fn (): array => Periode::pilihanBulan())
                        ->default((string) Hari::ini()->month)
                        ->selectablePlaceholder(false),
                ]),
        ]);
    }

    public function periode(): Periode
    {
        return Periode::dariFilter($this->filters);
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Periode: '.$this->periode()->label();
    }
}
