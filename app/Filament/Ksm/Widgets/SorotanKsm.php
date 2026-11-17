<?php

namespace App\Filament\Ksm\Widgets;

use App\Filament\Ksm\Resources\Pengangkutans\PengangkutanResource;
use App\Filament\Ksm\Resources\Users\UserResource;
use App\Filament\Shared\SorotanWidget;
use App\Models\Ksm;
use App\Models\User;
use App\Support\Hari;
use App\Support\RingkasanPengangkutan;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

/**
 * Village dashboard, beside "Selamat datang", one line per role: admin — active petugas with no
 * collection recorded today; petugas — their last collection round; warga — the last pickup at
 * their houses.
 */
class SorotanKsm extends SorotanWidget
{
    public static function canView(): bool
    {
        $pengguna = auth()->user();

        return (bool) ($pengguna?->isAdminKsm() || $pengguna?->isPetugas() || $pengguna?->isWarga());
    }

    protected function sorotan(): array
    {
        /** @var User $pengguna */
        $pengguna = auth()->user();
        $ksm = Filament::getTenant();

        return match (true) {
            $pengguna->isAdminKsm() => $this->untukAdmin($ksm),
            $pengguna->isPetugas() => $this->untukPetugas($ksm, $pengguna),
            default => $this->untukWarga($ksm, $pengguna),
        };
    }

    /** @return array{keterangan: string, tautan: string, labelTautan: string, ikonTautan: Heroicon} */
    private function untukAdmin(Ksm $ksm): array
    {
        $belumMencatat = (new RingkasanPengangkutan($ksm))->petugasBelumMencatat(Hari::tanggal());

        if ($belumMencatat === null) {
            return [
                'keterangan' => 'Belum ada petugas aktif — tambahkan di menu Pengguna.',
                'tautan' => UserResource::getUrl('index'),
                'labelTautan' => 'Pengguna',
                'ikonTautan' => Heroicon::OutlinedUsers,
            ];
        }

        return [
            'keterangan' => $belumMencatat->isEmpty()
                ? 'Semua petugas aktif sudah mencatat pengangkutan hari ini.'
                : 'Belum mencatat pengangkutan hari ini: '.self::daftarNama($belumMencatat),
            'tautan' => PengangkutanResource::getUrl('index'),
            'labelTautan' => 'Pengangkutan',
            'ikonTautan' => Heroicon::OutlinedTruck,
        ];
    }

    /** @return array{keterangan: string, tautan: string, labelTautan: string, ikonTautan: Heroicon} */
    private function untukPetugas(Ksm $ksm, User $petugas): array
    {
        $terakhir = (new RingkasanPengangkutan($ksm))->rondeTerakhirOleh($petugas);

        return [
            'keterangan' => $terakhir === null
                ? 'Belum pernah mencatat pengangkutan.'
                : 'Pengangkutan terakhir: '.Hari::sebut($terakhir).'.',
            'tautan' => PengangkutanResource::getUrl('create'),
            'labelTautan' => 'Catat pengangkutan',
            'ikonTautan' => Heroicon::OutlinedPlus,
        ];
    }

    /** @return array{keterangan: string} */
    private function untukWarga(Ksm $ksm, User $warga): array
    {
        $terakhir = (new RingkasanPengangkutan($ksm))->diangkutTerakhirUntuk($warga);

        return [
            'keterangan' => $terakhir === null
                ? 'Belum ada catatan sampah diangkut dari rumah Anda.'
                : 'Sampah terakhir diangkut dari rumah Anda: '.Hari::sebut($terakhir).'.',
        ];
    }
}
