<?php

namespace Tests\Feature;

use App\Support\Periode;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The report period comes from page filters that Filament does not validate and that anyone
 * can edit in the URL: it must follow the village calendar and never throw.
 */
class PeriodeTest extends TestCase
{
    public function test_without_filters_the_period_is_this_village_month_not_the_utc_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-30 23:30', 'Asia/Makassar'));
        $november = Periode::dariFilter(null);

        // 07:00 WITA on 1 December is still 30 November in UTC.
        $this->travelTo(CarbonImmutable::parse('2026-12-01 07:00', 'Asia/Makassar'));
        $desember = Periode::dariFilter(null);

        $this->assertSame(['2026-11-01', '2026-11-30'], [$november->dariString(), $november->sampaiString()]);
        $this->assertSame(['2026-12-01', '2026-12-31'], [$desember->dariString(), $desember->sampaiString()]);
        $this->assertSame('Desember 2026', $desember->label());
    }

    public function test_whole_year_filter_spans_january_to_december(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $periode = Periode::dariFilter(['tahun' => '2025', 'bulan' => 'semua']);

        $this->assertSame(['2025-01-01', '2025-12-31'], [$periode->dariString(), $periode->sampaiString()]);
        $this->assertSame('Tahun 2025', $periode->label());
        $this->assertSame('2025', $periode->kunci());
        $this->assertCount(12, $periode->bulanBulan());
    }

    public function test_month_filter_sent_as_strings_selects_that_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $periode = Periode::dariFilter(['tahun' => '2026', 'bulan' => '2']);

        $this->assertSame(['2026-02-01', '2026-02-28'], [$periode->dariString(), $periode->sampaiString()]);
        $this->assertSame('2026-02', $periode->kunci());
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function filterRusak(): array
    {
        return [
            'tahun huruf' => [['tahun' => 'abc', 'bulan' => '11']],
            'tahun terlalu lama' => [['tahun' => '1999', 'bulan' => '11']],
            'tahun terlalu jauh' => [['tahun' => '2028', 'bulan' => '11']],
            'tahun berisi sisipan' => [['tahun' => '2026 OR 1=1', 'bulan' => '11']],
            'tahun berupa array' => [['tahun' => ['2026'], 'bulan' => '11']],
            'bulan 13' => [['tahun' => '2026', 'bulan' => '13']],
            'bulan 0' => [['tahun' => '2026', 'bulan' => '0']],
            'bulan negatif' => [['tahun' => '2026', 'bulan' => '-1']],
            'kata setahun dengan spasi' => [['tahun' => '2026', 'bulan' => 'semua ']],
            'bulan berupa array' => [['tahun' => '2026', 'bulan' => ['11']]],
        ];
    }

    /** @param  array<string, mixed>  $filter */
    #[DataProvider('filterRusak')]
    public function test_tampered_filter_falls_back_to_this_month(array $filter): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $periode = Periode::dariFilter($filter);

        $this->assertSame(['2026-11-01', '2026-11-30'], [$periode->dariString(), $periode->sampaiString()]);
    }

    public function test_elapsed_days_stop_at_today_and_are_zero_for_a_future_month(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $november = Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']);

        $this->assertSame(17, $november->jumlahHari());
        $this->assertSame('2026-11-17', $november->hari()[16]);
        $this->assertCount(17, $november->hari());
        $this->assertSame(31, Periode::dariFilter(['tahun' => '2026', 'bulan' => '10'])->jumlahHari());
        $this->assertSame(0, Periode::dariFilter(['tahun' => '2026', 'bulan' => '12'])->jumlahHari());
    }

    public function test_previous_period_crosses_the_year_boundary(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $sebelumJanuari = Periode::dariFilter(['tahun' => '2026', 'bulan' => '1'])->sebelumnya();
        $sebelumTahun = Periode::dariFilter(['tahun' => '2026', 'bulan' => 'semua'])->sebelumnya();

        $this->assertSame(['2025-12-01', '2025-12-31'], [$sebelumJanuari->dariString(), $sebelumJanuari->sampaiString()]);
        $this->assertSame(['2025-01-01', '2025-12-31'], [$sebelumTahun->dariString(), $sebelumTahun->sampaiString()]);
    }
}
