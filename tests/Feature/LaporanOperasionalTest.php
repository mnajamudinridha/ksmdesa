<?php

namespace Tests\Feature;

use App\Enums\StatusPengangkutan;
use App\Filament\Ksm\Pages\Analitik\AnalitikOperasional;
use App\Filament\Ksm\Pages\Dashboard;
use App\Filament\Ksm\Pages\Laporan\LaporanOperasionalPage;
use App\Filament\Ksm\Widgets\DaftarTugasHariIni;
use App\Filament\Ksm\Widgets\KelengkapanDataStats;
use App\Filament\Ksm\Widgets\Laporan\TabelRealisasiPerRt;
use App\Filament\Ksm\Widgets\OperasionalPeriodeStats;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\AkunWarga;
use App\Support\Laporan\LaporanOperasional;
use App\Support\Periode;
use App\Support\RingkasanPengangkutan;
use App\Support\TerimaIuran;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BacaNilaiStat;
use Tests\Concerns\BacaUnduhanXlsx;
use Tests\TestCase;

/**
 * Phase 5A: Analitik › Operasional and Laporan › Operasional — coverage per RT, petugas
 * performance, daily rounds; Excel and print come from the same figures as the screen.
 *
 * Fixture (today 2026-11-17 WITA): RT 1 (10 KK) with active p1, p2 and inactive p3; RT 2
 * without customers; active p4 without an RT. Rounds — petugas Ani 3 Nov: p1 2,0 kg, p4 1,5 kg;
 * Ani 10 Nov: p1 3,0 kg, p2 not reachable, p3 5,0 kg; Budi 17 Nov: p1 4,0 kg; Ani 31 Oct:
 * p2 50,0 kg (outside November). Another KSM: a round on 10 Nov with 99,0 kg.
 */
class LaporanOperasionalTest extends TestCase
{
    use BacaNilaiStat;
    use BacaUnduhanXlsx;
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    private User $ani;

    private User $budi;

    private Pelanggan $p1;

    private Pelanggan $p2;

    private Pelanggan $p4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya', 'nama' => 'KSM Makmur Jaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->ani = User::factory()->for($this->ksm)->petugas()->create(['name' => 'Ani']);
        $this->budi = User::factory()->for($this->ksm)->petugas()->create(['name' => 'Budi']);

        $rt1 = Wilayah::factory()->for($this->ksm)->create(['rt' => 1, 'rw' => 1, 'jumlah_kk' => 10]);
        Wilayah::factory()->for($this->ksm)->create(['rt' => 2, 'rw' => 1]);
        $this->p1 = Pelanggan::factory()->for($this->ksm)->create(['wilayah_id' => $rt1->id, 'petugas_id' => $this->ani->id]);
        $this->p2 = Pelanggan::factory()->for($this->ksm)->create(['wilayah_id' => $rt1->id, 'petugas_id' => $this->ani->id]);
        $p3 = Pelanggan::factory()->for($this->ksm)->nonaktif()->create(['wilayah_id' => $rt1->id]);
        $this->p4 = Pelanggan::factory()->for($this->ksm)->create(['petugas_id' => $this->budi->id]);

        $this->ronde($this->ani, '2026-11-03', [[$this->p1, StatusPengangkutan::Diangkut, 2.0], [$this->p4, StatusPengangkutan::Diangkut, 1.5]]);
        $this->ronde($this->ani, '2026-11-10', [[$this->p1, StatusPengangkutan::Diangkut, 3.0], [$this->p2, StatusPengangkutan::TidakBisaDiakses, null], [$p3, StatusPengangkutan::Diangkut, 5.0]]);
        $this->ronde($this->budi, '2026-11-17', [[$this->p1, StatusPengangkutan::Diangkut, 4.0]]);
        $this->ronde($this->ani, '2026-10-31', [[$this->p2, StatusPengangkutan::Diangkut, 50.0]]);

