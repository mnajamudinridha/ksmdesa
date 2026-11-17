<?php

namespace App\Filament\Ksm\Pages\Laporan;

use App\Filament\Ksm\Widgets\Laporan\TabelArusKas;
use App\Filament\Ksm\Widgets\Laporan\TabelPemasukan;
use App\Filament\Ksm\Widgets\Laporan\TabelPengeluaran;
use App\Filament\Ksm\Widgets\Laporan\TabelRincianKas;
use App\Support\Laporan\LaporanKeuangan;
use App\Support\Periode;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

class LaporanKeuanganPage extends HalamanLaporan
{
    protected static string $routePath = 'laporan/keuangan';

    protected static ?string $slug = 'laporan/keuangan';

    protected static ?string $title = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Keuangan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static ?int $navigationSort = 15;

    protected static function jenisLaporan(): string
    {
        return 'keuangan';
    }

    protected function tabelLaporan(Periode $periode): array
    {
        return (new LaporanKeuangan(Filament::getTenant()))->semua($periode);
    }

    public function getWidgets(): array
    {
        return [
            TabelArusKas::class,
            TabelPemasukan::class,
            TabelPengeluaran::class,
            TabelRincianKas::class,
        ];
    }
}
