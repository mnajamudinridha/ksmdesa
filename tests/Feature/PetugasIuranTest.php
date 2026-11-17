<?php

namespace Tests\Feature;

use App\Filament\Ksm\Resources\PembayaranIurans\Pages\ManagePembayaranIurans;
use App\Filament\Ksm\Widgets\DaftarTugasHariIni;
use App\Filament\Ksm\Widgets\RiwayatIuranSaya;
use App\Filament\Ksm\Widgets\WargaStatsOverview;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\Setoran;
use App\Models\User;
use App\Support\TerimaIuran;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 4: a petugas records fee cash taken at their own houses (several months at once,
 * amount editable). It counts as paid immediately; they may correct it until deposited.
 */
class PetugasIuranTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    private User $petugasA;

    private User $petugasB;

    private Pelanggan $rumahA;

    private Pelanggan $rumahB;

    private Pelanggan $rumahNonaktif;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->petugasA = User::factory()->for($this->ksm)->petugas()->create();
        $this->petugasB = User::factory()->for($this->ksm)->petugas()->create();
        $this->rumahA = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah A', 'petugas_id' => $this->petugasA->id, 'iuran_bulanan' => 25_000]);
        $this->rumahB = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah B', 'petugas_id' => $this->petugasB->id]);
        $this->rumahNonaktif = Pelanggan::factory()->for($this->ksm)->create(['petugas_id' => $this->petugasA->id, 'aktif' => false]);

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
    }

    private function terimaIuran(array $data): mixed
    {
        return Livewire::test(DaftarTugasHariIni::class)
            ->callAction(TestAction::make('terimaIuran')->table($this->rumahA), data: $data);
    }

    public function test_petugas_records_several_months_that_count_as_paid_immediately(): void
    {
        $this->actingAs($this->petugasA);

        $this->terimaIuran(['bulan' => ['2026-10-01', '2026-11-01']])->assertHasNoFormErrors();

        $pembayaran = PembayaranIuran::query()->orderBy('periode')->get();
        $this->assertSame(['2026-10-01', '2026-11-01'], $pembayaran->map(fn (PembayaranIuran $p): string => $p->periode->toDateString())->all());
        $this->assertSame([25_000, 25_000], $pembayaran->pluck('nominal')->all());
        $this->assertSame([$this->petugasA->id], $pembayaran->pluck('petugas_id')->unique()->values()->all());
        $this->assertSame([$this->ksm->id], $pembayaran->pluck('ksm_id')->unique()->values()->all());
        $this->assertSame('2026-11-17', $pembayaran->first()->dibayar_pada->toDateString());
        $this->assertSame('belum', $pembayaran->first()->statusSetor());

        $this->actingAs($this->rumahA->akunWarga);
        Livewire::test(WargaStatsOverview::class)->assertSee('Lunas');
    }

    public function test_paid_or_out_of_window_months_are_rejected(): void
    {
        PembayaranIuran::factory()->for($this->rumahA)->create(['periode' => '2026-11-01']);
        $this->actingAs($this->petugasA);

        foreach (['2026-11-01', '2026-04-01', '2027-02-01'] as $bulan) {
            $this->terimaIuran(['bulan' => [$bulan]])->assertHasFormErrors(['bulan.0' => 'in']);
        }

        $this->assertSame(1, PembayaranIuran::query()->count());
    }

    public function test_a_changed_amount_shows_as_partial_payment(): void
    {
        $this->actingAs($this->petugasA);
        $this->terimaIuran(['bulan' => ['2026-11-01'], 'nominal' => 15_000])->assertHasNoFormErrors();

        $this->actingAs($this->rumahA->akunWarga);
        Livewire::test(WargaStatsOverview::class)->assertSee('Kurang bayar');
    }

    public function test_petugas_takes_fees_only_at_their_own_active_houses(): void
    {
        $this->assertTrue($this->petugasA->can('terimaIuran', $this->rumahA));
        $this->assertFalse($this->petugasA->can('terimaIuran', $this->rumahB));
        $this->assertFalse($this->petugasA->can('terimaIuran', $this->rumahNonaktif));
        $this->assertFalse($this->admin->can('terimaIuran', $this->rumahA));

        $this->actingAs($this->petugasA);
        Livewire::test(DaftarTugasHariIni::class)
            ->assertActionVisible(TestAction::make('terimaIuran')->table($this->rumahA))
            ->assertCanNotSeeTableRecords([$this->rumahB, $this->rumahNonaktif]);
    }

    public function test_receipt_date_is_today_or_yesterday(): void
    {
        $this->actingAs($this->petugasA);

        $this->terimaIuran(['bulan' => ['2026-11-01'], 'dibayar_pada' => '2026-11-15'])->assertHasFormErrors(['dibayar_pada']);
        $this->terimaIuran(['bulan' => ['2026-11-01'], 'dibayar_pada' => '2026-11-16'])->assertHasNoFormErrors();
    }

    public function test_petugas_corrects_own_payments_until_they_are_deposited(): void
    {
        TerimaIuran::catat($this->rumahA, $this->petugasA, ['2026-11-01'], 25_000, '2026-11-17');
        TerimaIuran::catat($this->rumahB, $this->petugasB, ['2026-11-01'], 25_000, '2026-11-17');
        [$milikA, $milikB] = [PembayaranIuran::query()->where('petugas_id', $this->petugasA->id)->sole(), PembayaranIuran::query()->where('petugas_id', $this->petugasB->id)->sole()];
        $this->actingAs($this->petugasA);

        Livewire::test(ManagePembayaranIurans::class)
            ->assertCanSeeTableRecords([$milikA])
            ->assertCanNotSeeTableRecords([$milikB])
            ->assertActionHidden('create')
            ->callAction(TestAction::make('edit')->table($milikA), data: ['nominal' => 20_000])
            ->assertHasNoFormErrors();
        $this->assertSame(20_000, $milikA->refresh()->nominal);

        $setoran = Setoran::factory()->for($this->ksm)->create(['petugas_id' => $this->petugasA->id]);
        $milikA->forceFill(['setoran_id' => $setoran->id])->save();

        Livewire::test(ManagePembayaranIurans::class)
            ->assertActionHidden(TestAction::make('edit')->table($milikA))
            ->assertActionHidden(TestAction::make('delete')->table($milikA));
        $this->assertFalse($this->petugasA->can('update', $milikA->refresh()));
        $this->assertTrue($this->admin->can('update', $milikA));
        $this->assertFalse($this->admin->can('delete', $milikA));
        $this->assertTrue($this->admin->can('delete', $milikB));
    }

    public function test_petugas_corrections_keep_the_amount_limits_and_the_real_customer(): void
    {
        TerimaIuran::catat($this->rumahA, $this->petugasA, ['2026-10-01', '2026-11-01'], 25_000, '2026-11-17');
        $november = PembayaranIuran::query()->whereDate('periode', '2026-11-01')->sole();
        $this->actingAs($this->petugasA);

        Livewire::test(ManagePembayaranIurans::class)
            ->callAction(TestAction::make('edit')->table($november), data: ['nominal' => 99_999_999])
            ->assertHasFormErrors(['nominal' => 'max']);

        // A tampered (disabled) customer must not steer the duplicate-month check: October is
        // already paid for this payment's real customer, so it is a validation error, not a 500.
        Livewire::test(ManagePembayaranIurans::class)
            ->callAction(TestAction::make('edit')->table($november), data: ['pelanggan_id' => $this->rumahB->id, 'periode' => '2026-10-01'])
            ->assertHasFormErrors(['periode']);

        $this->assertSame('2026-11-01', $november->refresh()->periode->toDateString());
        $this->assertSame($this->rumahA->id, $november->pelanggan_id);
    }

    public function test_petugas_can_also_record_from_the_iuran_diterima_page(): void
    {
        $this->actingAs($this->petugasA);

        Livewire::test(ManagePembayaranIurans::class)
            ->assertActionVisible('terimaIuran')
            ->callAction('terimaIuran', data: ['pelanggan_id' => $this->rumahA->id, 'bulan' => ['2026-11-01']])
            ->assertHasNoFormErrors();

        $pembayaran = PembayaranIuran::query()->sole();
        $this->assertTrue($pembayaran->pelanggan->is($this->rumahA));
        $this->assertSame($this->petugasA->id, $pembayaran->petugas_id);
        $this->assertSame(25_000, $pembayaran->nominal);

        // Another petugas's house or an inactive one is not on the list, so it is rejected.
        foreach ([$this->rumahB, $this->rumahNonaktif] as $bukan) {
            Livewire::test(ManagePembayaranIurans::class)
                ->callAction('terimaIuran', data: ['pelanggan_id' => $bukan->id, 'bulan' => ['2026-10-01'], 'nominal' => 25_000])
                ->assertHasFormErrors(['pelanggan_id' => 'in']);
        }

        $this->assertSame(1, PembayaranIuran::query()->count());

        $this->actingAs($this->admin);
        Livewire::test(ManagePembayaranIurans::class)->assertActionHidden('terimaIuran');
    }

    public function test_warga_history_says_who_received_the_cash(): void
    {
        TerimaIuran::catat($this->rumahA, $this->petugasA, ['2026-11-01'], 25_000, '2026-11-17');
        $this->actingAs($this->rumahA->akunWarga);

        Livewire::test(RiwayatIuranSaya::class)->assertSee($this->petugasA->name);
    }
}
