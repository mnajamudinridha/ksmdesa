<?php

namespace Tests\Feature;

use App\Filament\Ksm\Resources\Pelanggans\Pages\ManagePelanggans;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Customer details and collector assignment: admins assign, a petugas sees only their
 * own customers, read-only and without the money.
 */
class PelangganPetugasTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    private User $petugasA;

    private User $petugasB;

    private Pelanggan $milikA;

    private Pelanggan $milikB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya', 'desa' => 'Makmur Jaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->petugasA = User::factory()->for($this->ksm)->petugas()->create(['name' => 'Petugas A']);
        $this->petugasB = User::factory()->for($this->ksm)->petugas()->create(['name' => 'Petugas B']);
        $this->milikA = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah A', 'petugas_id' => $this->petugasA->id]);
        $this->milikB = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah B', 'petugas_id' => $this->petugasB->id]);

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

    public function test_petugas_sees_only_their_own_customers_without_money_or_edit(): void
    {
        $this->actingAs($this->petugasA);

        Livewire::test(ManagePelanggans::class)
            ->assertCanSeeTableRecords([$this->milikA])
            ->assertCanNotSeeTableRecords([$this->milikB])
            ->assertTableColumnHidden('iuran_bulanan')
            ->assertTableColumnHidden('tarifLayanan.nama')
            ->assertActionHidden(TestAction::make('edit')->table($this->milikA))
            ->assertActionVisible(TestAction::make('view')->table($this->milikA));

        $this->assertTrue($this->petugasA->can('view', $this->milikA));
        $this->assertFalse($this->petugasA->can('view', $this->milikB));
        $this->assertFalse($this->petugasA->can('update', $this->milikA));
    }

    public function test_admin_assigns_a_petugas_to_selected_customers_only(): void
    {
        $this->actingAs($this->admin);
        $tanpa = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah C']);

        Livewire::test(ManagePelanggans::class)
            ->selectTableRecords([$this->milikA->id, $tanpa->id])
            ->callAction(TestAction::make('tetapkanPetugas')->table()->bulk(), data: ['petugas_id' => $this->petugasB->id])
            ->assertHasNoFormErrors();

        $this->assertSame($this->petugasB->id, $this->milikA->refresh()->petugas_id);
        $this->assertSame($this->petugasB->id, $tanpa->refresh()->petugas_id);
        $this->assertSame($this->petugasB->id, $this->milikB->refresh()->petugas_id);
    }

    public function test_bulk_assign_refuses_inactive_or_other_ksm_petugas(): void
    {
        $this->actingAs($this->admin);
        $nonaktif = User::factory()->for($this->ksm)->petugas()->nonaktif()->create();
        $lain = $this->diKsmLain(fn () => User::factory()->for(Ksm::factory())->petugas()->create());

        foreach ([$nonaktif, $lain] as $salah) {
            Livewire::test(ManagePelanggans::class)
                ->selectTableRecords([$this->milikA->id])
                ->callAction(TestAction::make('tetapkanPetugas')->table()->bulk(), data: ['petugas_id' => $salah->id])
                ->assertHasFormErrors(['petugas_id']);
        }

        $this->assertSame($this->petugasA->id, $this->milikA->refresh()->petugas_id);
    }

    public function test_petugas_cannot_bulk_assign(): void
    {
        $this->actingAs($this->petugasA);

        Livewire::test(ManagePelanggans::class)
            ->assertActionHidden(TestAction::make('tetapkanPetugas')->table()->bulk());
    }

    public function test_editing_a_customer_whose_petugas_left_keeps_working(): void
    {
        $this->actingAs($this->admin);
        $this->petugasA->update(['aktif' => false]);

        Livewire::test(ManagePelanggans::class)
            ->callAction(TestAction::make('edit')->table($this->milikA), data: ['alamat' => 'Jl. Baru 1'])
            ->assertHasNoFormErrors();

        $this->milikA->refresh();
        $this->assertSame('Jl. Baru 1', $this->milikA->alamat);
        $this->assertSame($this->petugasA->id, $this->milikA->petugas_id);
    }

    public function test_filter_finds_customers_without_an_active_petugas(): void
    {
        $this->actingAs($this->admin);
        $tanpa = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah Tanpa']);
        $this->petugasB->update(['aktif' => false]);

        Livewire::test(ManagePelanggans::class)
            ->filterTable('tanpa_petugas', true)
            ->assertCanSeeTableRecords([$tanpa, $this->milikB])
            ->assertCanNotSeeTableRecords([$this->milikA]);
    }

    public function test_warga_cannot_see_the_customer_list(): void
    {
        $warga = User::factory()->for($this->ksm)->warga()->create();

        $this->assertFalse($warga->can('viewAny', Pelanggan::class));
        $this->actingAs($warga)->get('http://makmurjaya.ksmdesa.test/pelanggans')->assertForbidden();
    }

    public function test_map_link_uses_coordinates_then_address(): void
    {
        $koordinat = new Pelanggan(['latitude' => '-3.3194374', 'longitude' => '114.5900000', 'alamat' => 'Jl. A']);
        $alamat = new Pelanggan(['alamat' => 'Jl. Melati No. 2']);
        $alamat->setRelation('ksm', $this->ksm);

        $this->assertStringContainsString('query=-3.3194374%2C114.5900000', $koordinat->mapsUrl());
        $this->assertStringStartsWith('https://www.google.com/maps/search/', $alamat->mapsUrl());
        $this->assertStringContainsString(rawurlencode('Jl. Melati No. 2, Makmur Jaya'), $alamat->mapsUrl());
        $this->assertNull((new Pelanggan)->mapsUrl());
    }
}
