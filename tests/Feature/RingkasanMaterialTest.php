<?php

namespace Tests\Feature;

use App\Enums\AktivitasMaterial;
use App\Enums\JenisMitra;
use App\Enums\KelompokMaterial;
use App\Enums\StatusPengangkutan;
use App\Filament\Ksm\Pages\Laporan\LaporanLingkunganPage;
use App\Filament\Ksm\Widgets\Laporan\TabelMaterialPerJenis;
use App\Filament\Ksm\Widgets\LingkunganStats;
use App\Models\CatatanMaterial;
use App\Models\JenisMaterial;
use App\Models\Ksm;
use App\Models\Mitra;
use App\Models\Pelanggan;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Models\TransaksiKas;
use App\Models\User;
use App\Support\Laporan\LaporanKeuangan;
use App\Support\Periode;
use App\Support\RingkasanKas;
use App\Support\RingkasanMaterial;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BacaNilaiStat;
use Tests\Concerns\BacaUnduhanXlsx;
use Tests\TestCase;

/**
 * Phase 5C: material figures and material sales as cash income.
 *
 * Fixture (today 2026-11-17): November — sorted Plastik PET 100,0 kg (2 Nov), sorted Kardus
 * 40,0 kg (4 Nov), processed Organik 30,0 kg into "kompos 12 kg" (8 Nov), sold Plastik PET
 * 60,0 kg for Rp180.000 to buyer "Bank Sampah Lestari" (12 Nov); October — sorted Plastik PET
 * 20,0 kg. Collected waste in November: 340,0 kg. Another KSM: sorted 999,0 kg in November.
 */
class RingkasanMaterialTest extends TestCase
{
    use BacaNilaiStat;
    use BacaUnduhanXlsx;
    use RefreshDatabase;

    private Ksm $ksm;

    private CatatanMaterial $penjualan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $pet = JenisMaterial::factory()->for($this->ksm)->create(['nama' => 'Plastik PET', 'kelompok' => KelompokMaterial::Plastik]);
        $kardus = JenisMaterial::factory()->for($this->ksm)->create(['nama' => 'Kardus', 'kelompok' => KelompokMaterial::Kertas]);
        $organik = JenisMaterial::factory()->for($this->ksm)->create(['nama' => 'Sisa makanan', 'kelompok' => KelompokMaterial::Organik]);
        $pengepul = Mitra::factory()->for($this->ksm)->create(['nama' => 'Bank Sampah Lestari', 'jenis' => JenisMitra::Pengepul]);

        CatatanMaterial::factory()->for($this->ksm)->create(['tanggal' => '2026-11-02', 'jenis_material_id' => $pet->id, 'berat_kg' => 100]);
        CatatanMaterial::factory()->for($this->ksm)->create(['tanggal' => '2026-11-04', 'jenis_material_id' => $kardus->id, 'berat_kg' => 40]);
        CatatanMaterial::factory()->for($this->ksm)->olah()->create(['tanggal' => '2026-11-08', 'jenis_material_id' => $organik->id, 'berat_kg' => 30, 'hasil_olah' => 'kompos 12 kg']);
        $this->penjualan = CatatanMaterial::factory()->for($this->ksm)->jual(180_000)->create(['tanggal' => '2026-11-12', 'jenis_material_id' => $pet->id, 'berat_kg' => 60, 'mitra_id' => $pengepul->id]);
        CatatanMaterial::factory()->for($this->ksm)->create(['tanggal' => '2026-10-20', 'jenis_material_id' => $pet->id, 'berat_kg' => 20]);

        $ronde = Pengangkutan::factory()->for($this->ksm)->create(['tanggal' => '2026-11-10']);
        PengangkutanRincian::factory()->for($ronde)->create(['pelanggan_id' => Pelanggan::factory()->for($this->ksm)->create()->id, 'status' => StatusPengangkutan::Diangkut, 'perkiraan_kg' => 340]);

