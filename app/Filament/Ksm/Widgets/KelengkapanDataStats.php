<?php

namespace App\Filament\Ksm\Widgets;

use App\Filament\Ksm\Resources\Pelanggans\PelangganResource;
use App\Models\Pelanggan;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/** Admin dashboard: what is still missing on active customers (RT, phone, map location). */
class KelengkapanDataStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Kelengkapan data pelanggan aktif';

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

        return [
            Stat::make('Tanpa RT/RW', $tanpaRt)
                ->description($tanpaRt > 0 ? 'tetapkan di menu Pelanggan (filter "Tanpa RT")' : 'semua sudah ber-RT')
                ->descriptionIcon(Heroicon::MapPin)
                ->color($tanpaRt > 0 ? 'warning' : 'success')
                ->url(PelangganResource::getUrl('index')),
            Stat::make('Tanpa nomor HP', $tanpaHp)
                ->description($tanpaHp > 0 ? 'warga belum bisa login dengan HP' : 'semua punya nomor HP')
                ->descriptionIcon(Heroicon::Phone)
                ->color($tanpaHp > 0 ? 'warning' : 'success'),
            Stat::make('Tanpa lokasi', $tanpaLokasi)
                ->description($tanpaLokasi > 0 ? 'belum ada alamat maupun titik peta' : 'semua punya alamat/titik peta')
                ->descriptionIcon(Heroicon::Map)
                ->color($tanpaLokasi > 0 ? 'gray' : 'success'),
        ];
    }

    /** @return Builder<Pelanggan> */
    private function pelangganAktif(): Builder
    {
        return Pelanggan::query()->whereBelongsTo(Filament::getTenant())->where('aktif', true);
    }
}
