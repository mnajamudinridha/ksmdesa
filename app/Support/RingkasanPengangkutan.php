<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Enums\StatusPengangkutan;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Collection figures for the dashboards and reports: a whole KSM, or one petugas within it.
 * Days are village calendar days (Y-m-d from App\Support\Hari).
 */
final class RingkasanPengangkutan
{
    public function __construct(private readonly Ksm $ksm, private readonly ?User $petugas = null) {}

    public function rumahDiangkut(string $tanggal): int
    {
        return $this->rincian($tanggal, $tanggal)->where('status', StatusPengangkutan::Diangkut)->count();
    }

    public function rumahDicatat(string $tanggal): int
    {
        return $this->rincian($tanggal, $tanggal)->count();
    }

    public function berat(string $dari, string $sampai): float
    {
        return (float) $this->rincian($dari, $sampai)->sum('perkiraan_kg');
    }

    /**
     * Estimated kg per day for the last $jumlahHari days up to today, oldest first, days
     * without a round as 0.
     *
     * @return array<string, float>
     */
    public function beratPerHari(int $jumlahHari): array
    {
        $dari = Hari::tanggal($jumlahHari - 1);

        $tercatat = $this->ronde()
            ->join('pengangkutan_rincian', 'pengangkutan_rincian.pengangkutan_id', '=', 'pengangkutan.id')
            ->whereDate('pengangkutan.tanggal', '>=', $dari)
            ->selectRaw('date(pengangkutan.tanggal) as hari, sum(pengangkutan_rincian.perkiraan_kg) as kg')
            ->groupBy('hari')
            ->pluck('kg', 'hari');

        $hasil = [];

        for ($mundur = $jumlahHari - 1; $mundur >= 0; $mundur--) {
            $hari = Hari::tanggal($mundur);
            $hasil[$hari] = round((float) ($tercatat[$hari] ?? 0), 1);
        }

        return $hasil;
    }

    /**
     * Kg per day of a period (up to today), oldest first, days without a round as 0.
     *
     * @return array<string, float>
     */
    public function beratPerTanggal(Periode $periode): array
    {
        $perHari = $this->rondePeriode($periode)
            ->groupBy(fn (Pengangkutan $ronde): string => $ronde->tanggal->toDateString())
            ->map(fn (Collection $ronde): float => round((float) $ronde->sum('kg'), 1));

        return collect($periode->hari())->mapWithKeys(fn (string $hari): array => [$hari => $perHari[$hari] ?? 0.0])->all();
    }

    /**
     * Kg per month of a period, months without a round as 0.
     *
     * @return array<string, float> 'Y-m' => kg
     */
    public function beratPerBulan(Periode $periode): array
    {
        $perBulan = $this->rondePeriode($periode)
            ->groupBy(fn (Pengangkutan $ronde): string => $ronde->tanggal->format('Y-m'))
            ->map(fn (Collection $ronde): float => round((float) $ronde->sum('kg'), 1));

        return collect($periode->bulanBulan())->mapWithKeys(fn ($bulan): array => [$bulan->format('Y-m') => $perBulan[$bulan->format('Y-m')] ?? 0.0])->all();
    }

