<?php

namespace App\Support\Laporan;

use App\Enums\FormatAngka;
use App\Enums\JenisTransaksi;
use App\Models\Ksm;
use App\Models\TransaksiKas;
use App\Support\Periode;
use App\Support\RingkasanKas;
use App\Support\RingkasanPengangkutan;
use Illuminate\Support\Carbon;

/**
 * Financial report sections (monthly cash flow, income per source, spending per category,
 * cash-book entries) — each the single source for its screen table, Excel sheet and printout.
 */
final class LaporanKeuangan
{
    private readonly RingkasanKas $kas;

    public function __construct(private readonly Ksm $ksm)
    {
        $this->kas = new RingkasanKas($ksm);
    }

    /** @return list<TabelLaporan> */
    public function semua(Periode $periode): array
    {
        return [$this->arusKasBulanan($periode), $this->pemasukan($periode), $this->pengeluaran($periode), $this->rincian($periode)];
    }

    /** @return list<KolomLaporan> */
    public static function kolomArusKas(): array
    {
        return [
            new KolomLaporan('bulan', 'Bulan'),
            new KolomLaporan('saldo_awal', 'Saldo awal', FormatAngka::Rupiah),
            new KolomLaporan('iuran', 'Iuran', FormatAngka::Rupiah),
            new KolomLaporan('masuk_lain', 'Pemasukan lain', FormatAngka::Rupiah),
            new KolomLaporan('keluar', 'Pengeluaran', FormatAngka::Rupiah),
            new KolomLaporan('surplus', 'Surplus/defisit', FormatAngka::Rupiah),
            new KolomLaporan('saldo_akhir', 'Saldo akhir', FormatAngka::Rupiah),
        ];
    }

    public function arusKasBulanan(Periode $periode): TabelLaporan
    {
        $baris = array_map(
            fn (array $bulan): array => [...$bulan, 'bulan' => Carbon::createFromFormat('Y-m', $bulan['bulan'])->translatedFormat('F Y')],
            $this->kas->perBulan($periode->bulanBulan()),
        );

        return new TabelLaporan(
            judul: 'Arus kas bulanan',
            kolom: self::kolomArusKas(),
            baris: $baris,
            total: [
                'bulan' => 'Jumlah',
                'saldo_awal' => $baris[0]['saldo_awal'] ?? null,
                ...self::jumlahkan($baris, ['iuran', 'masuk_lain', 'keluar', 'surplus']),
                'saldo_akhir' => $baris === [] ? null : $baris[array_key_last($baris)]['saldo_akhir'],
            ],
            catatan: 'Iuran dihitung pada tanggal diterima (iuran hasil impor tanpa tanggal: tanggal 1 bulan iurannya); rinciannya ada di menu Pembayaran Iuran. Saldo "—" = sebelum tanggal mulai pembukuan (Profil KSM).',
        );
    }

    /** @return list<KolomLaporan> */
    public static function kolomPemasukan(): array
    {
        return [
            new KolomLaporan('sumber', 'Sumber'),
            new KolomLaporan('jumlah', 'Transaksi', FormatAngka::Angka),
            new KolomLaporan('nominal', 'Nominal', FormatAngka::Rupiah),
            new KolomLaporan('porsi', 'Porsi', FormatAngka::Persen),
        ];
    }

    public function pemasukan(Periode $periode): TabelLaporan
    {
        $baris = self::denganPorsi($this->kas->rincianPemasukan($periode->dariString(), $periode->sampaiString()));

        return new TabelLaporan(
            judul: 'Pemasukan per sumber',
            kolom: self::kolomPemasukan(),
            baris: $baris,
            total: ['sumber' => 'Jumlah', ...self::jumlahkan($baris, ['jumlah', 'nominal']), 'porsi' => $baris === [] ? null : 1.0],
            catatan: 'Transaksi iuran = jumlah pembayaran iuran yang diterima pada periode ini.',
        );
    }

    /** @return list<KolomLaporan> */
    public static function kolomPengeluaran(): array
    {
        return [
            new KolomLaporan('kategori', 'Kategori'),
            new KolomLaporan('jumlah', 'Transaksi', FormatAngka::Angka),
            new KolomLaporan('nominal', 'Nominal', FormatAngka::Rupiah),
            new KolomLaporan('porsi', 'Porsi', FormatAngka::Persen),
        ];
    }

    public function pengeluaran(Periode $periode): TabelLaporan
    {
        $baris = self::denganPorsi($this->kas->rincianPengeluaran($periode->dariString(), $periode->sampaiString()));

        return new TabelLaporan(
            judul: 'Pengeluaran per kategori',
            kolom: self::kolomPengeluaran(),
            baris: $baris,
            total: ['kategori' => 'Jumlah', ...self::jumlahkan($baris, ['jumlah', 'nominal']), 'porsi' => $baris === [] ? null : 1.0],
        );
    }

    /** @return list<KolomLaporan> */
    public static function kolomRincian(): array
    {
        return [
            new KolomLaporan('tanggal', 'Tanggal', FormatAngka::Tanggal),
            new KolomLaporan('jenis', 'Jenis'),
            new KolomLaporan('kategori', 'Kategori'),
            new KolomLaporan('uraian', 'Uraian'),
            new KolomLaporan('mitra', 'Mitra'),
            new KolomLaporan('masuk', 'Masuk', FormatAngka::Rupiah),
            new KolomLaporan('keluar', 'Keluar', FormatAngka::Rupiah),
            new KolomLaporan('bukti', 'Bukti'),
        ];
    }

    public function rincian(Periode $periode): TabelLaporan
    {
        $baris = TransaksiKas::query()
            ->whereBelongsTo($this->ksm)
            ->whereDate('tanggal', '>=', $periode->dariString())
            ->whereDate('tanggal', '<=', $periode->sampaiString())
            ->with(['kategori', 'mitra'])
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->map(fn (TransaksiKas $transaksi): array => [
                'tanggal' => $transaksi->tanggal->toDateString(),
                'jenis' => $transaksi->jenis->getLabel(),
                'kategori' => $transaksi->kategori?->nama ?? 'Tanpa kategori',
                'uraian' => $transaksi->uraian,
                'mitra' => $transaksi->mitra?->nama,
                'masuk' => $transaksi->jenis === JenisTransaksi::Masuk ? $transaksi->nominal : null,
                'keluar' => $transaksi->jenis === JenisTransaksi::Keluar ? $transaksi->nominal : null,
                'bukti' => filled($transaksi->bukti) ? 'Ada' : null,
            ])
            ->all();

        return new TabelLaporan(
            judul: 'Rincian transaksi kas',
            kolom: self::kolomRincian(),
            baris: $baris,
            total: ['tanggal' => 'Jumlah', ...self::jumlahkan($baris, ['masuk', 'keluar'])],
            catatan: 'Hanya transaksi yang dicatat di Buku Kas; iuran tercantum per bulan pada arus kas.',
        );
    }

    /**
     * @param  list<array<string, mixed>>  $baris  rows with a "nominal"
     * @return list<array<string, mixed>>
     */
    private static function denganPorsi(array $baris): array
    {
        $jumlah = array_sum(array_column($baris, 'nominal'));

        return array_map(fn (array $satu): array => [...$satu, 'porsi' => RingkasanPengangkutan::bagi($satu['nominal'], $jumlah)], $baris);
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
