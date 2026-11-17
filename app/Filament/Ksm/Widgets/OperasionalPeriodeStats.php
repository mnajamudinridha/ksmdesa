<?php

namespace App\Filament\Ksm\Widgets;

use App\Enums\FormatAngka;
use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\Indikator;
use App\Support\Periode;
use App\Support\RingkasanPengangkutan;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Analitik › Operasional: RT and household coverage, collection days, petugas, tonnage for the period. */
class OperasionalPeriodeStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function getHeading(): ?string
    {
        return 'Operasional · '.Periode::dariFilter($this->pageFilters)->label();
    }

    protected function getStats(): array
    {
        $periode = Periode::dariFilter($this->pageFilters);
        $ksm = Filament::getTenant();
        $ringkasan = new RingkasanPengangkutan($ksm);
        $baris = $ringkasan->perWilayah($periode);

        [$rtTerlayani, $jumlahRt] = $ringkasan->rtTerlayani($baris);
        [$terlayani, $aktif] = $ringkasan->terlayaniDanAktif($baris);
        $cakupan = RingkasanPengangkutan::bagi($terlayani, $aktif);
        $kunjunganAktif = array_sum(array_column(array_filter($baris, fn (array $satu): bool => $satu['jenis'] !== 'nonaktif'), 'kunjungan'));
        $rataMingguan = RingkasanPengangkutan::rataMingguan($kunjunganAktif, $aktif, $periode->jumlahHari() / 7);

        $petugasAktif = User::query()->whereBelongsTo($ksm)->where('peran', PeranPengguna::Petugas)->where('aktif', true)->count();
        $bertugas = count(array_filter($ringkasan->perPetugas($periode), fn (array $satu): bool => $satu['hari_bertugas'] > 0));

        return [
            Stat::make('RT terlayani', "{$rtTerlayani} dari {$jumlahRt} RT")
                ->description($jumlahRt === 0 ? 'isi daftar RT di Data Master → Wilayah' : 'RT dengan ≥1 rumah diangkut')
                ->descriptionIcon(Heroicon::Map)
                ->color('primary'),
            Stat::make('Cakupan layanan', FormatAngka::Persen->tampil($cakupan))
                ->description("{$terlayani} dari {$aktif} rumah aktif diangkut ≥1×")
                ->descriptionIcon(Heroicon::Home)
                ->color(Indikator::warna($cakupan)),
            Stat::make('Hari pengangkutan', $ringkasan->hariPengangkutan($periode))
                ->description('rata-rata '.FormatAngka::Angka->tampil($rataMingguan).' angkut/rumah/minggu')
                ->descriptionIcon(Heroicon::CalendarDays)
                ->color('primary'),
            Stat::make('Petugas aktif', $petugasAktif)
                ->description("{$bertugas} bertugas di periode ini")
                ->descriptionIcon(Heroicon::UserGroup)
                ->color('gray'),
            Stat::make('Perkiraan sampah', RingkasanPengangkutan::kg($ringkasan->berat($periode->dariString(), $periode->sampaiString())))
                ->description('dari catatan petugas')
                ->descriptionIcon(Heroicon::Scale)
                ->color('success'),
        ];
    }
}