        $this->diKsmLain(function (): void {
            $lain = Ksm::factory()->create();
            CatatanMaterial::factory()->for($lain)->create(['tanggal' => '2026-11-05', 'berat_kg' => 999]);
            CatatanMaterial::factory()->for($lain)->jual(777_000)->create(['tanggal' => '2026-11-05', 'berat_kg' => 5]);
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

    private function november(): Periode
    {
        return Periode::dariFilter(['tahun' => '2026', 'bulan' => '11']);
    }

    public function test_recovered_material_is_sorted_plus_processed_of_this_ksm_in_the_period(): void
    {
        $material = new RingkasanMaterial($this->ksm);

        $this->assertSame(170.0, $material->terpulihkan('2026-11-01', '2026-11-30'));
        $this->assertSame(['Plastik' => 100.0, 'Kertas & kardus' => 40.0, 'Organik' => 30.0], $material->perKelompok('2026-11-01', '2026-11-30'));
        $this->assertSame(30.0, $material->diolah('2026-11-01', '2026-11-30'));
    }

    public function test_sales_and_stock_per_kind(): void
    {
        $material = new RingkasanMaterial($this->ksm);

        $perJenis = array_column($material->perJenis($this->november()), null, 'jenis');

        $this->assertSame(['kg' => 60.0, 'nilai' => 180_000, 'jumlah' => 1], $material->terjual('2026-11-01', '2026-11-30'));
        $this->assertSame(
            ['pilah' => 100.0, 'olah' => 0.0, 'terpulihkan' => 100.0, 'jual_kg' => 60.0, 'nilai' => 180_000, 'stok' => 60.0],
            array_intersect_key($perJenis['Plastik PET'], array_flip(['pilah', 'olah', 'terpulihkan', 'jual_kg', 'nilai', 'stok'])),
        );
        $this->assertSame([40.0, 0.0], [$perJenis['Kardus']['stok'], $perJenis['Sisa makanan']['stok']]);
        $this->assertSame([['mitra' => 'Bank Sampah Lestari', 'transaksi' => 1, 'kg' => 60.0, 'nilai' => 180_000]], $material->perPengepul($this->november()));
    }

    public function test_material_sales_are_cash_income_on_the_sale_date(): void
    {
        $kas = new RingkasanKas($this->ksm);

        $bulan = array_column($kas->perBulan($this->november()->bulanBulan()), null, 'bulan');

        $this->assertSame(180_000, $kas->masuk('2026-11-01', '2026-11-30'));
        $this->assertSame(['Penjualan material' => 180_000], $kas->pemasukanPerSumber('2026-11-01', '2026-11-30'));
        $this->assertSame(180_000, $bulan['2026-11']['material']);
        $this->assertSame(0, $kas->masuk('2026-10-01', '2026-10-31'));
    }

    public function test_editing_or_removing_a_sale_changes_the_cash_at_once_without_a_copy_left_behind(): void
    {
        $kas = new RingkasanKas($this->ksm);
        $material = new RingkasanMaterial($this->ksm);

        $this->penjualan->update(['nilai_jual' => 200_000]);
        $setelahUbah = $kas->masuk('2026-11-01', '2026-11-30');

        // Switched to "sorted": no buyer, no value, and it now counts as recovered instead.
        $this->penjualan->update(['aktivitas' => AktivitasMaterial::Pilah]);
        $setelahJadiPilah = [$kas->masuk('2026-11-01', '2026-11-30'), $material->terpulihkan('2026-11-01', '2026-11-30'), $this->penjualan->refresh()->nilai_jual, $this->penjualan->mitra_id];

        $this->penjualan->delete();

        $this->assertSame(200_000, $setelahUbah);
        $this->assertSame([0, 230.0, null, null], $setelahJadiPilah);
        $this->assertSame(0, $kas->masuk('2026-11-01', '2026-11-30'));
        $this->assertSame(0, TransaksiKas::query()->count());
    }

    public function test_finance_report_shows_material_sales_in_their_own_column(): void
    {
        $arusKas = (new LaporanKeuangan($this->ksm))->arusKasBulanan($this->november());

        $this->assertSame([0, 180_000, 0, 180_000], [$arusKas->baris[0]['iuran'], $arusKas->baris[0]['material'], $arusKas->baris[0]['masuk_lain'], $arusKas->baris[0]['surplus']]);
    }

    public function test_negative_stock_is_shown_as_a_warning(): void
    {
        CatatanMaterial::factory()->for($this->ksm)->jual(10_000)->create(['tanggal' => '2026-11-13', 'jenis_material_id' => JenisMaterial::query()->where('nama', 'Kardus')->value('id'), 'berat_kg' => 55]);

        $tabel = Livewire::test(TabelMaterialPerJenis::class, ['pageFilters' => ['tahun' => '2026', 'bulan' => '11']]);
        $stok = $tabel->instance()->getTable()->getColumn('stok');

        $tabel->assertSee('-15,0 kg');
        $this->assertSame('danger', $stok->getColor(-15.0));
        $this->assertNull($stok->getColor(60.0));
    }

    public function test_environment_stats_show_the_recovery_rate(): void
    {
        $stat = $this->nilaiStat(Livewire::test(LingkunganStats::class, ['pageFilters' => ['tahun' => '2026', 'bulan' => '11']]));

        $this->assertSame(
            ['Material terpulihkan' => '170,0 kg', 'Terjual' => '60,0 kg', 'Diolah' => '30,0 kg', 'Tingkat pemulihan' => '50%'],
            $stat,
        );
    }

    public function test_environment_report_excel_has_numeric_cells_and_keeps_names_as_text(): void
    {
        JenisMaterial::query()->where('nama', 'Kardus')->update(['nama' => '=HYPERLINK("http://contoh.test")']);

        $komponen = Livewire::test(LaporanLingkunganPage::class)
            ->callAction('unduhExcel')
            ->assertFileDownloaded('laporan-lingkungan-makmurjaya-2026-11.xlsx');

        $isi = $this->bacaUnduhanXlsx($komponen);
        $this->assertSame(['Material per jenis', 'Penjualan per pengepul', 'Material per bulan'], array_keys($isi));
        $pet = $this->barisBerlabel($isi['Material per jenis'], 'Plastik PET');
        $this->assertEqualsWithDelta(60.0, $pet[7], 0.001);
        $this->assertIsNotString($pet[7]);
        $this->assertContains('=HYPERLINK("http://contoh.test")', array_column($isi['Material per jenis'], 0));
        foreach ($this->xmlLembarXlsx($komponen) as $lembar => $xml) {
            $this->assertStringNotContainsString('<f>', $xml, "{$lembar} berisi rumus");
        }
    }

    public function test_material_figures_stay_within_the_given_ksm_even_outside_its_panel(): void
    {
        // Filament's tenant scope only runs while the ksm panel is current.
        Filament::setCurrentPanel('admin');

        $material = new RingkasanMaterial($this->ksm);

        $this->assertSame(170.0, $material->terpulihkan('2026-11-01', '2026-11-30'));
        $this->assertSame(180_000, $material->terjual('2026-11-01', '2026-11-30')['nilai']);
        $this->assertSame(['Kardus', 'Plastik PET', 'Sisa makanan'], array_column($material->perJenis($this->november()), 'jenis'));
        $this->assertSame(['Bank Sampah Lestari'], array_column($material->perPengepul($this->november()), 'mitra'));
    }
}