        $this->diKsmLain(function (): void {
            $lain = Ksm::factory()->create();
            $petugasLain = User::factory()->for($lain)->petugas()->create(['name' => 'Petugas Lain']);
            $rumahLain = Pelanggan::factory()->for($lain)->create(['petugas_id' => $petugasLain->id]);
            $this->ronde($petugasLain, '2026-11-10', [[$rumahLain, StatusPengangkutan::Diangkut, 99.0]], $lain);
        });

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
        $this->actingAs($this->admin);
    }

    /** @param  list<array{0: Pelanggan, 1: StatusPengangkutan, 2: ?float}>  $rincian */
    private function ronde(User $petugas, string $tanggal, array $rincian, ?Ksm $ksm = null): void
    {
        $ronde = Pengangkutan::factory()->for($ksm ?? $this->ksm)->create(['petugas_id' => $petugas->id, 'tanggal' => $tanggal]);

        foreach ($rincian as [$pelanggan, $status, $kg]) {
            PengangkutanRincian::factory()->for($ronde)->create(['pelanggan_id' => $pelanggan->id, 'status' => $status, 'perkiraan_kg' => $kg]);
        }
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

    private function november(): Periode
    {
        return Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']);
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, array<string, mixed>>
     */
    private function perLabel(array $baris, string $kunci): array
    {
        return array_column($baris, null, $kunci);
    }

    public function test_per_rt_rows_count_coverage_of_active_houses_and_reconcile_with_the_ksm_total(): void
    {
        $tabel = (new LaporanOperasional($this->ksm))->perWilayah($this->november());

        $baris = $this->perLabel($tabel->baris, 'wilayah');
        $this->assertSame(['RT 01 / RW 01', 'RT 02 / RW 01', 'Tanpa RT', 'Pelanggan nonaktif'], array_keys($baris));

        $this->assertSame(
            ['rumah_aktif' => 2, 'terlayani' => 1, 'cakupan' => 0.5, 'penetrasi' => 0.2, 'kunjungan' => 3, 'rata_mingguan' => 0.6, 'tidak_bisa_diakses' => 1, 'kg' => 9.0],
            array_intersect_key($baris['RT 01 / RW 01'], array_flip(['rumah_aktif', 'terlayani', 'cakupan', 'penetrasi', 'kunjungan', 'rata_mingguan', 'tidak_bisa_diakses', 'kg'])),
        );
        $this->assertSame([0, 0, null, 0, 0.0], [$baris['RT 02 / RW 01']['rumah_aktif'], $baris['RT 02 / RW 01']['terlayani'], $baris['RT 02 / RW 01']['cakupan'], $baris['RT 02 / RW 01']['kunjungan'], $baris['RT 02 / RW 01']['kg']]);
        $this->assertSame([1, 1, 1.0, 1.5], [$baris['Tanpa RT']['rumah_aktif'], $baris['Tanpa RT']['terlayani'], $baris['Tanpa RT']['cakupan'], $baris['Tanpa RT']['kg']]);
        $this->assertSame([null, 1, 5.0], [$baris['Pelanggan nonaktif']['rumah_aktif'], $baris['Pelanggan nonaktif']['kunjungan'], $baris['Pelanggan nonaktif']['kg']]);

        $this->assertSame([3, 2, 5, 1, 15.5], [$tabel->total['rumah_aktif'], $tabel->total['terlayani'], $tabel->total['kunjungan'], $tabel->total['tidak_bisa_diakses'], $tabel->total['kg']]);
        $this->assertSame(2 / 3, $tabel->total['cakupan']);
        $this->assertSame(15.5, (new RingkasanPengangkutan($this->ksm))->berat('2026-11-01', '2026-11-30'));
    }

    public function test_report_figures_stay_within_the_given_ksm_even_outside_its_panel(): void
    {
        // Filament's tenant scope only runs while the ksm panel is current: the explicit
        // KSM filter in the summaries must hold on its own.
        Filament::setCurrentPanel('admin');

        $laporan = new LaporanOperasional($this->ksm);

        $this->assertSame(['2026-11-03', '2026-11-10', '2026-11-17'], array_column($laporan->perHari($this->november())->baris, 'tanggal'));
        $this->assertSame(15.5, $laporan->perWilayah($this->november())->total['kg']);
        $this->assertSame(['Ani', 'Budi'], array_column($laporan->perPetugas($this->november())->baris, 'petugas'));
    }

    public function test_operational_stats_show_rt_and_household_coverage_for_the_period(): void
    {
        $widget = Livewire::test(OperasionalPeriodeStats::class, ['pageFilters' => ['tahun' => '2026', 'bulan' => '11']]);

        $this->assertSame(
            ['RT terlayani' => '1 dari 2 RT', 'Cakupan layanan' => '67%', 'Hari pengangkutan' => '3', 'Petugas aktif' => '2', 'Perkiraan sampah' => '15,5 kg'],
            $this->nilaiStat($widget),
        );
        $widget->assertSee('2 dari 3 rumah aktif diangkut ≥1×');
    }

    public function test_petugas_rows_count_only_their_rounds_and_the_cash_they_received_in_the_period(): void
    {
        TerimaIuran::catat($this->p1, $this->ani, ['2026-11-01'], 50_000, '2026-11-05');
        // Paid in advance for December, received in November: counts in November.
        TerimaIuran::catat($this->p1, $this->ani, ['2026-12-01'], 20_000, '2026-11-06');
        TerimaIuran::catat($this->p2, $this->ani, ['2026-10-01'], 25_000, '2026-10-30');

        $tabel = (new LaporanOperasional($this->ksm))->perPetugas($this->november());

        $baris = $this->perLabel($tabel->baris, 'petugas');
        $this->assertSame(['Ani', 'Budi'], array_keys($baris));
        $this->assertSame(
            ['rumah_tugas' => 2, 'hari_bertugas' => 2, 'rumah_dicatat' => 5, 'diangkut' => 4, 'kg' => 11.5, 'iuran_diterima' => 70_000, 'belum_disetor' => 95_000],
            array_intersect_key($baris['Ani'], array_flip(['rumah_tugas', 'hari_bertugas', 'rumah_dicatat', 'diangkut', 'kg', 'iuran_diterima', 'belum_disetor'])),
        );
        $this->assertSame([1, 1, 1, 1, 4.0, 0], [$baris['Budi']['rumah_tugas'], $baris['Budi']['hari_bertugas'], $baris['Budi']['rumah_dicatat'], $baris['Budi']['diangkut'], $baris['Budi']['kg'], $baris['Budi']['iuran_diterima']]);
    }

    public function test_daily_rows_cover_only_this_ksms_rounds_in_the_period(): void
    {
        $tabel = (new LaporanOperasional($this->ksm))->perHari($this->november());

        $baris = $this->perLabel($tabel->baris, 'tanggal');
        $this->assertSame(['2026-11-03', '2026-11-10', '2026-11-17'], array_keys($baris));
        $this->assertSame(
            ['ronde' => 1, 'dicatat' => 3, 'diangkut' => 2, 'tidak_ada_sampah' => 0, 'tidak_bisa_diakses' => 1, 'kg' => 8.0],
            array_intersect_key($baris['2026-11-10'], array_flip(['ronde', 'dicatat', 'diangkut', 'tidak_ada_sampah', 'tidak_bisa_diakses', 'kg'])),
        );
        $this->assertSame(15.5, $tabel->total['kg']);
    }

    public function test_excel_download_has_one_sheet_per_section_with_numeric_cells_matching_the_screen(): void
    {
        $komponen = Livewire::test(LaporanOperasionalPage::class)
            ->callAction('unduhExcel')
            ->assertFileDownloaded('laporan-operasional-makmurjaya-2026-11.xlsx');

        $isi = $this->bacaUnduhanXlsx($komponen);

        $this->assertSame(['Realisasi pengangkutan per RT', 'Kinerja petugas', 'Rekap harian'], array_keys($isi));
        $rt1 = $this->barisBerlabel($isi['Realisasi pengangkutan per RT'], 'RT 01 / RW 01');
        $jumlah = $this->barisBerlabel($isi['Realisasi pengangkutan per RT'], 'Jumlah');
        $this->assertEqualsWithDelta(0.5, $rt1[4], 0.0001);
        $this->assertEqualsWithDelta(9.0, $rt1[9], 0.0001);
        $this->assertIsNotString($rt1[9]);
        $this->assertEqualsWithDelta(15.5, $jumlah[9], 0.0001);
        $this->assertSame('Periode: November 2026', $isi['Realisasi pengangkutan per RT'][2][0]);
    }

    public function test_excel_writes_a_name_starting_with_equals_as_text_not_as_a_formula(): void
    {
        // Any petugas can rename themselves in "Profil saya".
        $this->budi->update(['name' => '=HYPERLINK("http://contoh.test","klik")']);

        $komponen = Livewire::test(LaporanOperasionalPage::class)->callAction('unduhExcel');

        $petugas = array_column($this->bacaUnduhanXlsx($komponen)['Kinerja petugas'], 0);
        $this->assertContains('=HYPERLINK("http://contoh.test","klik")', $petugas);
        foreach ($this->xmlLembarXlsx($komponen) as $lembar => $xml) {
            $this->assertStringNotContainsString('<f>', $xml, "{$lembar} berisi rumus");
        }
    }

    public function test_tampered_period_filter_shows_this_month_instead_of_failing(): void
    {
        Livewire::withQueryParams(['filters' => ['tahun' => 'x', 'bulan' => '99']])
            ->test(LaporanOperasionalPage::class)
            ->assertOk()
            ->assertSee('Periode: November 2026');

        Livewire::test(TabelRealisasiPerRt::class, ['pageFilters' => ['tahun' => ['2026'], 'bulan' => '-1']])
            ->assertOk()
            ->assertSee('RT 01 / RW 01')
            ->assertSee('9,0 kg');
    }

    public function test_print_action_opens_the_print_dialog_and_the_letterhead_escapes_the_ksm_name(): void
    {
        $this->ksm->update(['nama' => 'KSM <b>Makmur</b>']);

        $this->assertFalse(TabelRealisasiPerRt::isLazyLoaded(), 'Printing must not catch a table that is still loading.');

        Livewire::test(LaporanOperasionalPage::class)
            ->assertActionExists('cetak', fn (Action $action): bool => $action->getAlpineClickHandler() === 'window.print()')
            ->assertSeeHtml('class="ksm-kop-cetak"')
            ->assertSee('KSM <b>Makmur</b>')
            ->assertDontSeeHtml('<b>Makmur</b>');
    }

    public function test_petugas_and_warga_are_forbidden_from_analytics_and_reports(): void
    {
        $warga = $this->p1->akunWarga;

        foreach ([$this->ani, $warga] as $pengguna) {
            $this->flushSession();
            $this->actingAs($pengguna);

            $this->get('http://makmurjaya.ksmdesa.test/analitik/operasional')->assertForbidden();
            $this->get('http://makmurjaya.ksmdesa.test/laporan/operasional')->assertForbidden();
            $this->assertFalse(TabelRealisasiPerRt::canView());
            $this->assertFalse(OperasionalPeriodeStats::canView());
        }
    }

    public function test_admin_opens_both_pages(): void
    {
        $this->get('http://makmurjaya.ksmdesa.test/analitik/operasional')->assertOk()->assertSee('Analitik Operasional');
        $this->get('http://makmurjaya.ksmdesa.test/laporan/operasional')->assertOk()->assertSee('Laporan Operasional');
        $this->assertSame('http://makmurjaya.ksmdesa.test/analitik/operasional', AnalitikOperasional::getUrl(tenant: $this->ksm));
    }

    public function test_dashboard_shows_data_completeness_and_keeps_report_widgets_off(): void
    {
        AkunWarga::sinkronkanNomor($this->p1, '081200001111');
        $this->p2->update(['alamat' => 'Jl. Mawar 2']);

        Livewire::test(Dashboard::class)
            ->assertSeeLivewire(KelengkapanDataStats::class)
            ->assertDontSeeLivewire(TabelRealisasiPerRt::class)
            ->assertDontSeeLivewire(OperasionalPeriodeStats::class);

        $this->assertSame(
            ['Tanpa RT/RW' => '1', 'Tanpa nomor HP' => '2', 'Tanpa lokasi' => '2'],
            $this->nilaiStat(Livewire::test(KelengkapanDataStats::class)),
        );

        $this->actingAs($this->ani);
        Livewire::test(Dashboard::class)
            ->assertSeeLivewire(DaftarTugasHariIni::class)
            ->assertDontSeeLivewire(KelengkapanDataStats::class);
    }
}
