<?php

namespace App\Filament\Ksm\Widgets;

use App\Support\Hari;
use App\Support\Periode;
use App\Support\RingkasanIuran;
use App\Support\RingkasanKas;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/** Income, spending, surplus and cash balance — this month on the dashboard, the chosen period on Analitik › Keuangan. */
class KeuanganStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    /** Report figures change when someone records something, not every few seconds. */
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function getHeading(): ?string
    {
        return 'Keuangan · '.Periode::dariFilter($this->pageFilters)->label();
    }

    protected function getStats(): array
    {
        $periode = Periode::dariFilter($this->pageFilters);
        $kas = new RingkasanKas(Filament::getTenant());
        [$dari, $sampai] = [$periode->dariString(), $periode->sampaiString()];

        $iuran = $kas->iuran($dari, $sampai);
        $masuk = $kas->masuk($dari, $sampai);
        $keluar = $kas->keluar($dari, $sampai);
        $selisih = $masuk - $keluar;

        $hariIni = Hari::tanggal();
        $saldo = $kas->saldoAkhir(min($sampai, $hariIni));
        $memuatHariIni = $dari <= $hariIni && $hariIni <= $sampai;
        $diPetugas = $memuatHariIni ? $kas->diTanganPetugas() : 0;

        return [
            Stat::make('Pemasukan', RingkasanIuran::rupiah($masuk))
                ->description('iuran '.RingkasanIuran::rupiah($iuran).' · lainnya '.RingkasanIuran::rupiah($masuk - $iuran))
                ->descriptionIcon(Heroicon::ArrowDownCircle)
                ->color('success'),
            Stat::make('Pengeluaran', RingkasanIuran::rupiah($keluar))
                ->description('dari Buku Kas')
                ->descriptionIcon(Heroicon::ArrowUpCircle)
                ->color('danger'),
            Stat::make($selisih >= 0 ? 'Surplus' : 'Defisit', RingkasanIuran::rupiah(abs($selisih)))
                ->description('pemasukan − pengeluaran')
                ->color($selisih >= 0 ? 'success' : 'danger'),
            Stat::make('Saldo kas', $saldo === null ? '—' : RingkasanIuran::rupiah($saldo))
                ->description(match (true) {
                    $saldo === null => 'sebelum mulai pembukuan — atur di Profil KSM',
                    $diPetugas > 0 => 'termasuk '.RingkasanIuran::rupiah($diPetugas).' di tangan petugas',
                    default => 'per '.Carbon::parse(min($sampai, $hariIni))->translatedFormat('d M Y'),
                })
                ->descriptionIcon(Heroicon::Wallet)
                ->color($saldo !== null && $saldo < 0 ? 'danger' : 'primary'),
        ];
    }
}
