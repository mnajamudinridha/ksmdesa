<?php

namespace Tests\Feature;

use App\Filament\Ksm\Resources\Setorans\Pages\CreateSetoran;
use App\Filament\Ksm\Resources\Setorans\Pages\ListSetorans;
use App\Filament\Ksm\Resources\Setorans\Pages\ViewSetoran;
use App\Filament\Ksm\Resources\Setorans\SetoranResource;
use App\Filament\Ksm\Widgets\IuranStatsOverview;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\Setoran;
use App\Models\User;
use App\Support\TerimaIuran;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4: the admin receives a petugas's collected cash as a deposit (Setoran) — exactly
 * that petugas's undeposited payments; cancelling returns them, none is ever lost.
 */
class SetoranTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    private User $petugasA;

    private User $petugasB;

    private PembayaranIuran $a1;

    private PembayaranIuran $a2;

    private PembayaranIuran $b1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->petugasA = User::factory()->for($this->ksm)->petugas()->create();
        $this->petugasB = User::factory()->for($this->ksm)->petugas()->create();
        $rumahA = Pelanggan::factory()->for($this->ksm)->create(['petugas_id' => $this->petugasA->id]);
        $rumahB = Pelanggan::factory()->for($this->ksm)->create(['petugas_id' => $this->petugasB->id]);

        TerimaIuran::catat($rumahA, $this->petugasA, ['2026-10-01', '2026-11-01'], 25_000, '2026-11-17');
        TerimaIuran::catat($rumahB, $this->petugasB, ['2026-11-01'], 50_000, '2026-11-17');
        [$this->a1, $this->a2] = PembayaranIuran::query()->where('petugas_id', $this->petugasA->id)->orderBy('periode')->get()->all();
        $this->b1 = PembayaranIuran::query()->where('petugas_id', $this->petugasB->id)->sole();

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
        $this->actingAs($this->admin);
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

    public function test_options_are_exactly_the_chosen_petugas_undeposited_payments(): void
    {
        $sudah = PembayaranIuran::factory()->for(Pelanggan::factory()->for($this->ksm)->create(['petugas_id' => $this->petugasA->id]))->create(['periode' => '2026-09-01']);
        $sudah->forceFill(['petugas_id' => $this->petugasA->id, 'setoran_id' => Setoran::factory()->for($this->ksm)->create(['petugas_id' => $this->petugasA->id])->id])->save();
        $langsung = PembayaranIuran::factory()->for(Pelanggan::factory()->for($this->ksm)->create())->create();
        $this->diKsmLain(function (): void {
            $lain = Ksm::factory()->create();
            $rumah = Pelanggan::factory()->for($lain)->create();
            TerimaIuran::catat($rumah, User::factory()->for($lain)->petugas()->create(), ['2026-11-01'], 25_000, '2026-11-17');
        });

        $this->assertSame([$this->a1->id, $this->a2->id], array_keys(SetoranResource::opsiPembayaran($this->petugasA->id)));
        $this->assertSame([$this->petugasA->id, $this->petugasB->id], collect(array_keys(SetoranResource::opsiPetugas()))->sort()->values()->all());
        $this->assertSame('langsung', $langsung->statusSetor());

        Livewire::test(CreateSetoran::class)
            ->fillForm(['petugas_id' => $this->petugasA->id])
            ->assertSchemaStateSet(['pembayaran' => [(string) $this->a1->id, (string) $this->a2->id]]);
    }

    public function test_creating_a_deposit_links_the_payments_and_lowers_cash_with_petugas(): void
    {
        Livewire::test(CreateSetoran::class)
            ->fillForm(['petugas_id' => $this->petugasA->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $setoran = Setoran::query()->sole();
        $this->assertSame($this->admin->id, $setoran->diterima_oleh_id);
        $this->assertTrue($setoran->ksm->is($this->ksm));
        $this->assertSame('2026-11-17', $setoran->tanggal->toDateString());
        $this->assertEqualsCanonicalizing([$this->a1->id, $this->a2->id], $setoran->pembayaranIuran()->pluck('id')->all());
        $this->assertNull($this->b1->refresh()->setoran_id);

        // Only petugas B's Rp50.000 is still out.
        Livewire::test(IuranStatsOverview::class)->assertSee('Rp50.000');
        Livewire::test(ListSetorans::class)->assertTableColumnStateSet('total', 50_000, $setoran);
    }

    public function test_another_petugas_payment_cannot_be_slipped_in(): void
    {
        Livewire::test(CreateSetoran::class)
            ->fillForm(['petugas_id' => $this->petugasA->id])
            ->fillForm(['pembayaran' => [(string) $this->a1->id, (string) $this->b1->id]])
            ->call('create')
            ->assertHasFormErrors(['pembayaran.1' => 'in']);

        $this->assertSame(0, Setoran::query()->count());
        $this->assertNull($this->a1->refresh()->setoran_id);
    }

    public function test_a_payment_deposited_meanwhile_is_rejected_and_nothing_is_saved(): void
    {
        $form = Livewire::test(CreateSetoran::class)->fillForm(['petugas_id' => $this->petugasA->id]);

        $lain = Setoran::factory()->for($this->ksm)->create(['petugas_id' => $this->petugasA->id]);
        $this->a1->forceFill(['setoran_id' => $lain->id])->save();

        $form->call('create')->assertHasFormErrors(['pembayaran.0']);

        $this->assertSame(1, Setoran::query()->count());
        $this->assertNull($this->a2->refresh()->setoran_id);
        $this->assertSame($lain->id, $this->a1->refresh()->setoran_id);
    }

    public function test_cancelling_a_deposit_returns_its_payments(): void
    {
        Livewire::test(CreateSetoran::class)->fillForm(['petugas_id' => $this->petugasA->id])->call('create');
        $setoran = Setoran::query()->sole();

        Livewire::test(ViewSetoran::class, ['record' => $setoran->getRouteKey()])->callAction('delete');

        $this->assertSame(0, Setoran::query()->count());
        $this->assertSame(3, PembayaranIuran::query()->count());
        $this->assertSame(3, PembayaranIuran::query()->belumDisetor()->count());
    }

    public function test_other_ksm_deposits_stay_invisible(): void
    {
        $lain = $this->diKsmLain(fn () => Setoran::factory()->create());
        $milik = Setoran::factory()->for($this->ksm)->create(['petugas_id' => $this->petugasA->id]);

        Livewire::test(ListSetorans::class)
            ->assertCanSeeTableRecords([$milik])
            ->assertCanNotSeeTableRecords([$lain]);
    }
}
