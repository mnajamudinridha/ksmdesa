<?php

namespace Tests\Feature;

use App\Enums\StatusPengangkutan;
use App\Filament\Ksm\Resources\Pengangkutans\PengangkutanResource;
use App\Filament\Ksm\Resources\Users\UserResource;
use App\Filament\Ksm\Widgets\SorotanKsm;
use App\Filament\Resources\Ksms\KsmResource;
use App\Filament\Widgets\SorotanMaster;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Models\User;
use App\Support\Hari;
use App\Support\RingkasanPengangkutan;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The companion of "Selamat datang" on every dashboard: today's date in village time and one line
 * on what needs attention — per KSM on the master panel, per role in a village panel.
 *
 * Today is Tuesday 2026-11-17, 07:00 WITA (still the 16th in UTC). Village fixture, KSM
 * "makmurjaya": active petugas Andi (round today), Budi (last round Saturday 14 Nov) and Citra
 * (never); inactive petugas Dodi; warga W's house collected yesterday and "no waste" today, the
 * neighbour's house collected today. Another KSM: active petugas Eko, no rounds.
 */
class SorotanDasborTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $andi;

    private User $budi;

    private User $citra;

    private User $warga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 07:00', 'Asia/Makassar'));
    }

    private function siapkanDesa(): void
    {
        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->andi = User::factory()->for($this->ksm)->petugas()->create(['name' => 'Andi']);
        $this->budi = User::factory()->for($this->ksm)->petugas()->create(['name' => 'Budi']);
        $this->citra = User::factory()->for($this->ksm)->petugas()->create(['name' => 'Citra']);
        User::factory()->for($this->ksm)->petugas()->create(['name' => 'Dodi', 'aktif' => false]);

        $rumah = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah W']);
        $tetangga = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah Tetangga']);
        $this->warga = $rumah->akunWarga;

        $this->ronde($this->andi, '2026-11-17', [[$rumah, StatusPengangkutan::TidakAdaSampah], [$tetangga, StatusPengangkutan::Diangkut]]);
        $this->ronde($this->budi, '2026-11-16', [[$rumah, StatusPengangkutan::Diangkut]]);
        $this->ronde($this->budi, '2026-11-14', [[$tetangga, StatusPengangkutan::Diangkut]]);

        $lain = Ksm::factory()->create();
        User::factory()->for($lain)->petugas()->create(['name' => 'Eko']);

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
    }

    /** @param  list<array{0: Pelanggan, 1: StatusPengangkutan}>  $rincian */
    private function ronde(User $petugas, string $tanggal, array $rincian): void
    {
        $ronde = Pengangkutan::factory()->for($petugas->ksm)->create(['petugas_id' => $petugas->id, 'tanggal' => $tanggal]);

        foreach ($rincian as [$pelanggan, $status]) {
            PengangkutanRincian::factory()->for($ronde)->create(['pelanggan_id' => $pelanggan->id, 'status' => $status]);
        }
    }

    public function test_dates_are_said_the_way_people_say_them_in_village_time(): void
    {
        $this->assertSame(
            ['hari ini', 'kemarin', 'Sabtu, 14 November', 'Rabu, 31 Desember 2025'],
            [Hari::sebut('2026-11-17'), Hari::sebut('2026-11-16 00:00:00'), Hari::sebut('2026-11-14'), Hari::sebut('2025-12-31')],
        );
    }

    public function test_master_lists_ksms_that_recorded_nothing_lately_and_shortens_a_long_list(): void
    {
        foreach (['KSM Damai', 'KSM Ceria', 'KSM Bahagia', 'KSM Asri'] as $nama) {
            Ksm::factory()->create(['nama' => $nama]);
        }
        $aktif = Ksm::factory()->create(['nama' => 'KSM Aktif']);
        PembayaranIuran::factory()->for(Pelanggan::factory()->for($aktif))->create();
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(SorotanMaster::class)
            ->assertSee('Selasa, 17 November 2026')
            ->assertSee('Belum mencatat 7 hari: KSM Asri, KSM Bahagia, KSM Ceria dan 1 lainnya')
            ->assertDontSee('KSM Aktif')
            ->assertSee(KsmResource::getUrl('index'));
    }

    public function test_master_says_when_every_ksm_recorded_and_when_there_is_none_yet(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->superAdmin()->create());

        $tanpaKsm = Livewire::test(SorotanMaster::class);
        PembayaranIuran::factory()->for(Pelanggan::factory()->for(Ksm::factory()))->create();

        $tanpaKsm->assertSee('Belum ada KSM terdaftar.')->assertSee(KsmResource::getUrl('create'));
        Livewire::test(SorotanMaster::class)->assertSee('Semua KSM mencatat dalam 7 hari terakhir.');
    }

    public function test_admin_sees_the_active_petugas_without_a_round_today(): void
    {
        $this->siapkanDesa();
        $this->actingAs(User::factory()->for($this->ksm)->admin()->create());

        Livewire::test(SorotanKsm::class)
            ->assertSee('Selasa, 17 November 2026')
            ->assertSee('Belum mencatat pengangkutan hari ini: Budi, Citra')
            ->assertSee(PengangkutanResource::getUrl('index'));

        $this->ronde($this->budi, '2026-11-17', []);
        $this->ronde($this->citra, '2026-11-17', []);

        Livewire::test(SorotanKsm::class)->assertSee('Semua petugas aktif sudah mencatat pengangkutan hari ini.');
    }

    public function test_admin_of_a_ksm_without_active_petugas_is_sent_to_the_user_menu(): void
    {
        $this->siapkanDesa();
        User::query()->whereBelongsTo($this->ksm)->where('peran', 'petugas')->update(['aktif' => false]);
        $this->actingAs(User::factory()->for($this->ksm)->admin()->create());

        Livewire::test(SorotanKsm::class)
            ->assertSee('Belum ada petugas aktif')
            ->assertSee(UserResource::getUrl('index'));
    }

    public function test_petugas_sees_their_own_last_round_and_a_shortcut_to_record_one(): void
    {
        $this->siapkanDesa();

        $this->actingAs($this->budi);
        Livewire::test(SorotanKsm::class)
            ->assertSee('Pengangkutan terakhir: kemarin.')
            ->assertSee(PengangkutanResource::getUrl('create'));

        $this->actingAs($this->citra);
        Livewire::test(SorotanKsm::class)->assertSee('Belum pernah mencatat pengangkutan.');
    }

    public function test_warga_sees_the_last_day_waste_was_collected_from_their_own_house(): void
    {
        $this->siapkanDesa();
        $this->actingAs($this->warga);

        Livewire::test(SorotanKsm::class)
            ->assertSee('Sampah terakhir diangkut dari rumah Anda: kemarin.')
            ->assertDontSee(PengangkutanResource::getUrl('create'));

        $this->actingAs(Pelanggan::factory()->for($this->ksm)->create()->akunWarga);
        Livewire::test(SorotanKsm::class)->assertSee('Belum ada catatan sampah diangkut dari rumah Anda.');
    }

    public function test_every_village_role_sees_the_companion_and_only_master_admins_see_the_master_one(): void
    {
        $this->siapkanDesa();

        foreach ([User::factory()->for($this->ksm)->admin()->create(), $this->budi, $this->warga] as $pengguna) {
            $this->actingAs($pengguna);
            $this->assertTrue(SorotanKsm::canView(), $pengguna->peran->value);
            $this->assertFalse(SorotanMaster::canView(), $pengguna->peran->value);
        }

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->assertTrue(SorotanMaster::canView());
    }

    public function test_figures_stay_within_the_given_ksm_even_outside_its_panel(): void
    {
        $this->siapkanDesa();
        // Filament's tenant scope only runs while the ksm panel is current.
        Filament::setCurrentPanel('admin');
        $lain = Ksm::query()->whereKeyNot($this->ksm->getKey())->sole();

        $this->assertSame(['Budi', 'Citra'], (new RingkasanPengangkutan($this->ksm))->petugasBelumMencatat('2026-11-17')->all());
        $this->assertSame('2026-11-16', (new RingkasanPengangkutan($this->ksm))->rondeTerakhirOleh($this->budi));
        $this->assertNull((new RingkasanPengangkutan($lain))->rondeTerakhirOleh($this->budi));
        $this->assertSame('2026-11-16', (new RingkasanPengangkutan($this->ksm))->diangkutTerakhirUntuk($this->warga));
        $this->assertNull((new RingkasanPengangkutan($lain))->diangkutTerakhirUntuk($this->warga));
    }
}
