<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The period a report or analytics page shows: one month, or a whole year, in village days.
 *
 * Built only through dariFilter(): dashboard page filters are not validated by Filament and
 * can be edited in the URL (?filters[...]), so anything unexpected falls back to the default
 * (this village month) instead of throwing.
 */
final readonly class Periode
{
    public const string SETAHUN = 'semua';

    private const int TAHUN_AWAL = 2020;

    private function __construct(
        public int $tahun,
        public ?int $bulan,
        public CarbonImmutable $dari,
        public CarbonImmutable $sampai,
    ) {}

    /** @param  array<string, mixed>|null  $filters  raw page filters: ['tahun' => '2026', 'bulan' => '11' | 'semua'] */
    public static function dariFilter(?array $filters): self
    {
        $ini = Hari::ini();

        $tahun = filter_var($filters['tahun'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => self::TAHUN_AWAL, 'max_range' => $ini->year + 1]]);
        $tahun = $tahun === false ? $ini->year : $tahun;

        $nilaiBulan = $filters['bulan'] ?? null;
        $bulan = $nilaiBulan === self::SETAHUN
            ? null
            : filter_var($nilaiBulan, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
        $bulan = $bulan === false ? $ini->month : $bulan;

        return self::buat($tahun, $bulan);
    }

    public static function bulanIni(): self
    {
        return self::dariFilter(null);
    }

    private static function buat(int $tahun, ?int $bulan): self
    {
        $dari = Hari::ini()->setDate($tahun, $bulan ?? 1, 1);

        return new self($tahun, $bulan, $dari, $bulan === null ? $dari->endOfYear()->startOfDay() : $dari->endOfMonth()->startOfDay());
    }

    public function label(): string
    {
        return $this->bulan === null ? "Tahun {$this->tahun}" : $this->dari->translatedFormat('F Y');
    }

    /** For file names: 2026-11, or 2026 for a whole year. */
    public function kunci(): string
    {
        return $this->bulan === null ? (string) $this->tahun : $this->dari->format('Y-m');
    }

    public function dariString(): string
    {
        return $this->dari->toDateString();
    }

    public function sampaiString(): string
    {
        return $this->sampai->toDateString();
    }

    /** Days elapsed in the period up to today, inclusive (0 for a period that has not started). */
    public function jumlahHari(): int
    {
        $akhir = $this->sampai->min(Hari::ini());

        return $this->dari->greaterThan($akhir) ? 0 : (int) round($this->dari->diffInDays($akhir)) + 1;
    }

    /** @return list<string> Y-m-d of each day of the period up to today */
    public function hari(): array
    {
        $hari = [];

        for ($tanggal = $this->dari, $akhir = $this->sampai->min(Hari::ini()); $tanggal->lessThanOrEqualTo($akhir); $tanggal = $tanggal->addDay()) {
            $hari[] = $tanggal->toDateString();
        }

        return $hari;
    }

    /** @return list<CarbonImmutable> first day of each month in the period */
    public function bulanBulan(): array
    {
        $bulan = [];

        for ($awal = $this->dari; $awal->lessThanOrEqualTo($this->sampai); $awal = $awal->addMonthNoOverflow()) {
            $bulan[] = $awal;
        }

        return $bulan;
    }

    /** The previous month, or the previous year. */
    public function sebelumnya(): self
    {
        if ($this->bulan === null) {
            return self::buat($this->tahun - 1, null);
        }

        $awal = $this->dari->subMonthNoOverflow();

        return self::buat($awal->year, $awal->month);
    }

    /** @return array<int, string> */
    public static function pilihanTahun(): array
    {
        $tahun = range(Hari::ini()->year + 1, self::TAHUN_AWAL);

        return array_combine($tahun, array_map('strval', $tahun));
    }

    /** @return array<int|string, string> */
    public static function pilihanBulan(): array
    {
        $bulan = [self::SETAHUN => 'Setahun penuh'];

        foreach (range(1, 12) as $nomor) {
            $bulan[$nomor] = CarbonImmutable::create(2000, $nomor, 1)->translatedFormat('F');
        }

        return $bulan;
    }
}
