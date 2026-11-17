<?php

namespace Tests\Feature;

use App\Enums\JenisTransaksi;
use App\Filament\Ksm\Pages\Analitik\AnalitikKeuangan;
use App\Filament\Ksm\Pages\Laporan\LaporanKeuanganPage;
use App\Filament\Ksm\Widgets\KeuanganStats;
use App\Filament\Ksm\Widgets\KomposisiPemasukanChart;
use App\Models\KategoriTransaksi;
use App\Models\Ksm;
use App\Models\Mitra;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\TransaksiKas;
use App\Models\User;
use App\Support\Laporan\LaporanKeuangan;
use App\Support\Periode;
use App\Support\RingkasanKas;
use App\Support\TerimaIuran;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BacaNilaiStat;
use Tests\Concerns\BacaUnduhanXlsx;
use Tests\TestCase;

/**
 * Phase 5B: the cash summary — fees by the day the cash came in, manual entries, and the
 * balance from the cash book's start date.
 *
 * Fixture (today 2026-11-17; opening balance Rp1.000.000 on 1 Nov). Fees: A November fee,
 * no received date (imported), Rp25.000; B October fee received 3 Nov, Rp25.000; C November
 * fee received 28 Oct, Rp50.000; D December fee received 10 Nov by a petugas (not yet handed
 * over), Rp40.000. Entries: income 5 Nov Rp200.000 "Dukungan mitra" from partner M; spending
 * 6 Nov Rp150.000 "BBM"; spending 15 Oct Rp70.000 "BBM". Another KSM: spending 6 Nov
 * Rp999.000 and a November fee Rp888.000.
 */
class RingkasanKasTest extends TestCase
{
    use BacaNilaiStat;
    use BacaUnduhanXlsx;
    use RefreshDatabase;

    private Ksm $ksm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya', 'saldo_awal' => 1_000_000, 'kas_mulai' => '2026-11-01']);
        $petugas = User::factory()->for($this->ksm)->petugas()->create();
        $rumahA = Pelanggan::factory()->for($this->ksm)->create(['petugas_id' => $petugas->id]);
        $rumahC = Pelanggan::factory()->for($this->ksm)->create();

        PembayaranIuran::factory()->for($rumahA)->create(['periode' => '2026-11-01', 'dibayar_pada' => null, 'nominal' => 25_000]);
        PembayaranIuran::factory()->for($rumahA)->create(['periode' => '2026-10-01', 'dibayar_pada' => '2026-11-03', 'nominal' => 25_000]);
        PembayaranIuran::factory()->for($rumahC)->create(['periode' => '2026-11-01', 'dibayar_pada' => '2026-10-28', 'nominal' => 50_000]);
        TerimaIuran::catat($rumahA, $petugas, ['2026-12-01'], 40_000, '2026-11-10');

        $dukungan = KategoriTransaksi::factory()->for($this->ksm)->masuk()->create(['nama' => 'Dukungan mitra']);
        $bbm = KategoriTransaksi::factory()->for($this->ksm)->create(['nama' => 'BBM']);
        $mitra = Mitra::factory()->for($this->ksm)->create(['nama' => 'PT Peduli']);
        TransaksiKas::factory()->for($this->ksm)->masuk()->create(['tanggal' => '2026-11-05', 'nominal' => 200_000, 'kategori_transaksi_id' => $dukungan->id, 'mitra_id' => $mitra->id]);
        TransaksiKas::factory()->for($this->ksm)->create(['tanggal' => '2026-11-06', 'nominal' => 150_000, 'kategori_transaksi_id' => $bbm->id]);
        TransaksiKas::factory()->for($this->ksm)->create(['tanggal' => '2026-10-15', 'nominal' => 70_000, 'kategori_transaksi_id' => $bbm->id]);

