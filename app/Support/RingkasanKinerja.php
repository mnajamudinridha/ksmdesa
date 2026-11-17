<?php

namespace App\Support;

use App\Enums\FormatAngka;
use App\Models\Ksm;
use App\Models\Mitra;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * The KSM's performance in one place: the four circular-economy indicators and a summary across
 * finance, fees, operations, environment and partnerships, each against the previous period.
 * Built only from the other summaries, so no figure here can disagree with its own report.
 */
final class RingkasanKinerja
{
    /**
     * Figures that only exist as of today (partner status has no history): no previous value,
     * so no change, rather than today's number presented as last period's.
     *
     * @var list<string>
     */
    private const array SAAT_INI = ['mitra_aktif'];

    private readonly RingkasanKas $kas;

    private readonly RingkasanIuran $iuran;

    private readonly RingkasanPengangkutan $pengangkutan;

    private readonly RingkasanMaterial $material;

    private readonly RingkasanMitra $mitra;

    public function __construct(private readonly Ksm $ksm)
    {
        $this->kas = new RingkasanKas($ksm);
        $this->iuran = new RingkasanIuran($ksm);
        $this->pengangkutan = new RingkasanPengangkutan($ksm);
        $this->material = new RingkasanMaterial($ksm);
        $this->mitra = new RingkasanMitra($ksm);
    }

    /**
     * Ratios (0.73 = 73 %); null when there is nothing to divide by yet.
     *
     * - penagihan: fees collected for the fee months of the period (from the first recorded month,
     *   up to this month) ÷ (current monthly target × those months).
     * - cakupan: active houses collected at least once ÷ active houses.
     * - pemulihan: recovered material kg ÷ collected waste kg.
     * - kemandirian: own income (fees by cash date + material sales) ÷ spending — partner support
     *   and other income are left out.
     *
     * @return array{penagihan: ?float, cakupan: ?float, pemulihan: ?float, kemandirian: ?float}
     */
    public function indikator(Periode $periode): array
    {
        [$dari, $sampai] = [$periode->dariString(), $periode->sampaiString()];

        return [
            'penagihan' => $this->penagihan($periode),
            'cakupan' => $this->pengangkutan->cakupanKsm($periode),
            'pemulihan' => RingkasanPengangkutan::bagi($this->material->terpulihkan($dari, $sampai), $this->pengangkutan->berat($dari, $sampai)),
            'kemandirian' => RingkasanPengangkutan::bagi($this->kas->iuran($dari, $sampai) + $this->kas->penjualanMaterial($dari, $sampai), $this->kas->keluar($dari, $sampai)),
        ];
    }

    /**
     * Summary rows, each with its own format ("__format") and the change against the previous
     * period (relative; null when the previous value is zero or unknown).
     *
     * @return list<array{bidang: string, indikator: string, nilai: int|float|null, sebelumnya: int|float|null, perubahan: ?float, __format: string}>
     */
    public function ringkasan(Periode $periode): array
    {
        $ini = $this->angka($periode);
        $sebelumnya = [...$this->angka($periode->sebelumnya()), ...array_fill_keys(self::SAAT_INI, null)];

        return array_map(fn (array $baris): array => [
            'bidang' => $baris[0],
            'indikator' => $baris[1],
            'nilai' => $ini[$baris[2]],
            'sebelumnya' => $sebelumnya[$baris[2]],
            'perubahan' => self::perubahan($ini[$baris[2]], $sebelumnya[$baris[2]]),
            '__format' => $baris[3]->value,
        ], [
            ['Keuangan', 'Pemasukan', 'masuk', FormatAngka::Rupiah],
            ['Keuangan', 'Pengeluaran', 'keluar', FormatAngka::Rupiah],
            ['Keuangan', 'Surplus/defisit', 'surplus', FormatAngka::Rupiah],
            ['Keuangan', 'Saldo akhir', 'saldo', FormatAngka::Rupiah],
            ['Iuran', 'Terkumpul (per bulan iuran)', 'iuran_periode', FormatAngka::Rupiah],
            ['Operasional', 'Hari pengangkutan', 'hari_angkut', FormatAngka::Angka],
            ['Operasional', 'Kunjungan diangkut', 'kunjungan', FormatAngka::Angka],
            ['Operasional', 'Sampah terangkut', 'terangkut', FormatAngka::Kg],
            ['Operasional', 'RT terlayani', 'rt_terlayani', FormatAngka::Angka],
            ['Lingkungan', 'Material terpulihkan', 'terpulihkan', FormatAngka::Kg],
            ['Lingkungan', 'Material terjual', 'terjual_kg', FormatAngka::Kg],
            ['Lingkungan', 'Nilai penjualan', 'nilai_jual', FormatAngka::Rupiah],
            ['Lingkungan', 'Material diolah', 'diolah', FormatAngka::Kg],
            ['Kemitraan', 'Mitra aktif (saat ini)', 'mitra_aktif', FormatAngka::Angka],
            ['Kemitraan', 'Dukungan diterima', 'dukungan', FormatAngka::Rupiah],
        ]);
    }

    private function penagihan(Periode $periode): ?float
    {
        $pertama = $this->iuran->bulanPertama();

        if ($pertama === null) {
            return null;
        }

        $bulanIni = Hari::ini()->startOfMonth();
        $bulan = array_filter(
            $periode->bulanBulan(),
            fn (CarbonImmutable $satu): bool => $satu->lessThanOrEqualTo($bulanIni) && $satu->format('Y-m') >= $pertama->format('Y-m'),
        );

        $terkumpul = array_sum(array_map(fn (CarbonImmutable $satu): int => $this->iuran->terkumpul(Carbon::instance($satu)), $bulan));

        return RingkasanPengangkutan::bagi($terkumpul, $this->iuran->target() * count($bulan));
    }

    /** @return array<string, int|float|null> */
    private function angka(Periode $periode): array
    {
        [$dari, $sampai] = [$periode->dariString(), $periode->sampaiString()];
        $perWilayah = $this->pengangkutan->perWilayah($periode);
        $terjual = $this->material->terjual($dari, $sampai);
        $masuk = $this->kas->masuk($dari, $sampai);
        $keluar = $this->kas->keluar($dari, $sampai);

        return [
            'masuk' => $masuk,
            'keluar' => $keluar,
            'surplus' => $masuk - $keluar,
            'saldo' => $this->kas->saldoAkhir(min($sampai, Hari::tanggal())),
            'iuran_periode' => array_sum(array_map(fn (CarbonImmutable $satu): int => $this->iuran->terkumpul(Carbon::instance($satu)), $periode->bulanBulan())),
            'hari_angkut' => $this->pengangkutan->hariPengangkutan($periode),
            'kunjungan' => array_sum(array_column($perWilayah, 'kunjungan')),
            'terangkut' => round($this->pengangkutan->berat($dari, $sampai), 1),
            'rt_terlayani' => $this->pengangkutan->rtTerlayani($perWilayah)[0],
            'terpulihkan' => $this->material->terpulihkan($dari, $sampai),
            'terjual_kg' => $terjual['kg'],
            'nilai_jual' => $terjual['nilai'],
            'diolah' => $this->material->diolah($dari, $sampai),
            'mitra_aktif' => Mitra::query()->whereBelongsTo($this->ksm)->where('aktif', true)->count(),
            'dukungan' => $this->mitra->dukungan($periode),
        ];
    }

    private static function perubahan(int|float|null $ini, int|float|null $sebelumnya): ?float
    {
        if ($ini === null || $sebelumnya === null || $sebelumnya == 0) {
            return null;
        }

        return ($ini - $sebelumnya) / abs($sebelumnya);
    }
}
