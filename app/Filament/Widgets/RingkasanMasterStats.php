<?php

namespace App\Filament\Widgets;

use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Support\Hari;
use App\Support\RingkasanIuran;
use App\Support\RingkasanPengangkutan;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Master dashboard: all KSMs together. The master panel has no tenant, so these queries
 * deliberately span every KSM.
 */
class RingkasanMasterStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    protected function getStats(): array
    {
        $bulan = Hari::ini()->startOfMonth();
        $aktifMencatat = Ksm::query()->aktifMencatat(Ksm::batasAktifMencatat())->count();
        $iuran = (int) PembayaranIuran::query()->whereDate('periode', $bulan->toDateString())->sum('nominal');
        $target = (int) Pelanggan::query()->where('aktif', true)->sum('iuran_bulanan');
        $diPetugas = (int) PembayaranIuran::query()->belumDisetor()->sum('nominal');
        $kg = (float) PengangkutanRincian::query()
            ->whereIn('pengangkutan_id', Pengangkutan::query()->whereDate('tanggal', '>=', Hari::tanggal(29))->select('id'))
            ->sum('perkiraan_kg');

        return [
            Stat::make('KSM terdaftar', Ksm::query()->count())
                ->description("{$aktifMencatat} aktif mencatat ".Ksm::HARI_AKTIF_MENCATAT.' hari terakhir')
                ->descriptionIcon(Heroicon::BuildingOffice2)
                ->color('primary'),
            Stat::make('Pelanggan aktif', Pelanggan::query()->where('aktif', true)->count())
                ->description('dari '.Pelanggan::query()->count().' terdaftar')
                ->descriptionIcon(Heroicon::HomeModern)
                ->color('primary'),
            Stat::make('Iuran '.$bulan->translatedFormat('F Y'), RingkasanIuran::rupiah($iuran))
                ->description('target '.RingkasanIuran::rupiah($target).($target > 0 ? ' · '.round($iuran / $target * 100).'%' : ''))
                ->descriptionIcon(Heroicon::Banknotes)
                ->color($target > 0 && $iuran >= $target ? 'success' : 'warning'),
            Stat::make('Di tangan petugas', RingkasanIuran::rupiah($diPetugas))
                ->description($diPetugas > 0 ? 'belum disetor ke pengurus KSM' : 'semua iuran sudah disetor')
                ->descriptionIcon(Heroicon::Wallet)
                ->color($diPetugas > 0 ? 'warning' : 'success'),
            Stat::make('Sampah 30 hari', RingkasanPengangkutan::kg($kg))
                ->description('perkiraan dari catatan petugas')
                ->descriptionIcon(Heroicon::Scale)
                ->color('success'),
        ];
    }
}
