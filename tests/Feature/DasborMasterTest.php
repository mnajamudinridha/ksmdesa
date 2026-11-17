<?php

namespace Tests\Feature;

use App\Filament\Widgets\IuranSemuaKsmChart;
use App\Filament\Widgets\RingkasanMasterStats;
use App\Filament\Widgets\RingkasanPerKsmTable;
use App\Models\CatatanMaterial;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\TransaksiKas;
use App\Models\User;
use App\Support\TerimaIuran;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BacaNilaiStat;
use Tests\TestCase;

/**
 * Phase 5A: the master dashboard across all KSMs — totals, cash still with petugas per KSM,
 * and which KSMs have not recorded anything in the last 7 days.
 *
 * Fixture (today 2026-11-17): KSM A recorded a petugas payment yesterday (Rp50.000, November);
 * KSM B's last record is 10 days old (petugas Rp30.000, October); KSM C never recorded anything.
 */
class DasborMasterTest extends TestCase
{
    use BacaNilaiStat;
    use RefreshDatabase;

    private Ksm $a;

    private Ksm $b;

    private Ksm $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->a = Ksm::factory()->create(['nama' => 'KSM A']);
        $this->b = Ksm::factory()->create(['nama' => 'KSM B']);
        $this->c = Ksm::factory()->create(['nama' => 'KSM C']);
        $this->terimaIuran($this->a, '2026-11-01', 50_000, now()->subDay());
        $this->terimaIuran($this->b, '2026-10-01', 30_000, now()->subDays(10));

        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    private function terimaIuran(Ksm $ksm, string $periode, int $nominal, CarbonInterface $dibuat): void
    {
        $petugas = User::factory()->for($ksm)->petugas()->create();
        $rumah = Pelanggan::factory()->for($ksm)->create(['petugas_id' => $petugas->id, 'iuran_bulanan' => 25_000]);
        TerimaIuran::catat($rumah, $petugas, [$periode], $nominal, $dibuat->toDateString());
        PembayaranIuran::query()->where('pelanggan_id', $rumah->id)->update(['created_at' => $dibuat]);
    }

    public function test_stats_total_all_ksms_and_count_those_recording_in_the_last_7_days(): void
    {
        $widget = Livewire::test(RingkasanMasterStats::class);

        $stat = $this->nilaiStat($widget);
        $this->assertSame(['3', 'Rp50.000', 'Rp80.000'], [$stat['KSM terdaftar'], $stat['Iuran November 2026'], $stat['Di tangan petugas']]);
        $widget->assertSee('1 aktif mencatat 7 hari terakhir');
    }

    public function test_table_marks_and_filters_ksms_that_have_not_recorded_recently(): void
    {
        Livewire::test(RingkasanPerKsmTable::class)
            ->assertCanSeeTableRecords([$this->a, $this->b, $this->c])
            ->assertTableColumnStateSet('status', 'Aktif mencatat', $this->a)
            ->assertTableColumnStateSet('status', 'Belum aktif', $this->b)
            ->assertTableColumnStateSet('status', 'Belum aktif', $this->c)
            ->assertTableColumnStateSet('di_tangan_petugas', 30_000, $this->b)
            ->assertTableColumnStateSet('iuran_bulan_ini', 50_000, $this->a)
            ->filterTable('belum_aktif')
            ->assertCanSeeTableRecords([$this->b, $this->c])
            ->assertCanNotSeeTableRecords([$this->a]);
    }

    public function test_a_cash_book_entry_counts_as_recording(): void
    {
        $transaksi = TransaksiKas::factory()->for($this->c)->create();
        $transaksi->forceFill(['created_at' => now()->subDays(2)])->save();

        Livewire::test(RingkasanPerKsmTable::class)
            ->assertTableColumnStateSet('status', 'Aktif mencatat', $this->c);
    }

    public function test_a_material_record_counts_as_recording(): void
    {
        $catatan = CatatanMaterial::factory()->for($this->c)->create();
        $catatan->forceFill(['created_at' => now()->subDay()])->save();

        Livewire::test(RingkasanPerKsmTable::class)
            ->assertTableColumnStateSet('status', 'Aktif mencatat', $this->c);
    }

    public function test_village_admin_cannot_see_master_widgets(): void
    {
        $this->actingAs(User::factory()->for($this->a)->admin()->create());

        $this->assertFalse(RingkasanMasterStats::canView());
        $this->assertFalse(RingkasanPerKsmTable::canView());
        $this->assertFalse(IuranSemuaKsmChart::canView());
    }

    public function test_master_dashboard_loads(): void
    {
        $this->get('http://ksmdesa.test/admin')
            ->assertOk()
            ->assertSeeLivewire(RingkasanMasterStats::class)
            ->assertSeeLivewire(RingkasanPerKsmTable::class);
    }
}
