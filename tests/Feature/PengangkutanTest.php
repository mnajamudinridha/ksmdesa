<?php

namespace Tests\Feature;

use App\Enums\StatusPengangkutan;
use App\Filament\Ksm\Resources\Pengangkutans\Pages\CreatePengangkutan;
use App\Filament\Ksm\Resources\Pengangkutans\Pages\EditPengangkutan;
use App\Filament\Ksm\Resources\Pengangkutans\Pages\ListPengangkutans;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Daily collection rounds: a petugas records their own houses with an estimated weight, on
 * the village's calendar day, and may correct it until the end of the next day.
 */
class PengangkutanTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    private User $petugasA;

    private User $petugasB;

    private Pelanggan $rumahA1;

    private Pelanggan $rumahA2;

    private Pelanggan $rumahANonaktif;

    private Pelanggan $rumahB;

    protected function setUp(): void
    {
        parent::setUp();

        // 08:00 WITA on 17 Nov = 00:00 UTC; tests that care about the boundary travel further.
        $this->travelTo(CarbonImmutable::parse('2026-11-17 08:00', 'Asia/Makassar'));
        Repeater::fake();

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->petugasA = User::factory()->for($this->ksm)->petugas()->create();
        $this->petugasB = User::factory()->for($this->ksm)->petugas()->create();
        $this->rumahA1 = Pelanggan::factory()->for($this->ksm)->create(['nomor_urut' => 1, 'petugas_id' => $this->petugasA->id]);
        $this->rumahA2 = Pelanggan::factory()->for($this->ksm)->create(['nomor_urut' => 2, 'petugas_id' => $this->petugasA->id]);
        $this->rumahANonaktif = Pelanggan::factory()->for($this->ksm)->create(['nomor_urut' => 3, 'petugas_id' => $this->petugasA->id, 'aktif' => false]);
        $this->rumahB = Pelanggan::factory()->for($this->ksm)->create(['nomor_urut' => 4, 'petugas_id' => $this->petugasB->id]);

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

    /** @return array<string, mixed> */
    private function baris(Pelanggan $pelanggan, StatusPengangkutan $status = StatusPengangkutan::Diangkut, ?float $kg = 4.5): array
    {
        return ['pelanggan_id' => $pelanggan->id, 'status' => $status->value, 'perkiraan_kg' => $kg, 'catatan' => null];
    }

    private function ronde(User $petugas, string $tanggal): Pengangkutan
    {
        return Pengangkutan::factory()->for($this->ksm)->create(['petugas_id' => $petugas->id, 'tanggal' => $tanggal]);
    }

    public function test_petugas_form_starts_with_their_own_active_houses_on_todays_village_date(): void
    {
        $this->actingAs($this->petugasA);

        $form = Livewire::test(CreatePengangkutan::class)
            ->assertSchemaStateSet(['tanggal' => '2026-11-17'])
            ->assertFormFieldHidden('petugas_id');

        $this->assertSame(
            [$this->rumahA1->id, $this->rumahA2->id],
            array_values(array_map(fn (array $baris): int => $baris['pelanggan_id'], $form->get('data.rincian'))),
        );
    }

    public function test_today_is_the_village_day_not_the_utc_day(): void
    {
        // 07:00 WITA on 18 Nov is still 17 Nov in UTC.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 07:00', 'Asia/Makassar'));
        $this->actingAs($this->petugasA);

        Livewire::test(CreatePengangkutan::class)->assertSchemaStateSet(['tanggal' => '2026-11-18']);
    }

    public function test_petugas_records_a_round_that_is_always_theirs(): void
    {
        $this->actingAs($this->petugasA);

        Livewire::test(CreatePengangkutan::class)
            ->fillForm([
                'petugas_id' => $this->petugasB->id,
                'rincian' => [$this->baris($this->rumahA1, kg: 4.5), $this->baris($this->rumahA2, StatusPengangkutan::TidakAdaSampah, null)],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ronde = Pengangkutan::query()->sole();
        $this->assertSame($this->petugasA->id, $ronde->petugas_id);
        $this->assertTrue($ronde->ksm->is($this->ksm));
        $this->assertSame('2026-11-17', $ronde->tanggal->toDateString());
        $this->assertEquals(['4.5', null], $ronde->rincian()->orderBy('pelanggan_id')->pluck('perkiraan_kg')->all());
    }

    public function test_petugas_cannot_record_houses_that_are_not_theirs_or_inactive(): void
    {
        $this->actingAs($this->petugasA);

        foreach ([$this->rumahB, $this->rumahANonaktif] as $bukan) {
            Livewire::test(CreatePengangkutan::class)
                ->fillForm(['rincian' => [$this->baris($bukan)]])
                ->call('create')
                ->assertHasFormErrors(['rincian.0.pelanggan_id' => 'in']);
        }

        $lain = $this->diKsmLain(fn () => Pelanggan::factory()->for(Ksm::factory())->create());

        Livewire::test(CreatePengangkutan::class)
            ->fillForm(['rincian' => [$this->baris($lain)]])
            ->call('create')
            ->assertHasFormErrors(['rincian.0.pelanggan_id' => 'in']);

        $this->assertSame(0, Pengangkutan::query()->count());
    }

    public function test_the_same_house_twice_in_one_round_is_rejected(): void
    {
        $this->actingAs($this->petugasA);

        Livewire::test(CreatePengangkutan::class)
            ->fillForm(['rincian' => [$this->baris($this->rumahA1), $this->baris($this->rumahA1)]])
            ->call('create')
            ->assertHasFormErrors(['rincian.1.pelanggan_id']);
    }

    public function test_one_round_per_petugas_per_day(): void
    {
        $this->ronde($this->petugasA, '2026-11-17');
        $this->actingAs($this->petugasA);

        Livewire::test(CreatePengangkutan::class)
            ->fillForm(['rincian' => [$this->baris($this->rumahA1)]])
            ->call('create')
            ->assertHasFormErrors(['tanggal']);

        // Another petugas on the same day is fine.
        $this->actingAs($this->petugasB);
        Livewire::test(CreatePengangkutan::class)
            ->fillForm(['rincian' => [$this->baris($this->rumahB)]])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_petugas_may_back_fill_yesterday_but_not_earlier_or_the_future(): void
    {
        $this->actingAs($this->petugasA);

        foreach (['2026-11-15' => true, '2026-11-18' => true, '2026-11-16' => false] as $tanggal => $ditolak) {
            $hasil = Livewire::test(CreatePengangkutan::class)
                ->fillForm(['tanggal' => $tanggal, 'rincian' => [$this->baris($this->rumahA1)]])
                ->call('create');

            $ditolak ? $hasil->assertHasFormErrors(['tanggal']) : $hasil->assertHasNoFormErrors();
        }
    }

    public function test_weight_is_required_only_for_collected_waste_and_dropped_otherwise(): void
    {
        $this->actingAs($this->petugasA);

        Livewire::test(CreatePengangkutan::class)
            ->fillForm(['rincian' => [$this->baris($this->rumahA1, kg: null)]])
            ->call('create')
            ->assertHasFormErrors(['rincian.0.perkiraan_kg' => 'required']);

        foreach ([0, 600] as $salah) {
            Livewire::test(CreatePengangkutan::class)
                ->fillForm(['rincian' => [$this->baris($this->rumahA1, kg: $salah)]])
                ->call('create')
                ->assertHasFormErrors(['rincian.0.perkiraan_kg']);
        }

        $rincian = PengangkutanRincian::factory()
            ->for($this->ronde($this->petugasA, '2026-11-17'))
            ->create(['pelanggan_id' => $this->rumahA1->id, 'perkiraan_kg' => 5]);
        $rincian->update(['status' => StatusPengangkutan::TidakBisaDiakses]);

        $this->assertNull($rincian->refresh()->perkiraan_kg);
    }

    public function test_petugas_can_correct_until_the_end_of_the_next_day_only(): void
    {
        $kemarin = $this->ronde($this->petugasA, '2026-11-16');
        $lusa = $this->ronde($this->petugasA, '2026-11-15');
        $this->actingAs($this->petugasA);

        $this->assertTrue($this->petugasA->can('update', $kemarin));
        $this->assertFalse($this->petugasA->can('update', $lusa));
        $this->assertFalse($this->petugasA->can('delete', $lusa));
        $this->assertTrue($this->admin->can('update', $lusa));

        Livewire::test(ListPengangkutans::class)
            ->assertActionVisible(TestAction::make('edit')->table($kemarin))
            ->assertActionHidden(TestAction::make('edit')->table($lusa))
            ->assertActionHidden(TestAction::make('delete')->table($lusa));

        $this->get("http://makmurjaya.ksmdesa.test/pengangkutans/{$lusa->id}/edit")->assertForbidden();
    }

    public function test_petugas_edit_keeps_the_date_and_saves_house_changes(): void
    {
        $ronde = $this->ronde($this->petugasA, '2026-11-17');
        PengangkutanRincian::factory()->for($ronde)->create(['pelanggan_id' => $this->rumahA1->id, 'perkiraan_kg' => 3]);
        $this->actingAs($this->petugasA);

        Livewire::test(EditPengangkutan::class, ['record' => $ronde->getRouteKey()])
            ->assertFormFieldDisabled('tanggal')
            ->fillForm(['tanggal' => '2026-11-16', 'rincian' => [$this->baris($this->rumahA1, kg: 7), $this->baris($this->rumahA2, kg: 2)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $ronde->refresh();
        $this->assertSame('2026-11-17', $ronde->tanggal->toDateString());
        $this->assertEquals(9.0, (float) $ronde->rincian()->sum('perkiraan_kg'));
    }

    public function test_each_petugas_sees_only_their_own_rounds_and_warga_none(): void
    {
        $milikA = $this->ronde($this->petugasA, '2026-11-17');
        $milikB = $this->ronde($this->petugasB, '2026-11-17');
        $lain = $this->diKsmLain(fn () => Pengangkutan::factory()->create());

        $this->actingAs($this->petugasA);
        Livewire::test(ListPengangkutans::class)
            ->assertCanSeeTableRecords([$milikA])
            ->assertCanNotSeeTableRecords([$milikB, $lain])
            ->assertTableColumnHidden('petugas.name');
        $this->get("http://makmurjaya.ksmdesa.test/pengangkutans/{$milikB->id}/edit")->assertNotFound();

        $this->actingAs($this->admin);
        Livewire::test(ListPengangkutans::class)
            ->assertCanSeeTableRecords([$milikA, $milikB])
            ->assertCanNotSeeTableRecords([$lain]);

        $warga = User::factory()->for($this->ksm)->warga()->create();
        $this->actingAs($warga)->get('http://makmurjaya.ksmdesa.test/pengangkutans')->assertForbidden();
    }

    public function test_admin_picks_a_petugas_and_the_rows_follow_that_petugas(): void
    {
        $this->actingAs($this->admin);

        $form = Livewire::test(CreatePengangkutan::class)
            ->assertSchemaStateSet(['rincian' => []])
            ->fillForm(['petugas_id' => $this->petugasB->id]);

        $this->assertSame([$this->rumahB->id], array_values(array_map(fn (array $baris): int => $baris['pelanggan_id'], $form->get('data.rincian'))));

        $form->fillForm(['rincian' => [$this->baris($this->rumahB, kg: 6)]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->petugasB->id, Pengangkutan::query()->sole()->petugas_id);
    }

    public function test_admin_cannot_assign_a_round_to_someone_who_is_not_an_active_petugas_here(): void
    {
        $this->actingAs($this->admin);
        $nonaktif = User::factory()->for($this->ksm)->petugas()->nonaktif()->create();
        $lain = $this->diKsmLain(fn () => User::factory()->for(Ksm::factory())->petugas()->create());

        foreach ([$nonaktif, $lain, $this->admin] as $salah) {
            Livewire::test(CreatePengangkutan::class)
                ->fillForm(['petugas_id' => $salah->id, 'rincian' => [$this->baris($this->rumahA1)]])
                ->call('create')
                ->assertHasFormErrors(['petugas_id' => 'in']);
        }
    }

    public function test_list_totals_the_estimated_weight(): void
    {
        $ronde = $this->ronde($this->petugasA, '2026-11-17');
        PengangkutanRincian::factory()->for($ronde)->create(['pelanggan_id' => $this->rumahA1->id, 'perkiraan_kg' => 4.5]);
        PengangkutanRincian::factory()->for($ronde)->create(['pelanggan_id' => $this->rumahA2->id, 'status' => StatusPengangkutan::TidakAdaSampah]);
        $kemarin = $this->ronde($this->petugasB, '2026-11-16');
        PengangkutanRincian::factory()->for($kemarin)->create(['pelanggan_id' => $this->rumahB->id, 'perkiraan_kg' => 3]);
        $this->actingAs($this->admin);

        Livewire::test(ListPengangkutans::class)
            ->assertTableColumnStateSet('rumah_diangkut', 1, $ronde)
            ->assertTableColumnStateSet('total_kg', 4.5, $ronde)
            ->assertTableColumnSummarySet('total_kg', 'total', 7.5);
    }
}
