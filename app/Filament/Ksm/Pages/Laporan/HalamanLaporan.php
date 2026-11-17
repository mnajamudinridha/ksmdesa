<?php

namespace App\Filament\Ksm\Pages\Laporan;

use App\Filament\Ksm\Pages\Concerns\PunyaFilterPeriode;
use App\Support\Hari;
use App\Support\Laporan\EksporXlsx;
use App\Support\Laporan\TabelLaporan;
use App\Support\Periode;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * A formal report: period filter, report tables (widgets), "Unduh Excel" and "Cetak / PDF".
 * The tables, the spreadsheet and the printout come from the same TabelLaporan objects, so
 * the three can never disagree. Each page sets $routePath and $slug to the same string.
 */
abstract class HalamanLaporan extends Dashboard
{
    use PunyaFilterPeriode;

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    /** Short name used in the downloaded file name: laporan-{jenis}-{ksm}-{periode}.xlsx */
    abstract protected static function jenisLaporan(): string;

    /** @return list<TabelLaporan> the report's sections, in page order */
    abstract protected function tabelLaporan(Periode $periode): array;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // Letterhead and signature lines appear only when printed.
            View::make('filament.laporan.kop')
                ->viewData(fn (): array => [
                    'ksm' => Filament::getTenant(),
                    'judul' => $this->getTitle(),
                    'periode' => $this->periode()->label(),
                    'dicetak' => Hari::ini()->translatedFormat('d F Y'),
                    'pencetak' => auth()->user()?->name,
                ]),
            $this->getFiltersFormContentComponent(),
            $this->getWidgetsContentComponent(),
            View::make('filament.laporan.ttd'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduhExcel')
                ->label('Unduh Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->authorize(fn (): bool => (bool) auth()->user()?->isAdminKsm())
                ->action(function (): StreamedResponse {
                    $periode = $this->periode();

                    return EksporXlsx::unduh(static::jenisLaporan(), (string) $this->getTitle(), Filament::getTenant(), $periode, $this->tabelLaporan($periode));
                }),
            Action::make('cetak')
                ->label('Cetak / PDF')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->authorize(fn (): bool => (bool) auth()->user()?->isAdminKsm())
                ->alpineClickHandler('window.print()'),
        ];
    }
}
