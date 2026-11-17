<?php

namespace App\Support\Laporan;

use App\Enums\FormatAngka;
use App\Models\Ksm;
use App\Models\Mitra;
use App\Support\Periode;
use App\Support\RingkasanMitra;

/** Partnership report sections: what each partner contributed, and the partner list. */
final class LaporanKemitraan
{
    private readonly RingkasanMitra $mitra;

    public function __construct(private readonly Ksm $ksm)
    {
        $this->mitra = new RingkasanMitra($ksm);
    }

    /** @return list<TabelLaporan> */
    public function semua(Periode $periode): array
    {
        return [$this->kontribusi($periode), $this->daftar($periode)];
    }

    /** @return list<KolomLaporan> */
    public static function kolomKontribusi(): array
    {
        return [
            new KolomLaporan('mitra', 'Mitra'),
            new KolomLaporan('jenis', 'Jenis'),
            new KolomLaporan('dukungan', 'Dukungan (kas)', FormatAngka::Rupiah),
            new KolomLaporan('jual_kg', 'Penjualan', FormatAngka::Kg),
            new KolomLaporan('nilai', 'Nilai penjualan', FormatAngka::Rupiah),
        ];
    }

    public function kontribusi(Periode $periode): TabelLaporan
    {
        $baris = array_map(fn (array $satu): array => array_diff_key($satu, ['aktif' => true]), $this->mitra->kontribusi($periode));

        return new TabelLaporan(
            judul: 'Kontribusi mitra',
            kolom: self::kolomKontribusi(),
            baris: $baris,
            total: [
                'mitra' => 'Jumlah',
                'jenis' => null,
                'dukungan' => (int) array_sum(array_column($baris, 'dukungan')),
                'jual_kg' => round((float) array_sum(array_column($baris, 'jual_kg')), 1),
                'nilai' => (int) array_sum(array_column($baris, 'nilai')),
            ],
            catatan: 'Dukungan = pemasukan Buku Kas yang mencantumkan mitra. Penjualan = catatan material "dijual" ke mitra itu sebagai pengepul.',
        );
    }

    /** @return list<KolomLaporan> */
    public static function kolomDaftar(): array
    {
        return [
            new KolomLaporan('nama', 'Nama'),
            new KolomLaporan('jenis', 'Jenis'),
            new KolomLaporan('kontak', 'Narahubung'),
            new KolomLaporan('no_hp', 'Nomor HP'),
            new KolomLaporan('status', 'Status'),
        ];
    }

    /** The whole list (also inactive partners); the period does not apply. */
    public function daftar(Periode $periode): TabelLaporan
    {
        $baris = Mitra::query()
            ->whereBelongsTo($this->ksm)
            ->orderBy('nama')
            ->get()
            ->map(fn (Mitra $mitra): array => [
                'nama' => $mitra->nama,
                'jenis' => $mitra->jenis->getLabel(),
                'kontak' => $mitra->kontak,
                'no_hp' => $mitra->no_hp,
                'status' => $mitra->aktif ? 'Aktif' : 'Nonaktif',
            ])
            ->all();

        return new TabelLaporan(judul: 'Daftar mitra', kolom: self::kolomDaftar(), baris: $baris);
    }
}
