<?php

namespace App\Support\Laporan;

use App\Enums\FormatAngka;
use App\Models\Ksm;
use App\Support\Periode;
use App\Support\RingkasanMaterial;
use App\Support\RingkasanPengangkutan;

/** Environment report sections: per material kind, per buyer, per month. */
final class LaporanLingkungan
{
    private readonly RingkasanMaterial $material;

    private readonly RingkasanPengangkutan $pengangkutan;

    public function __construct(Ksm $ksm)
    {
        $this->material = new RingkasanMaterial($ksm);
        $this->pengangkutan = new RingkasanPengangkutan($ksm);
    }

    /** @return list<TabelLaporan> */
    public function semua(Periode $periode): array
    {
        return [$this->perJenis($periode), $this->perPengepul($periode), $this->perBulan($periode)];
    }

    /** @return list<KolomLaporan> */
    public static function kolomPerJenis(): array
    {
        return [
            new KolomLaporan('jenis', 'Jenis'),
            new KolomLaporan('kelompok', 'Kelompok'),
            new KolomLaporan('pilah', 'Dipilah', FormatAngka::Kg),
            new KolomLaporan('olah', 'Diolah', FormatAngka::Kg),
            new KolomLaporan('terpulihkan', 'Terpulihkan', FormatAngka::Kg),
            new KolomLaporan('jual_kg', 'Terjual', FormatAngka::Kg),
            new KolomLaporan('nilai', 'Nilai jual', FormatAngka::Rupiah),
            new KolomLaporan('stok', 'Stok perkiraan', FormatAngka::Kg),
        ];
    }

    public function perJenis(Periode $periode): TabelLaporan
    {
        $baris = $this->material->perJenis($periode);

        return new TabelLaporan(
            judul: 'Material per jenis',
            kolom: self::kolomPerJenis(),
            baris: $baris,
            total: ['jenis' => 'Jumlah', 'kelompok' => null, ...self::jumlahkanKg($baris, ['pilah', 'olah', 'terpulihkan', 'jual_kg', 'stok']), 'nilai' => (int) array_sum(array_column($baris, 'nilai'))],
            catatan: 'Terpulihkan = dipilah + diolah (penjualan tidak dihitung lagi). Stok perkiraan = dipilah − terjual sampai akhir periode; negatif berarti catatan pilah belum lengkap.',
        );
    }

    /** @return list<KolomLaporan> */
    public static function kolomPerPengepul(): array
    {
        return [
            new KolomLaporan('mitra', 'Pengepul'),
            new KolomLaporan('transaksi', 'Penjualan', FormatAngka::Angka),
            new KolomLaporan('kg', 'Berat', FormatAngka::Kg),
            new KolomLaporan('nilai', 'Nilai', FormatAngka::Rupiah),
        ];
    }

    public function perPengepul(Periode $periode): TabelLaporan
    {
        $baris = $this->material->perPengepul($periode);

        return new TabelLaporan(
            judul: 'Penjualan per pengepul',
            kolom: self::kolomPerPengepul(),
            baris: $baris,
            total: ['mitra' => 'Jumlah', 'transaksi' => (int) array_sum(array_column($baris, 'transaksi')), ...self::jumlahkanKg($baris, ['kg']), 'nilai' => (int) array_sum(array_column($baris, 'nilai'))],
            catatan: 'Nilai penjualan masuk buku kas sebagai "Penjualan material".',
        );
    }

    /** @return list<KolomLaporan> */
    public static function kolomPerBulan(): array
    {
        return [
            new KolomLaporan('bulan', 'Bulan'),
            new KolomLaporan('terpulihkan', 'Terpulihkan', FormatAngka::Kg),
            new KolomLaporan('jual_kg', 'Terjual', FormatAngka::Kg),
            new KolomLaporan('nilai', 'Nilai jual', FormatAngka::Rupiah),
            new KolomLaporan('terangkut', 'Sampah terangkut', FormatAngka::Kg),
            new KolomLaporan('pemulihan', 'Tingkat pemulihan', FormatAngka::Persen),
        ];
    }

    public function perBulan(Periode $periode): TabelLaporan
    {
        $baris = array_map(function ($awal): array {
            [$dari, $sampai] = [$awal->toDateString(), $awal->endOfMonth()->toDateString()];
            $terjual = $this->material->terjual($dari, $sampai);
            $terpulihkan = $this->material->terpulihkan($dari, $sampai);
            $terangkut = round($this->pengangkutan->berat($dari, $sampai), 1);

            return [
                'bulan' => $awal->translatedFormat('F Y'),
                'terpulihkan' => $terpulihkan,
                'jual_kg' => $terjual['kg'],
                'nilai' => $terjual['nilai'],
                'terangkut' => $terangkut,
                'pemulihan' => RingkasanPengangkutan::bagi($terpulihkan, $terangkut),
            ];
        }, $periode->bulanBulan());

        $total = ['bulan' => 'Jumlah', ...self::jumlahkanKg($baris, ['terpulihkan', 'jual_kg', 'terangkut']), 'nilai' => (int) array_sum(array_column($baris, 'nilai'))];

        return new TabelLaporan(
            judul: 'Material per bulan',
            kolom: self::kolomPerBulan(),
            baris: $baris,
            total: [...$total, 'pemulihan' => RingkasanPengangkutan::bagi($total['terpulihkan'], $total['terangkut'])],
            catatan: 'Tingkat pemulihan = material terpulihkan ÷ perkiraan sampah terangkut petugas (bisa lebih dari 100%).',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @param  list<string>  $kunci
     * @return array<string, float>
     */
    private static function jumlahkanKg(array $baris, array $kunci): array
    {
        return collect($kunci)->mapWithKeys(fn (string $satu): array => [$satu => round((float) array_sum(array_column($baris, $satu)), 1)])->all();
    }
}
