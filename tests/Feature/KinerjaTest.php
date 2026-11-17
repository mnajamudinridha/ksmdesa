<?php

namespace Tests\Feature;

use App\Enums\JenisMitra;
use App\Enums\StatusPengangkutan;
use App\Filament\Ksm\Pages\Dashboard;
use App\Filament\Ksm\Pages\Laporan\LaporanKinerjaPage;
use App\Filament\Ksm\Widgets\KemitraanStats;
use App\Filament\Ksm\Widgets\LingkunganStats;
use App\Filament\Ksm\Widgets\ProgresEkonomiSirkularStats;
use App\Models\CatatanMaterial;
use App\Models\KategoriTransaksi;
use App\Models\Ksm;
use App\Models\Mitra;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Models\TransaksiKas;
use App\Models\User;
use App\Support\Laporan\LaporanKemitraan;
use App\Support\Laporan\LaporanKinerja;
use App\Support\Periode;
use App\Support\RingkasanKinerja;
use App\Support\TerimaIuran;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BacaNilaiStat;
use Tests\Concerns\BacaUnduhanXlsx;
use Tests\TestCase;

/**
 * Phase 5C: the circular-economy indicators, the integrated performance report and partnerships.
 *
 * Fixture (today 2026-11-17, two active houses at Rp25.000/month). Fees: A November fee, no
 * received date, Rp25.000; B October fee received 3 Nov, Rp25.000; C November fee received
 * 28 Oct, Rp50.000; D December fee received 10 Nov by a petugas, Rp40.000. Cash book: income
 * 5 Nov Rp200.000 from partner "PT Peduli"; spending 6 Nov Rp150.000; spending 15 Oct Rp70.000.
 * Material: sorted 100,0 kg (2 Nov); sold 60,0 kg for Rp180.000 to buyer "Bank Sampah" (12 Nov).
 * Collection: house A collected 340,0 kg on 10 Nov (house C not collected).
 */
