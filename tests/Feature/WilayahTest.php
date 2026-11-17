<?php

namespace Tests\Feature;

use App\Filament\Ksm\Resources\Pelanggans\Pages\ManagePelanggans;
use App\Filament\Ksm\Resources\Wilayahs\Pages\ManageWilayahs;
use App\Filament\Shared\PilihanMaster;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Models\User;
use App\Models\Wilayah;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5A: the village's RT/RW list (master Wilayah) and customers picking their RT from it.
 */
class WilayahTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->actingAs($this->admin);

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
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

    public function test_admin_adds_an_rt_to_this_ksm(): void
    {
        Livewire::test(ManageWilayahs::class)
            ->callAction('create', data: ['rt' => 3, 'rw' => 1, 'dusun' => 'Dusun Makmur', 'jumlah_kk' => 40])
            ->assertHasNoFormErrors();

        $wilayah = Wilayah::query()->sole();
        $this->assertTrue($wilayah->ksm->is($this->ksm));
        $this->assertSame('RT 03 / RW 01 · Dusun Makmur', $wilayah->label());
        $this->assertSame(40, $wilayah->jumlah_kk);
    }

    public function test_rejects_an_rt_already_listed_in_the_same_rw_including_rts_without_rw(): void
    {
        Wilayah::factory()->for($this->ksm)->create(['rt' => 1, 'rw' => 2]);
        Wilayah::factory()->for($this->ksm)->create(['rt' => 5, 'rw' => null]);

        Livewire::test(ManageWilayahs::class)
            ->callAction('create', data: ['rt' => 1, 'rw' => 2])
            ->assertHasFormErrors(['rt' => 'RT ini sudah ada di daftar.']);

        // The database cannot catch this one (NULL ≠ NULL): only the form rule does.
        Livewire::test(ManageWilayahs::class)
            ->callAction('create', data: ['rt' => 5, 'rw' => null])
            ->assertHasFormErrors(['rt' => 'RT ini sudah ada di daftar.']);

        $this->assertSame(2, Wilayah::query()->count());
    }

    public function test_same_rt_number_is_allowed_in_another_rw_and_in_another_ksm(): void
    {
        Wilayah::factory()->for($this->ksm)->create(['rt' => 1, 'rw' => 2]);
        $this->diKsmLain(fn () => Wilayah::factory()->for(Ksm::factory())->create(['rt' => 7, 'rw' => 1]));

        Livewire::test(ManageWilayahs::class)
            ->callAction('create', data: ['rt' => 1, 'rw' => 3])
            ->assertHasNoFormErrors()
            ->callAction('create', data: ['rt' => 1, 'rw' => null])
            ->assertHasNoFormErrors()
            ->callAction('create', data: ['rt' => 7, 'rw' => 1])
            ->assertHasNoFormErrors();

        $this->assertSame(4, Wilayah::query()->whereBelongsTo($this->ksm)->count());
    }

    public function test_customer_can_only_pick_an_rt_of_this_ksm(): void
    {
        $rtSini = Wilayah::factory()->for($this->ksm)->create(['rt' => 1, 'rw' => 1]);
        $rtLain = $this->diKsmLain(fn () => Wilayah::factory()->for(Ksm::factory())->create(['rt' => 2, 'rw' => 1]));
        $pelanggan = Pelanggan::factory()->for($this->ksm)->create();

        Livewire::test(ManagePelanggans::class)
            ->callAction(TestAction::make('edit')->table($pelanggan), data: ['wilayah_id' => $rtLain->id])
            ->assertHasFormErrors(['wilayah_id' => 'in']);
        $this->assertNull($pelanggan->refresh()->wilayah_id);

        Livewire::test(ManagePelanggans::class)
            ->callAction(TestAction::make('edit')->table($pelanggan), data: ['wilayah_id' => $rtSini->id])
            ->assertHasNoFormErrors();
        $this->assertSame($rtSini->id, $pelanggan->refresh()->wilayah_id);

        Livewire::test(ManagePelanggans::class)
            ->callAction(TestAction::make('edit')->table($pelanggan), data: ['nama' => 'Nama Baru'])
            ->assertHasNoFormErrors();
        $this->assertSame($rtSini->id, $pelanggan->refresh()->wilayah_id);
    }

    public function test_rt_options_stay_within_this_ksm_even_outside_its_panel(): void
    {
        $rtSini = Wilayah::factory()->for($this->ksm)->create(['rt' => 1, 'rw' => 1]);
        $this->diKsmLain(fn () => Wilayah::factory()->for(Ksm::factory())->create(['rt' => 2, 'rw' => 1]));

        // Filament's tenant scope only runs while the ksm panel is current.
        Filament::setCurrentPanel('admin');

        $this->assertSame([$rtSini->id => 'RT 01 / RW 01'], PilihanMaster::wilayah());
    }

    public function test_rt_with_customers_cannot_be_deleted_but_an_empty_one_can(): void
    {
        $terpakai = Wilayah::factory()->for($this->ksm)->create(['rt' => 1, 'rw' => 1]);
        $kosong = Wilayah::factory()->for($this->ksm)->create(['rt' => 2, 'rw' => 1]);
        Pelanggan::factory()->for($this->ksm)->create(['wilayah_id' => $terpakai->id]);

        Livewire::test(ManageWilayahs::class)
            ->assertActionHidden(TestAction::make('delete')->table($terpakai))
            ->callAction(TestAction::make('delete')->table($kosong));

        $this->assertFalse($this->admin->can('delete', $terpakai));
        $this->assertModelExists($terpakai);
        $this->assertModelMissing($kosong);
    }

    public function test_petugas_and_warga_are_forbidden_from_the_rt_list(): void
    {
        $petugas = User::factory()->for($this->ksm)->petugas()->create();
        $warga = Pelanggan::factory()->for($this->ksm)->create()->akunWarga;

        foreach ([$petugas, $warga] as $pengguna) {
            $this->flushSession();
            $this->actingAs($pengguna)->get('http://makmurjaya.ksmdesa.test/wilayahs')->assertForbidden();
        }
    }

    public function test_deleting_a_ksm_removes_its_rts_and_customers(): void
    {
        $wilayah = Wilayah::factory()->for($this->ksm)->create();
        $pelanggan = Pelanggan::factory()->for($this->ksm)->create(['wilayah_id' => $wilayah->id]);
        PembayaranIuran::factory()->for($pelanggan)->create();
        PengangkutanRincian::factory()
            ->for(Pengangkutan::factory()->for($this->ksm))
            ->create(['pelanggan_id' => $pelanggan->id]);

        $this->ksm->delete();

        $this->assertModelMissing($wilayah);
        $this->assertModelMissing($pelanggan);
    }
}
