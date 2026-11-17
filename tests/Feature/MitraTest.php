<?php

namespace Tests\Feature;

use App\Enums\JenisMitra;
use App\Filament\Ksm\Resources\Mitras\Pages\ManageMitras;
use App\Models\Ksm;
use App\Models\Mitra;
use App\Models\TransaksiKas;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5B: the KSM's partner list and the support each partner gave (income naming it).
 */
class MitraTest extends TestCase
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

    public function test_phone_number_is_validated_and_saved_normalised(): void
    {
        Livewire::test(ManageMitras::class)
            ->callAction('create', data: ['nama' => 'Bank Sampah Lestari', 'jenis' => JenisMitra::Pengepul->value, 'no_hp' => '12345'])
            ->assertHasFormErrors(['no_hp' => 'Nomor HP tidak valid (contoh 081234567890).']);
        Livewire::test(ManageMitras::class)
            ->callAction('create', data: ['nama' => 'Bank Sampah Lestari', 'jenis' => JenisMitra::Pengepul->value, 'no_hp' => '+62 812-3456-7890', 'aktif' => true])
            ->assertHasNoFormErrors();

        $mitra = Mitra::query()->sole();
        $this->assertSame(['081234567890', JenisMitra::Pengepul], [$mitra->no_hp, $mitra->jenis]);
        $this->assertTrue($mitra->ksm->is($this->ksm));
    }

    public function test_name_is_unique_within_the_ksm_only(): void
    {
        Mitra::factory()->for($this->ksm)->create(['nama' => 'PT Peduli']);
        $this->diKsmLain(fn () => Mitra::factory()->for(Ksm::factory())->create(['nama' => 'CV Hijau']));

        Livewire::test(ManageMitras::class)
            ->callAction('create', data: ['nama' => 'PT Peduli', 'jenis' => JenisMitra::Swasta->value])
            ->assertHasFormErrors(['nama' => 'Mitra dengan nama ini sudah ada.']);
        Livewire::test(ManageMitras::class)
            ->callAction('create', data: ['nama' => 'CV Hijau', 'jenis' => JenisMitra::Swasta->value, 'aktif' => true])
            ->assertHasNoFormErrors();

        $this->assertSame(['CV Hijau', 'PT Peduli'], Mitra::query()->whereBelongsTo($this->ksm)->orderBy('nama')->pluck('nama')->all());
    }

    public function test_support_column_sums_income_naming_the_partner_but_not_spending(): void
    {
        $mitra = Mitra::factory()->for($this->ksm)->create();
        TransaksiKas::factory()->for($this->ksm)->masuk()->create(['mitra_id' => $mitra->id, 'nominal' => 300_000]);
        TransaksiKas::factory()->for($this->ksm)->masuk()->create(['mitra_id' => $mitra->id, 'nominal' => 200_000]);
        TransaksiKas::factory()->for($this->ksm)->masuk()->create(['nominal' => 999_000]);
        // Spending naming the partner (written past the model, as old data could be) is not support.
        $keluar = TransaksiKas::factory()->for($this->ksm)->create(['nominal' => 77_000]);
        TransaksiKas::query()->whereKey($keluar->id)->update(['mitra_id' => $mitra->id]);

        Livewire::test(ManageMitras::class)
            ->assertTableColumnStateSet('dukungan', 500_000, $mitra);
    }

    public function test_partner_named_on_a_transaction_cannot_be_deleted(): void
    {
        $terpakai = Mitra::factory()->for($this->ksm)->create();
        $kosong = Mitra::factory()->for($this->ksm)->create();
        TransaksiKas::factory()->for($this->ksm)->masuk()->create(['mitra_id' => $terpakai->id]);

        Livewire::test(ManageMitras::class)
            ->assertActionHidden(TestAction::make('delete')->table($terpakai))
            ->callAction(TestAction::make('delete')->table($kosong));

        $this->assertFalse($this->admin->can('delete', $terpakai));
        $this->assertModelExists($terpakai);
        $this->assertModelMissing($kosong);
    }
}