    /**
     * Coverage per RT for a period. Rows: every RT of the KSM (also those without customers),
     * then "Tanpa RT" (active customers without an RT) and "Pelanggan nonaktif" (all inactive
     * customers, only when they were visited). Coverage counts customers active today — the
     * active flag has no history. Σ kg over all rows = berat() of the period.
     *
     * @return list<array{jenis: string, wilayah: string, jumlah_kk: ?int, rumah_aktif: ?int, terlayani: ?int, cakupan: ?float, penetrasi: ?float, kunjungan: int, rata_mingguan: ?float, tidak_bisa_diakses: int, kg: float}>
     */
    public function perWilayah(Periode $periode): array
    {
        $aktifPerKunci = Pelanggan::query()
            ->whereBelongsTo($this->ksm)
            ->where('aktif', true)
            ->toBase()
            ->selectRaw('wilayah_id, count(*) as jumlah')
            ->groupBy('wilayah_id')
            ->get()
            ->mapWithKeys(fn (object $baris): array => [self::kunciWilayah($baris->wilayah_id) => (int) $baris->jumlah]);

        $baris = [];

        foreach (Wilayah::query()->whereBelongsTo($this->ksm)->urut()->get() as $wilayah) {
            $kunci = self::kunciWilayah($wilayah->getKey());
            $baris[$kunci] = self::barisWilayah('rt', $wilayah->label(), $wilayah->jumlah_kk, $aktifPerKunci[$kunci] ?? 0);
        }

        $baris['tanpa'] = self::barisWilayah('tanpa', 'Tanpa RT', null, $aktifPerKunci['tanpa'] ?? 0);
        $baris['nonaktif'] = self::barisWilayah('nonaktif', 'Pelanggan nonaktif', null, null);

        $dicatat = [];
        $terlayani = [];

        $rincian = $this->rincian($periode->dariString(), $periode->sampaiString())
            ->join('pelanggan', 'pelanggan.id', '=', 'pengangkutan_rincian.pelanggan_id')
            ->get(['pengangkutan_rincian.pelanggan_id', 'pengangkutan_rincian.status', 'pengangkutan_rincian.perkiraan_kg', 'pelanggan.wilayah_id', 'pelanggan.aktif']);

        foreach ($rincian as $satu) {
            $kunci = ! $satu->aktif ? 'nonaktif' : self::kunciWilayah($satu->wilayah_id);
            $kunci = isset($baris[$kunci]) ? $kunci : 'tanpa';

            $dicatat[$kunci] = true;
            $baris[$kunci]['kg'] += (float) $satu->perkiraan_kg;

            if ($satu->status === StatusPengangkutan::Diangkut) {
                $baris[$kunci]['kunjungan']++;

                if ($kunci !== 'nonaktif') {
                    $terlayani[$kunci][$satu->pelanggan_id] = true;
                }
            } elseif ($satu->status === StatusPengangkutan::TidakBisaDiakses) {
                $baris[$kunci]['tidak_bisa_diakses']++;
            }
        }

        $minggu = $periode->jumlahHari() / 7;
        $hasil = [];

        foreach ($baris as $kunci => $satuBaris) {
            if (($kunci === 'nonaktif' || ($kunci === 'tanpa' && $satuBaris['rumah_aktif'] === 0)) && ! isset($dicatat[$kunci])) {
                continue;
            }

            $satuBaris['kg'] = round($satuBaris['kg'], 1);

            if ($kunci !== 'nonaktif') {
                $satuBaris['terlayani'] = count($terlayani[$kunci] ?? []);
                $satuBaris['cakupan'] = self::bagi($satuBaris['terlayani'], $satuBaris['rumah_aktif']);
                $satuBaris['penetrasi'] = self::bagi($satuBaris['rumah_aktif'], $satuBaris['jumlah_kk']);
                $satuBaris['rata_mingguan'] = self::rataMingguan($satuBaris['kunjungan'], $satuBaris['rumah_aktif'], $minggu);
            }

            $hasil[] = $satuBaris;
        }

        return $hasil;
    }

    /** Share of active customers (with or without an RT) collected at least once in the period. */
    public function cakupanKsm(Periode $periode): ?float
    {
        [$terlayani, $aktif] = $this->terlayaniDanAktif($this->perWilayah($periode));

        return self::bagi($terlayani, $aktif);
    }

    /**
     * @param  list<array<string, mixed>>  $baris  rows of perWilayah()
     * @return array{0: int, 1: int} [served, active] over the RT and "Tanpa RT" rows
     */
    public function terlayaniDanAktif(array $baris): array
    {
        $aktif = array_filter($baris, fn (array $satu): bool => $satu['jenis'] !== 'nonaktif');

        return [array_sum(array_column($aktif, 'terlayani')), array_sum(array_column($aktif, 'rumah_aktif'))];
    }