class KinerjaTest extends TestCase
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

        $peduli = Mitra::factory()->for($this->ksm)->create(['nama' => 'PT Peduli']);
        $pengepul = Mitra::factory()->for($this->ksm)->create(['nama' => 'Bank Sampah', 'jenis' => JenisMitra::Pengepul]);
        TransaksiKas::factory()->for($this->ksm)->masuk()->create(['tanggal' => '2026-11-05', 'nominal' => 200_000, 'mitra_id' => $peduli->id, 'kategori_transaksi_id' => KategoriTransaksi::factory()->for($this->ksm)->masuk()->create()->id]);
        TransaksiKas::factory()->for($this->ksm)->create(['tanggal' => '2026-11-06', 'nominal' => 150_000]);
        TransaksiKas::factory()->for($this->ksm)->create(['tanggal' => '2026-10-15', 'nominal' => 70_000]);

        CatatanMaterial::factory()->for($this->ksm)->create(['tanggal' => '2026-11-02', 'berat_kg' => 100]);
        CatatanMaterial::factory()->for($this->ksm)->jual(180_000)->create(['tanggal' => '2026-11-12', 'berat_kg' => 60, 'mitra_id' => $pengepul->id]);

        $ronde = Pengangkutan::factory()->for($this->ksm)->create(['petugas_id' => $petugas->id, 'tanggal' => '2026-11-10']);
        PengangkutanRincian::factory()->for($ronde)->create(['pelanggan_id' => $rumahA->id, 'status' => StatusPengangkutan::Diangkut, 'perkiraan_kg' => 340]);

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
        $this->actingAs(User::factory()->for($this->ksm)->admin()->create());
    }

    private function kinerja(): RingkasanKinerja
    {
        return new RingkasanKinerja($this->ksm->fresh());
    }

    public function test_november_indicators(): void
    {
        $indikator = $this->kinerja()->indikator(Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']));

        // penagihan: November fees by fee month (A + C = 75.000) ÷ target 50.000.
        // kemandirian: (fees by cash date 90.000 + sales 180.000) ÷ spending 150.000 — partner support left out.
        $this->assertSame(1.5, $indikator['penagihan']);
        $this->assertSame(0.5, $indikator['cakupan']);
        $this->assertSame(100 / 340, $indikator['pemulihan']);
        $this->assertSame(1.8, $indikator['kemandirian']);
    }

    public function test_fee_collection_counts_from_the_first_recorded_month_and_never_a_future_one(): void
    {
        $tahun = $this->kinerja()->indikator(Periode::dariFilter(['tahun' => '2026', 'bulan' => 'semua']));
        $desember = $this->kinerja()->indikator(Periode::dariFilter(['tahun' => '2026', 'bulan' => '12']));

        // October (B 25.000) + November (75.000) over two months of target — not Jan–Nov.
        $this->assertSame(1.0, $tahun['penagihan']);
        $this->assertNull($desember['penagihan']);
    }

    public function test_a_new_ksm_has_no_indicator_yet_instead_of_dividing_by_zero(): void
    {
        $baru = Ksm::factory()->create();

        $this->assertSame(
            ['penagihan' => null, 'cakupan' => null, 'pemulihan' => null, 'kemandirian' => null],
            (new RingkasanKinerja($baru))->indikator(Periode::bulanIni()),
        );
    }

    public function test_dashboard_card_shows_the_four_indicators(): void
    {
        $stat = $this->nilaiStat(Livewire::test(ProgresEkonomiSirkularStats::class));

        $this->assertSame(
            ['Tingkat penagihan iuran' => '150%', 'Cakupan layanan' => '50%', 'Tingkat pemulihan material' => '29%', 'Kemandirian biaya' => '180%'],
            $stat,
        );
    }

    public function test_performance_report_compares_with_october_and_exports_numbers(): void
    {
        $laporan = new LaporanKinerja($this->ksm->fresh());
        $november = Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']);

        $indikator = array_column($laporan->indikator($november)->baris, null, 'indikator');
        $ringkasan = array_column($laporan->ringkasan($november)->baris, null, 'indikator');

        // October: fees 25.000 ÷ 50.000; own income 50.000 (C, received 28 Oct) ÷ spending 70.000.
        $this->assertSame(0.5, $indikator['Tingkat penagihan iuran']['sebelumnya']);
        $this->assertSame(round((1.8 - 50_000 / 70_000) * 100, 1), $indikator['Kemandirian biaya']['perubahan']);
        $this->assertSame([470_000, 50_000, 8.4, 'rupiah'], [$ringkasan['Pemasukan']['nilai'], $ringkasan['Pemasukan']['sebelumnya'], $ringkasan['Pemasukan']['perubahan'], $ringkasan['Pemasukan']['__format']]);
        // 1.000.000 + income 470.000 − spending 150.000; October is before the cash book starts.
        $this->assertSame([1_320_000, null], [$ringkasan['Saldo akhir']['nilai'], $ringkasan['Saldo akhir']['sebelumnya']]);
        $this->assertSame([100.0, 340.0], [$ringkasan['Material terpulihkan']['nilai'], $ringkasan['Sampah terangkut']['nilai']]);
        // Partner status has no history: no "previous" figure is made up from today's count.
        $this->assertSame([2, null, null], [$ringkasan['Mitra aktif (saat ini)']['nilai'], $ringkasan['Mitra aktif (saat ini)']['sebelumnya'], $ringkasan['Mitra aktif (saat ini)']['perubahan']]);
        $this->assertFalse(ProgresEkonomiSirkularStats::isLazyLoaded(), 'Printing the report must not catch the card still loading.');

        $komponen = Livewire::test(LaporanKinerjaPage::class)
            ->callAction('unduhExcel')
            ->assertFileDownloaded('laporan-kinerja-makmurjaya-2026-11.xlsx');
        $isi = $this->bacaUnduhanXlsx($komponen);
        $baris = collect($isi['Ringkasan kinerja'])->first(fn (array $satu): bool => ($satu[1] ?? null) === 'Pemasukan');
        $this->assertSame(470_000, $baris[2]);
    }

    public function test_partnership_figures_split_support_from_sales(): void
    {
        // Spending naming the partner (written past the model, as old data could be) is not support.
        $keluar = TransaksiKas::factory()->for($this->ksm)->create(['tanggal' => '2026-11-07', 'nominal' => 33_000]);
        TransaksiKas::query()->whereKey($keluar->id)->update(['mitra_id' => Mitra::query()->where('nama', 'PT Peduli')->value('id')]);

        $kontribusi = array_column((new LaporanKemitraan($this->ksm->fresh()))->kontribusi(Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']))->baris, null, 'mitra');

        $this->assertSame([200_000, 0], [$kontribusi['PT Peduli']['dukungan'], $kontribusi['PT Peduli']['nilai']]);
        $this->assertSame([0, 60.0, 180_000], [$kontribusi['Bank Sampah']['dukungan'], $kontribusi['Bank Sampah']['jual_kg'], $kontribusi['Bank Sampah']['nilai']]);
        $this->assertSame(
            ['Mitra aktif' => '2', 'Dukungan diterima' => 'Rp200.000', 'Penjualan ke pengepul' => 'Rp180.000', 'Mitra berkontribusi' => '2'],
            $this->nilaiStat(Livewire::test(KemitraanStats::class, ['pageFilters' => ['tahun' => '2026', 'bulan' => '11']])),
        );
    }

    public function test_an_inactive_partner_that_contributed_still_appears_in_the_report(): void
    {
        Mitra::query()->where('nama', 'PT Peduli')->update(['aktif' => false]);

        $mitra = array_column((new LaporanKemitraan($this->ksm->fresh()))->kontribusi(Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']))->baris, 'mitra');
        $oktober = array_column((new LaporanKemitraan($this->ksm->fresh()))->kontribusi(Periode::dariFilter(['tahun' => '2026', 'bulan' => '10']))->baris, 'mitra');

        $this->assertSame(['Bank Sampah', 'PT Peduli (nonaktif)'], $mitra);
        $this->assertSame(['Bank Sampah'], $oktober);
    }

    public function test_dashboard_shows_the_new_cards_to_admins_only(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSeeLivewire(ProgresEkonomiSirkularStats::class)
            ->assertSeeLivewire(LingkunganStats::class)
            ->assertSeeLivewire(KemitraanStats::class);

        $this->actingAs(User::factory()->for($this->ksm)->petugas()->create());

        Livewire::test(Dashboard::class)
            ->assertDontSeeLivewire(ProgresEkonomiSirkularStats::class)
            ->assertDontSeeLivewire(LingkunganStats::class);
    }

    public function test_performance_figures_stay_within_the_given_ksm_even_outside_its_panel(): void
    {
        $this->diKsmLain();

        // Filament's tenant scope only runs while the ksm panel is current.
        Filament::setCurrentPanel('admin');

        $this->assertSame(1.8, $this->kinerja()->indikator(Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']))['kemandirian']);
        $this->assertSame(['Bank Sampah', 'PT Peduli'], array_column((new LaporanKemitraan($this->ksm->fresh()))->kontribusi(Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']))->baris, 'mitra'));
    }

    /** Another KSM with sales, spending and a partner in November. */
    private function diKsmLain(): void
    {
        Filament::setTenant(null, isQuiet: true);

        try {
            $lain = Ksm::factory()->create();
            $mitra = Mitra::factory()->for($lain)->create(['nama' => 'Mitra KSM lain', 'jenis' => JenisMitra::Pengepul]);
            CatatanMaterial::factory()->for($lain)->jual(900_000)->create(['tanggal' => '2026-11-05', 'mitra_id' => $mitra->id]);
            TransaksiKas::factory()->for($lain)->create(['tanggal' => '2026-11-05', 'nominal' => 5_000_000]);
        } finally {
            Filament::setTenant($this->ksm, isQuiet: true);
        }
    }
}