        $this->diKsmLain(function (): void {
            $lain = Ksm::factory()->create();
            TransaksiKas::factory()->for($lain)->create(['tanggal' => '2026-11-06', 'nominal' => 999_000]);
            PembayaranIuran::factory()->for(Pelanggan::factory()->for($lain))->create(['periode' => '2026-11-01', 'dibayar_pada' => '2026-11-04', 'nominal' => 888_000]);
        });

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
        $this->actingAs(User::factory()->for($this->ksm)->admin()->create());
    }

    private function diKsmLain(callable $buat): mixed
    {
        Filament::setTenant(null, isQuiet: true);

        try {
            return $buat();
        } finally {
            Filament::setTenant($this->ksm, isQuiet: true);
        }
    }

    private function kas(): RingkasanKas
    {
        return new RingkasanKas($this->ksm->fresh());
    }

    public function test_november_counts_fees_on_the_day_the_cash_came_in(): void
    {
        $kas = $this->kas();

        $this->assertSame(90_000, $kas->iuran('2026-11-01', '2026-11-30'));
        $this->assertSame(290_000, $kas->masuk('2026-11-01', '2026-11-30'));
        $this->assertSame(150_000, $kas->keluar('2026-11-01', '2026-11-30'));
        $this->assertSame(['Iuran' => 90_000, 'Dukungan mitra' => 200_000], $kas->pemasukanPerSumber('2026-11-01', '2026-11-30'));
    }

    public function test_balance_starts_on_the_start_date_and_is_unknown_before_it(): void
    {
        $kas = $this->kas();

        $this->assertSame(1_000_000, $kas->saldoAwal('2026-11-01'));
        $this->assertSame(1_140_000, $kas->saldoAkhir('2026-11-30'));
        $this->assertNull($kas->saldoAkhir('2026-10-31'));
    }

    public function test_monthly_rows_report_flows_before_the_start_and_carry_the_balance_after(): void
    {
        $bulan = $this->kas()->perBulan(Periode::dariFilter(['tahun' => '2026', 'bulan' => 'semua'])->bulanBulan());

        $perBulan = array_column($bulan, null, 'bulan');
        $this->assertSame(['saldo_awal' => null, 'iuran' => 50_000, 'keluar' => 70_000, 'saldo_akhir' => null], array_intersect_key($perBulan['2026-10'], array_flip(['saldo_awal', 'iuran', 'keluar', 'saldo_akhir'])));
        $this->assertSame([1_000_000, 90_000, 200_000, 150_000, 140_000, 1_140_000], array_values(array_intersect_key($perBulan['2026-11'], array_flip(['saldo_awal', 'iuran', 'masuk_lain', 'keluar', 'surplus', 'saldo_akhir']))));
        $this->assertSame([1_140_000, 1_140_000], [$perBulan['2026-12']['saldo_awal'], $perBulan['2026-12']['saldo_akhir']]);
    }

    public function test_without_a_start_date_the_balance_counts_from_the_first_record(): void
    {
        $this->ksm->update(['kas_mulai' => null]);

        $this->assertSame(1_120_000, $this->kas()->saldoAkhir('2026-11-30'));
    }

    public function test_finance_stats_show_the_balance_and_the_cash_still_with_petugas_only_for_a_period_including_today(): void
    {
        $november = Livewire::test(KeuanganStats::class, ['pageFilters' => ['tahun' => '2026', 'bulan' => '11']]);
        $desember = Livewire::test(KeuanganStats::class, ['pageFilters' => ['tahun' => '2026', 'bulan' => '12']]);

        $this->assertSame(
            ['Pemasukan' => 'Rp290.000', 'Pengeluaran' => 'Rp150.000', 'Surplus' => 'Rp140.000', 'Saldo kas' => 'Rp1.140.000'],
            $this->nilaiStat($november),
        );
        $november->assertSee('termasuk Rp40.000 di tangan petugas');
        $desember->assertDontSee('di tangan petugas');
    }

    public function test_october_shows_no_balance_because_it_is_before_the_start_date(): void
    {
        $stat = $this->nilaiStat(Livewire::test(KeuanganStats::class, ['pageFilters' => ['tahun' => '2026', 'bulan' => '10']]));

        $this->assertSame(['Rp50.000', 'Rp70.000', 'Rp20.000', '—'], [$stat['Pemasukan'], $stat['Pengeluaran'], $stat['Defisit'], $stat['Saldo kas']]);
    }

    public function test_income_chart_has_one_slice_per_source_of_this_ksm(): void
    {
        $grafik = Livewire::test(KomposisiPemasukanChart::class, ['pageFilters' => ['tahun' => '2026', 'bulan' => '11']])->instance();
        $data = (fn (): array => $this->getData())->call($grafik);

        $this->assertSame(['Iuran', 'Dukungan mitra'], $data['labels']);
        $this->assertSame([90_000, 200_000], $data['datasets'][0]['data']);
    }

    public function test_excel_report_matches_the_cash_summary_and_keeps_user_text_out_of_formulas(): void
    {
        TransaksiKas::query()->where('nominal', 150_000)->update(['uraian' => '=1+1']);

        $komponen = Livewire::test(LaporanKeuanganPage::class)
            ->callAction('unduhExcel')
            ->assertFileDownloaded('laporan-keuangan-makmurjaya-2026-11.xlsx');

        $isi = $this->bacaUnduhanXlsx($komponen);
        $this->assertSame(['Arus kas bulanan', 'Pemasukan per sumber', 'Pengeluaran per kategori', 'Rincian transaksi kas'], array_keys($isi));
        $november = $this->barisBerlabel($isi['Arus kas bulanan'], 'November 2026');
        $this->assertSame([90_000, 0, 1_140_000], [$november[2], $november[3], $november[7]]);
        $this->assertContains('=1+1', array_column($isi['Rincian transaksi kas'], 3));
        foreach ($this->xmlLembarXlsx($komponen) as $lembar => $xml) {
            $this->assertStringNotContainsString('<f>', $xml, "{$lembar} berisi rumus");
        }
    }

    public function test_report_sections_list_this_ksms_entries_with_shares(): void
    {
        $laporan = new LaporanKeuangan($this->ksm->fresh());
        $november = Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']);

        $pengeluaran = $laporan->pengeluaran($november);
        $rincian = $laporan->rincian($november);

        $this->assertSame([['kategori' => 'BBM', 'jumlah' => 1, 'nominal' => 150_000, 'porsi' => 1.0]], $pengeluaran->baris);
        $this->assertSame(['2026-11-05', '2026-11-06'], array_column($rincian->baris, 'tanggal'));
        $this->assertSame(['PT Peduli', null], array_column($rincian->baris, 'mitra'));
        $this->assertSame([200_000, 150_000], [$rincian->total['masuk'], $rincian->total['keluar']]);
        // Fee "transactions" = payments received in the period: A, B and D (C came in October).
        $this->assertSame(['sumber' => 'Iuran', 'jumlah' => 3, 'nominal' => 90_000], array_intersect_key($laporan->pemasukan($november)->baris[0], array_flip(['sumber', 'jumlah', 'nominal'])));
    }

    public function test_empty_section_shows_no_total_row_on_screen_or_in_excel(): void
    {
        $komponen = Livewire::withQueryParams(['filters' => ['tahun' => '2026', 'bulan' => '9']])
            ->test(LaporanKeuanganPage::class)
            ->callAction('unduhExcel');

        $rincian = $this->bacaUnduhanXlsx($komponen)['Rincian transaksi kas'];
        $this->assertNotContains('Jumlah', array_column($rincian, 0));
        $this->assertSame([], (new LaporanKeuangan($this->ksm->fresh()))->rincian(Periode::dariFilter(['tahun' => '2026', 'bulan' => '9']))->rekamanTabel());
    }

    public function test_admin_opens_finance_pages_and_petugas_and_warga_are_forbidden(): void
    {
        $this->get('http://makmurjaya.ksmdesa.test/analitik/keuangan')->assertOk()->assertSee('Analitik Keuangan');
        $this->get('http://makmurjaya.ksmdesa.test/laporan/keuangan')->assertOk()->assertSee('Laporan Keuangan');
        $this->assertSame('http://makmurjaya.ksmdesa.test/analitik/keuangan', AnalitikKeuangan::getUrl(tenant: $this->ksm));

        foreach ([User::factory()->for($this->ksm)->petugas()->create(), Pelanggan::factory()->for($this->ksm)->create()->akunWarga] as $pengguna) {
            $this->flushSession();
            $this->actingAs($pengguna);

            $this->get('http://makmurjaya.ksmdesa.test/analitik/keuangan')->assertForbidden();
            $this->get('http://makmurjaya.ksmdesa.test/laporan/keuangan')->assertForbidden();
            $this->assertFalse(KeuanganStats::canView());
        }
    }

    public function test_cash_summary_stays_within_the_given_ksm_even_outside_its_panel(): void
    {
        // Filament's tenant scope only runs while the ksm panel is current.
        Filament::setCurrentPanel('admin');

        $kas = $this->kas();
        $rincian = (new LaporanKeuangan($this->ksm->fresh()))->rincian(Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']));

        $this->assertSame([90_000, 150_000], [$kas->iuran('2026-11-01', '2026-11-30'), $kas->keluar('2026-11-01', '2026-11-30')]);
        $this->assertSame([200_000, 150_000], [$rincian->total['masuk'], $rincian->total['keluar']]);
        $this->assertSame(JenisTransaksi::Keluar, TransaksiKas::query()->whereBelongsTo($this->ksm)->where('nominal', 150_000)->sole()->jenis);
    }
}
