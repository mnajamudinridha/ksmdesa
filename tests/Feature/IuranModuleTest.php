<?php

namespace Tests\Feature;

use App\Filament\Ksm\Pages\RekapIuran;
use App\Filament\Ksm\Resources\Pelanggans\Pages\ManagePelanggans;
use App\Filament\Ksm\Resources\PembayaranIurans\Pages\ManagePembayaranIurans;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Customers and monthly fees inside one KSM's panel — and nothing from another KSM.
 */
class IuranModuleTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);

        $pengurus = User::factory()->for($this->ksm)->admin()->create();
        $this->actingAs($pengurus);

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm);
        Filament::bootCurrentPanel();
    }

    /**
     * Records for a different KSM must be created outside the tenant context: while a
     * tenant is set, Filament's `creating` observer re-assigns every new record to it.
     */
    private function diKsmLain(callable $buat): mixed
    {
        Filament::setTenant(null, isQuiet: true);

        try {
            return $buat();
        } finally {
            Filament::setTenant($this->ksm, isQuiet: true);
        }
    }

    public function test_customer_list_only_shows_this_ksm(): void
    {
        $milikSendiri = Pelanggan::factory()->for($this->ksm)->count(2)->create();
        $milikKsmLain = $this->diKsmLain(fn () => Pelanggan::factory()->create());

        Livewire::test(ManagePelanggans::class)
            ->assertCanSeeTableRecords($milikSendiri)
            ->assertCanNotSeeTableRecords([$milikKsmLain]);
    }

    public function test_recording_a_payment_stores_the_month_start_and_this_ksm(): void
    {
        $pelanggan = Pelanggan::factory()->for($this->ksm)->create();

        Livewire::test(ManagePembayaranIurans::class)
            ->callAction('create', data: [
                'pelanggan_id' => $pelanggan->id,
                'periode' => '2026-06-17',
                'nominal' => 25_000,
            ])
            ->assertHasNoFormErrors();

        $pembayaran = PembayaranIuran::sole();
        $this->assertSame('2026-06-01', $pembayaran->periode->toDateString());
        $this->assertTrue($pembayaran->ksm->is($this->ksm));
    }

    public function test_cannot_record_a_payment_for_another_ksms_customer(): void
    {
        $milikKsmLain = $this->diKsmLain(fn () => Pelanggan::factory()->create());

        Livewire::test(ManagePembayaranIurans::class)
            ->callAction('create', data: [
                'pelanggan_id' => $milikKsmLain->id,
                'periode' => '2026-06-01',
                'nominal' => 25_000,
            ])
            ->assertHasFormErrors(['pelanggan_id']);

        $this->assertDatabaseCount('pembayaran_iuran', 0);
    }

    public function test_choosing_a_customer_fills_their_fee_but_never_another_ksms(): void
    {
        $milikSendiri = Pelanggan::factory()->for($this->ksm)->create(['iuran_bulanan' => 50_000]);
        $milikKsmLain = $this->diKsmLain(fn () => Pelanggan::factory()->create(['iuran_bulanan' => 777_000]));

        Livewire::test(ManagePembayaranIurans::class)
            ->mountAction('create')
            ->fillForm(['pelanggan_id' => $milikSendiri->id])
            ->assertSchemaStateSet(['nominal' => 50_000])
            ->fillForm(['nominal' => null])
            ->fillForm(['pelanggan_id' => $milikKsmLain->id])
            ->assertSchemaStateSet(['nominal' => null]);
    }

    public function test_a_second_payment_for_the_same_month_is_rejected(): void
    {
        $pelanggan = Pelanggan::factory()->for($this->ksm)->create();
        PembayaranIuran::factory()->for($pelanggan)->create(['periode' => '2026-06-01']);

        Livewire::test(ManagePembayaranIurans::class)
            ->callAction('create', data: [
                'pelanggan_id' => $pelanggan->id,
                'periode' => '2026-06-20',
                'nominal' => 25_000,
            ])
            ->assertHasFormErrors(['periode']);

        $this->assertDatabaseCount('pembayaran_iuran', 1);
    }

    public function test_rekap_totals_each_month_for_this_ksm_only(): void
    {
        $a = Pelanggan::factory()->for($this->ksm)->create(['nomor_urut' => 1]);
        $b = Pelanggan::factory()->for($this->ksm)->create(['nomor_urut' => 2, 'iuran_bulanan' => 50_000]);
        PembayaranIuran::factory()->for($a)->create(['periode' => '2026-04-01', 'nominal' => 25_000]);
        PembayaranIuran::factory()->for($b)->create(['periode' => '2026-04-01', 'nominal' => 50_000]);
        // Another KSM's payment in the same month must not leak into the total.
        $this->diKsmLain(fn () => PembayaranIuran::factory()->create(['periode' => '2026-04-01', 'nominal' => 999_000]));

        Livewire::test(RekapIuran::class, ['tahun' => 2026])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$a, $b])
            ->assertSee('Rp75.000')
            ->assertDontSee('Rp999.000');
    }
}