    /**
     * @param  list<array<string, mixed>>  $baris  rows of perWilayah()
     * @return array{0: int, 1: int} [RTs with at least one house collected, RTs in the list]
     */
    public function rtTerlayani(array $baris): array
    {
        $rt = array_filter($baris, fn (array $satu): bool => $satu['jenis'] === 'rt');

        return [count(array_filter($rt, fn (array $satu): bool => $satu['terlayani'] > 0)), count($rt)];
    }

    /** Days in the period with at least one house collected. */
    public function hariPengangkutan(Periode $periode): int
    {
        return $this->rondePeriode($periode)
            ->filter(fn (Pengangkutan $ronde): bool => $ronde->diangkut_count > 0)
            ->map(fn (Pengangkutan $ronde): string => $ronde->tanggal->toDateString())
            ->unique()
            ->count();
    }

    /**
     * Per petugas: active petugas, plus anyone (also inactive) who did a round in the period.
     *
     * @return list<array{petugas: string, aktif: bool, rumah_tugas: int, hari_bertugas: int, rumah_dicatat: int, diangkut: int, kg: float, iuran_diterima: int, belum_disetor: int}>
     */
    public function perPetugas(Periode $periode): array
    {
        $ronde = $this->rondePeriode($periode)->groupBy('petugas_id');

        $petugas = User::query()
            ->whereBelongsTo($this->ksm)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $aktif) => $aktif->where('peran', PeranPengguna::Petugas)->where('aktif', true))
                ->orWhereIn('id', $ronde->keys()->all()))
            ->orderBy('name')
            ->get();

        $ids = $petugas->modelKeys();

        $rumahTugas = Pelanggan::query()->whereBelongsTo($this->ksm)->where('aktif', true)->whereIn('petugas_id', $ids)
            ->toBase()->selectRaw('petugas_id, count(*) as jumlah')->groupBy('petugas_id')->pluck('jumlah', 'petugas_id');
        $iuranDiterima = PembayaranIuran::query()->whereBelongsTo($this->ksm)->whereIn('petugas_id', $ids)->diterimaAntara($periode->dariString(), $periode->sampaiString())
            ->toBase()->selectRaw('petugas_id, sum(nominal) as jumlah')->groupBy('petugas_id')->pluck('jumlah', 'petugas_id');
        $belumDisetor = PembayaranIuran::query()->whereBelongsTo($this->ksm)->whereIn('petugas_id', $ids)->belumDisetor()
            ->toBase()->selectRaw('petugas_id, sum(nominal) as jumlah')->groupBy('petugas_id')->pluck('jumlah', 'petugas_id');

        return $petugas->map(function (User $satu) use ($ronde, $rumahTugas, $iuranDiterima, $belumDisetor): array {
            $miliknya = $ronde[$satu->getKey()] ?? collect();

            return [
                'petugas' => $satu->name.($satu->aktif && $satu->peran === PeranPengguna::Petugas ? '' : ' (nonaktif)'),
                'aktif' => $satu->aktif && $satu->peran === PeranPengguna::Petugas,
                'rumah_tugas' => (int) ($rumahTugas[$satu->getKey()] ?? 0),
                'hari_bertugas' => $miliknya->map(fn (Pengangkutan $r): string => $r->tanggal->toDateString())->unique()->count(),
                'rumah_dicatat' => (int) $miliknya->sum('rincian_count'),
                'diangkut' => (int) $miliknya->sum('diangkut_count'),
                'kg' => round((float) $miliknya->sum('kg'), 1),
                'iuran_diterima' => (int) ($iuranDiterima[$satu->getKey()] ?? 0),
                'belum_disetor' => (int) ($belumDisetor[$satu->getKey()] ?? 0),
            ];
        })->values()->all();
    }

    /**
     * One row per day that had a round.
     *
     * @return list<array{tanggal: string, ronde: int, dicatat: int, diangkut: int, tidak_ada_sampah: int, tidak_bisa_diakses: int, kg: float}>
     */
    public function perHari(Periode $periode): array
    {
        return $this->rondePeriode($periode)
            ->groupBy(fn (Pengangkutan $ronde): string => $ronde->tanggal->toDateString())
            ->map(fn (Collection $ronde, string $tanggal): array => [
                'tanggal' => $tanggal,
                'ronde' => $ronde->count(),
                'dicatat' => (int) $ronde->sum('rincian_count'),
                'diangkut' => (int) $ronde->sum('diangkut_count'),
                'tidak_ada_sampah' => (int) $ronde->sum('tidak_ada_sampah_count'),
                'tidak_bisa_diakses' => (int) $ronde->sum('tidak_bisa_diakses_count'),
                'kg' => round((float) $ronde->sum('kg'), 1),
            ])
            ->values()
            ->all();
    }

    public static function kg(float $kg): string
    {
        return Number::format($kg, precision: 1, locale: 'id').' kg';
    }

    public static function bagi(int|float|null $pembilang, int|float|null $penyebut): ?float
    {
        return $pembilang === null || ! $penyebut ? null : $pembilang / $penyebut;
    }

    /** Collections per active house per week, to one decimal; null when nothing to divide by. */
    public static function rataMingguan(int $kunjungan, ?int $rumahAktif, float $minggu): ?float
    {
        return $rumahAktif && $minggu > 0 ? round($kunjungan / $rumahAktif / $minggu, 1) : null;
    }

    private static function kunciWilayah(mixed $wilayahId): string
    {
        return $wilayahId === null ? 'tanpa' : "w{$wilayahId}";
    }

    /** @return array<string, mixed> */
    private static function barisWilayah(string $jenis, string $label, ?int $jumlahKk, ?int $rumahAktif): array
    {
        return [
            'jenis' => $jenis,
            'wilayah' => $label,
            'jumlah_kk' => $jumlahKk,
            'rumah_aktif' => $rumahAktif,
            'terlayani' => null,
            'cakupan' => null,
            'penetrasi' => null,
            'kunjungan' => 0,
            'rata_mingguan' => null,
            'tidak_bisa_diakses' => 0,
            'kg' => 0.0,
        ];
    }

    /**
     * The period's rounds with their row counts and kg, oldest first.
     *
     * @return Collection<int, Pengangkutan>
     */
    private function rondePeriode(Periode $periode): Collection
    {
        return $this->ronde()
            ->whereDate('tanggal', '>=', $periode->dariString())
            ->whereDate('tanggal', '<=', $periode->sampaiString())
            ->withCount([
                'rincian',
                'rincian as diangkut_count' => fn (Builder $query) => $query->where('status', StatusPengangkutan::Diangkut),
                'rincian as tidak_ada_sampah_count' => fn (Builder $query) => $query->where('status', StatusPengangkutan::TidakAdaSampah),
                'rincian as tidak_bisa_diakses_count' => fn (Builder $query) => $query->where('status', StatusPengangkutan::TidakBisaDiakses),
            ])
            ->withSum('rincian as kg', 'perkiraan_kg')
            ->orderBy('tanggal')
            ->get();
    }

    /** @return Builder<Pengangkutan> */
    private function ronde(): Builder
    {
        return Pengangkutan::query()
            ->whereBelongsTo($this->ksm)
            ->when($this->petugas, fn (Builder $query) => $query->where('pengangkutan.petugas_id', $this->petugas->getKey()));
    }

    /** @return Builder<PengangkutanRincian> */
    private function rincian(string $dari, string $sampai): Builder
    {
        return PengangkutanRincian::query()->whereIn('pengangkutan_id', $this->ronde()
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->select('pengangkutan.id'));
    }
}
