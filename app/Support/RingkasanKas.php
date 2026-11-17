<?php

namespace App\Support;

use App\Enums\JenisTransaksi;
use App\Models\Ksm;
use App\Models\PembayaranIuran;
use App\Models\TransaksiKas;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The KSM's cash: one place for every figure of the cash book.
 *
 * - Income = fees by the day the cash came in (imported fees without that date: the first of
 *   their month) + manual income entries (+ material sales, phase 5C).
 * - Spending = manual spending entries.
 * - Balance on a day = opening balance + income − spending from the start date (kas_mulai) up
 *   to that day; none before the start date. Flows are always reported, balances can be "—".
 *
 * Dates are village dates compared as Y-m-d strings. Every query is limited to the given KSM.
 */
final class RingkasanKas
{
    /** "Since the first record" when no start date is set. */
    private const string AWAL = '0001-01-01';

    public function __construct(private readonly Ksm $ksm) {}

    public function iuran(string $dari, string $sampai): int
    {
        return (int) $this->pembayaran($dari, $sampai)->sum('nominal');
    }

    public function masukManual(string $dari, string $sampai): int
    {
        return (int) $this->transaksi(JenisTransaksi::Masuk, $dari, $sampai)->sum('nominal');
    }

    /** Material sales (phase 5C); nothing yet. */
    public function penjualanMaterial(string $dari, string $sampai): int
    {
        return 0;
    }

    public function masuk(string $dari, string $sampai): int
    {
        return $this->iuran($dari, $sampai) + $this->masukManual($dari, $sampai) + $this->penjualanMaterial($dari, $sampai);
    }

    public function keluar(string $dari, string $sampai): int
    {
        return (int) $this->transaksi(JenisTransaksi::Keluar, $dari, $sampai)->sum('nominal');
    }

    /** Balance at the end of $tanggal; null before the cash book's start date. */
    public function saldoAkhir(string $tanggal): ?int
    {
        $mulai = $this->mulai();

        if ($mulai !== null && $tanggal < $mulai) {
            return null;
        }

        $dari = $mulai ?? self::AWAL;

        return $this->ksm->saldo_awal + $this->masuk($dari, $tanggal) - $this->keluar($dari, $tanggal);
    }

    /** Balance on the morning of $tanggal; null before the cash book's start date. */
    public function saldoAwal(string $tanggal): ?int
    {
        if ($tanggal === $this->mulai()) {
            return $this->ksm->saldo_awal;
        }

        return $this->saldoAkhir(CarbonImmutable::parse($tanggal)->subDay()->toDateString());
    }

    /** Fee cash recorded by petugas and not yet handed over — part of the balance, held outside the box. */
    public function diTanganPetugas(): int
    {
        return (int) PembayaranIuran::query()->whereBelongsTo($this->ksm)->belumDisetor()->sum('nominal');
    }

    /**
     * Income per source: fees first, then each income category, "Tanpa kategori" last.
     *
     * @return list<array{sumber: string, jumlah: int, nominal: int}>
     */
    public function rincianPemasukan(string $dari, string $sampai): array
    {
        $baris = [[
            'sumber' => 'Iuran',
            'jumlah' => $this->pembayaran($dari, $sampai)->count(),
            'nominal' => $this->iuran($dari, $sampai),
        ]];

        foreach ($this->perKategori(JenisTransaksi::Masuk, $dari, $sampai) as $kategori) {
            $baris[] = ['sumber' => $kategori['kategori'], 'jumlah' => $kategori['jumlah'], 'nominal' => $kategori['nominal']];
        }

        return array_values(array_filter($baris, fn (array $satu): bool => $satu['nominal'] > 0));
    }

    /** @return list<array{kategori: string, jumlah: int, nominal: int}> */
    public function rincianPengeluaran(string $dari, string $sampai): array
    {
        return $this->perKategori(JenisTransaksi::Keluar, $dari, $sampai);
    }

    /** @return array<string, int> source => income, for charts */
    public function pemasukanPerSumber(string $dari, string $sampai): array
    {
        return array_column($this->rincianPemasukan($dari, $sampai), 'nominal', 'sumber');
    }

    /** @return array<string, int> category => spending, for charts */
    public function pengeluaranPerKategori(string $dari, string $sampai): array
    {
        return array_column($this->rincianPengeluaran($dari, $sampai), 'nominal', 'kategori');
    }

    /**
     * One row per month. A known balance is carried forward month to month; it is looked up
     * again only while unknown (the month holding the start date, or before it).
     *
     * @param  list<CarbonImmutable>  $bulan  first days of the months, oldest first
     * @return list<array{bulan: string, saldo_awal: ?int, iuran: int, material: int, masuk_lain: int, keluar: int, surplus: int, saldo_akhir: ?int}>
     */
    public function perBulan(array $bulan): array
    {
        $hasil = [];
        $saldo = null;

        foreach ($bulan as $awal) {
            $dari = $awal->startOfMonth()->toDateString();
            $sampai = $awal->endOfMonth()->toDateString();

            $iuran = $this->iuran($dari, $sampai);
            $material = $this->penjualanMaterial($dari, $sampai);
            $masukLain = $this->masukManual($dari, $sampai);
            $keluar = $this->keluar($dari, $sampai);
            $surplus = $iuran + $material + $masukLain - $keluar;

            $saldoAwal = $saldo ?? $this->saldoAwal($dari);
            $saldo = $saldoAwal !== null ? $saldoAwal + $surplus : $this->saldoAkhir($sampai);

            $hasil[] = [
                'bulan' => $awal->format('Y-m'),
                'saldo_awal' => $saldoAwal,
                'iuran' => $iuran,
                'material' => $material,
                'masuk_lain' => $masukLain,
                'keluar' => $keluar,
                'surplus' => $surplus,
                'saldo_akhir' => $saldo,
            ];
        }

        return $hasil;
    }

    /** @return list<array{kategori: string, jumlah: int, nominal: int}> by category name, "Tanpa kategori" last */
    private function perKategori(JenisTransaksi $jenis, string $dari, string $sampai): array
    {
        return $this->transaksi($jenis, $dari, $sampai)
            ->with('kategori')
            ->get(['id', 'kategori_transaksi_id', 'nominal'])
            ->groupBy(fn (TransaksiKas $transaksi): string => $transaksi->kategori?->nama ?? 'Tanpa kategori')
            ->map(fn (Collection $transaksi, string $nama): array => ['kategori' => $nama, 'jumlah' => $transaksi->count(), 'nominal' => (int) $transaksi->sum('nominal')])
            ->sortBy(fn (array $baris): string => ($baris['kategori'] === 'Tanpa kategori' ? '1' : '0').mb_strtolower($baris['kategori']))
            ->values()
            ->all();
    }

    private function mulai(): ?string
    {
        return $this->ksm->kas_mulai?->toDateString();
    }

    /** @return Builder<PembayaranIuran> */
    private function pembayaran(string $dari, string $sampai): Builder
    {
        return PembayaranIuran::query()->whereBelongsTo($this->ksm)->diterimaAntara($dari, $sampai);
    }

    /** @return Builder<TransaksiKas> */
    private function transaksi(JenisTransaksi $jenis, string $dari, string $sampai): Builder
    {
        return TransaksiKas::query()
            ->whereBelongsTo($this->ksm)
            ->where('jenis', $jenis)
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai);
    }
}
