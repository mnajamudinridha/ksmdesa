<?php

namespace App\Support\Laporan;

use App\Enums\FormatAngka;
use App\Models\Ksm;
use App\Support\Periode;
use App\Support\RingkasanPengangkutan;

/**
 * Operational report sections (per RT, per petugas, per day). Each section is the single
 * source for its screen table, its Excel sheet and its printout.
 */
final class LaporanOperasional
{
    private readonly RingkasanPengangkutan $ringkasan;

    public function __construct(Ksm $ksm)
    {
        $this->ringkasan = new RingkasanPengangkutan($ksm);
    }

    /** @return list<TabelLaporan> */
    public function semua(Periode $periode): array
    {
        return [$this->perWilayah($periode), $this->perPetugas($periode), $this->perHari($periode)];
    }

    /** @return list<KolomLaporan> */
    public static function kolomPerWilayah(): array
    {
        return [
            new KolomLaporan('wilayah', 'Wilayah'),
            new KolomLaporan('jumlah_kk', 'KK', FormatAngka::Angka),
            new KolomLaporan('rumah_aktif', 'Rumah aktif', FormatAngka::Angka),
            new KolomLaporan('terlayani', 'Terlayani', FormatAngka::Angka),
            new KolomLaporan('cakupan', 'Cakupan', FormatAngka::Persen),
            new KolomLaporan('penetrasi', 'Pelanggan/KK', FormatAngka::Persen),
            new KolomLaporan('kunjungan', 'Kunjungan diangkut', FormatAngka::Angka),
            new KolomLaporan('rata_mingguan', 'Rata-rata/rumah/minggu', FormatAngka::Angka),
            new KolomLaporan('tidak_bisa_diakses', 'Tidak bisa diakses', FormatAngka::Angka),
            new KolomLaporan('kg', 'Perkiraan', FormatAngka::Kg),
        ];
    }

    public function perWilayah(Periode $periode): TabelLaporan
    {
        $baris = $this->ringkasan->perWilayah($periode);
        [$terlayani, $aktif] = $this->ringkasan->terlayaniDanAktif($baris);
        $kunjunganAktif = array_sum(array_column(array_filter($baris, fn (array $satu): bool => $satu['jenis'] !== 'nonaktif'), 'kunjungan'));

        return new TabelLaporan(
            judul: 'Realisasi pengangkutan per RT',
            kolom: self::kolomPerWilayah(),
            baris: $baris,
            total: [
                'wilayah' => 'Jumlah',
                'jumlah_kk' => null,
                'rumah_aktif' => $aktif,
                'terlayani' => $terlayani,
                // Recomputed from the totals — never a sum of percentages.
                'cakupan' => RingkasanPengangkutan::bagi($terlayani, $aktif),
                'penetrasi' => null,
                'kunjungan' => array_sum(array_column($baris, 'kunjungan')),
                'rata_mingguan' => RingkasanPengangkutan::rataMingguan($kunjunganAktif, $aktif, $periode->jumlahHari() / 7),
                'tidak_bisa_diakses' => array_sum(array_column($baris, 'tidak_bisa_diakses')),
                'kg' => round(array_sum(array_column($baris, 'kg')), 1),
            ],
            catatan: 'Rumah aktif = status pelanggan saat laporan dibuat. Terlayani = diangkut minimal sekali pada periode ini.',
        );
    }

    /** @return list<KolomLaporan> */
    public static function kolomPerPetugas(): array
    {
        return [
            new KolomLaporan('petugas', 'Petugas'),
            new KolomLaporan('rumah_tugas', 'Rumah tugas', FormatAngka::Angka),
            new KolomLaporan('hari_bertugas', 'Hari bertugas', FormatAngka::Angka),
            new KolomLaporan('rumah_dicatat', 'Rumah dicatat', FormatAngka::Angka),
            new KolomLaporan('diangkut', 'Diangkut', FormatAngka::Angka),
            new KolomLaporan('kg', 'Perkiraan', FormatAngka::Kg),
            new KolomLaporan('iuran_diterima', 'Iuran diterima', FormatAngka::Rupiah),
            new KolomLaporan('belum_disetor', 'Belum disetor (saat ini)', FormatAngka::Rupiah),
        ];
    }

    public function perPetugas(Periode $periode): TabelLaporan
    {
        $baris = $this->ringkasan->perPetugas($periode);

        return new TabelLaporan(
            judul: 'Kinerja petugas',
            kolom: self::kolomPerPetugas(),
            baris: $baris,
            total: [
                'petugas' => 'Jumlah',
                ...self::jumlahkan($baris, ['rumah_tugas', 'hari_bertugas', 'rumah_dicatat', 'diangkut', 'iuran_diterima', 'belum_disetor']),
                'kg' => round(array_sum(array_column($baris, 'kg')), 1),
            ],
            catatan: 'Iuran diterima memakai tanggal diterima. Belum disetor = uang yang masih dipegang petugas hari ini.',
        );
    }

    /** @return list<KolomLaporan> */
    public static function kolomPerHari(): array
    {
        return [
            new KolomLaporan('tanggal', 'Tanggal', FormatAngka::Tanggal),
            new KolomLaporan('ronde', 'Ronde', FormatAngka::Angka),
            new KolomLaporan('dicatat', 'Rumah dicatat', FormatAngka::Angka),
            new KolomLaporan('diangkut', 'Diangkut', FormatAngka::Angka),
            new KolomLaporan('tidak_ada_sampah', 'Tidak ada sampah', FormatAngka::Angka),
            new KolomLaporan('tidak_bisa_diakses', 'Tidak bisa diakses', FormatAngka::Angka),
            new KolomLaporan('kg', 'Perkiraan', FormatAngka::Kg),
        ];
    }

    public function perHari(Periode $periode): TabelLaporan
    {
        $baris = $this->ringkasan->perHari($periode);

        return new TabelLaporan(
            judul: 'Rekap harian',
            kolom: self::kolomPerHari(),
            baris: $baris,
            total: [
                'tanggal' => 'Jumlah',
                ...self::jumlahkan($baris, ['ronde', 'dicatat', 'diangkut', 'tidak_ada_sampah', 'tidak_bisa_diakses']),
                'kg' => round(array_sum(array_column($baris, 'kg')), 1),
            ],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @param  list<string>  $kunci
     * @return array<string, int>
     */
    private static function jumlahkan(array $baris, array $kunci): array
    {
        return collect($kunci)->mapWithKeys(fn (string $satu): array => [$satu => (int) array_sum(array_column($baris, $satu))])->all();
    }
}
