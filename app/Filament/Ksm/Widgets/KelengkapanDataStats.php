<?php

namespace App\Filament\Ksm\Widgets;

use App\Filament\Ksm\Resources\Pelanggans\PelangganResource;
use App\Filament\Ksm\Resources\TransaksiKas\TransaksiKasResource;
use App\Models\Pelanggan;
use App\Models\TransaksiKas;
use App\Support\Periode;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/** Admin dashboard: what is still missing — on active customers (RT, phone, map location) and on this month's cash-book entries (receipts). */
class KelengkapanDataStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Kelengkapan data';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdminKsm();
    }

    protected function getStats(): array
    {
        $tanpaRt = $this->pelangganAktif()->whereNull('wilayah_id')->count();
        $tanpaHp = $this->pelangganAktif()
            ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhereHas('akunWarga', fn (Builder $akun) => $akun->whereNull('no_hp')))
            ->count();
        $tanpaLokasi = $this->pelangganAktif()->whereNull('latitude')->whereNull('alamat')->count();
        $bulanIni = Periode::bulanIni();
        $tanpaBukti = TransaksiKas::query()
            ->whereBelongsTo(Filament::getTenant())
            ->whereDate('tanggal', '>=', $bulanIni->dariString())
            ->whereDate('tanggal', '<=', $bulanIni->sampaiString())
            ->whereNull('bukti')
            ->count();

        return [
            Stat::make('Tanpa RT/RW', $tanpaRt)
                ->description($tanpaRt > 0 ? 'pelanggan aktif — tetapkan di menu Pelanggan (filter "Tanpa RT")' : 'semua pelanggan aktif sudah ber-RT')
                ->descriptionIcon(Heroicon::MapPin)
                ->color($tanpaRt > 0 ? 'warning' : 'success')
                ->url(PelangganResource::getUrl('index')),
            Stat::make('Tanpa nomor HP', $tanpaHp)
                ->description($tanpaHp > 0 ? 'pelanggan aktif — warga belum bisa login dengan HP' : 'semua pelanggan aktif punya nomor HP')
                ->descriptionIcon(Heroicon::Phone)
                ->color($tanpaHp > 0 ? 'warning' : 'success'),
            Stat::make('Tanpa lokasi', $tanpaLokasi)
                ->description($tanpaLokasi > 0 ? 'pelanggan aktif tanpa alamat maupun titik peta' : 'semua pelanggan aktif punya alamat/titik peta')
                ->descriptionIcon(Heroicon::Map)
                ->color($tanpaLokasi > 0 ? 'gray' : 'success'),
            Stat::make('Transaksi tanpa bukti', $tanpaBukti)
                ->description($tanpaBukti > 0 ? 'Buku Kas bulan ini — unggah foto/PDF bukti' : 'semua transaksi bulan ini berbukti')
                ->descriptionIcon(Heroicon::PaperClip)
                ->color($tanpaBukti > 0 ? 'warning' : 'success')
                ->url(TransaksiKasResource::getUrl('index')),
        ];
    }

    /** @return Builder<Pelanggan> */
    private function pelangganAktif(): Builder
    {
        return Pelanggan::query()->whereBelongsTo(Filament::getTenant())->where('aktif', true);
    }
}
