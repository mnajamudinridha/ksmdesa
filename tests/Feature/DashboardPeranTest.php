<?php

namespace Tests\Feature;

use App\Enums\StatusPengangkutan;
use App\Filament\Ksm\Widgets\BeratPerHariChart;
use App\Filament\Ksm\Widgets\DaftarTugasHariIni;
use App\Filament\Ksm\Widgets\IuranStatsOverview;
use App\Filament\Ksm\Widgets\KomposisiPengeluaranChart;
use App\Filament\Ksm\Widgets\OperasionalStatsOverview;
use App\Filament\Ksm\Widgets\RiwayatIuranSaya;
use App\Filament\Ksm\Widgets\RiwayatPengangkutanSaya;
use App\Filament\Ksm\Widgets\TugasPetugasStatsOverview;
use App\Filament\Ksm\Widgets\WargaStatsOverview;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use App\Models\User;
use App\Support\AkunWarga;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Each role gets its own dashboard: admins the KSM's operations, a petugas today's houses,
 * a warga only their own houses — never anyone else's.
 */
class DashboardPeranTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    private User $petugas;

    private User $warga;

    private Pelanggan $rumahWarga;

    private Pelanggan $rumahTetangga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->petugas = User::factory()->for($this->ksm)->petugas()->create(['name' => 'Pak Angkut', 'no_hp' => '081299990000']);

        $this->rumahWarga = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah Saya', 'petugas_id' => $this->petugas->id, 'iuran_bulanan' => 25_000, 'nomor_urut' => 1]);
        $this->rumahTetangga = Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Rumah Tetangga', 'petugas_id' => $this->petugas->id, 'nomor_urut' => 2]);
        $this->warga = $this->rumahWarga->akunWarga;
        AkunWarga::sinkronkanNomor($this->rumahWarga, '081211112222');

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
    }

    private function ronde(string $tanggal, array $rincian): Pengangkutan
    {
        $ronde = Pengangkutan::factory()->for($this->ksm)->create(['petugas_id' => $this->petugas->id, 'tanggal' => $tanggal]);

        foreach ($rincian as [$pelanggan, $status, $kg]) {
            PengangkutanRincian::factory()->for($ronde)->create(['pelanggan_id' => $pelanggan->id, 'status' => $status, 'perkiraan_kg' => $kg]);
        }

        return $ronde;
    }

    /**
     * @return array<string, array{0: string, 1: array<int, class-string>}>
     */
    public static function widgetPerPeran(): array
    {
        $admin = [IuranStatsOverview::class, OperasionalStatsOverview::class, BeratPerHariChart::class, KomposisiPengeluaranChart::class];
        $petugas = [TugasPetugasStatsOverview::class, DaftarTugasHariIni::class];
        $warga = [WargaStatsOverview::class, RiwayatIuranSaya::class, RiwayatPengangkutanSaya::class];

        return [
            'admin' => ['admin', $admin],
            'petugas' => ['petugas', $petugas],
            'warga' => ['warga', $warga],
        ];
    }

    /**
     * @param  array<int, class-string>  $milikPeran
     */
    #[DataProvider('widgetPerPeran')]
    public function test_each_role_sees_only_its_own_widgets(string $peran, array $milikPeran): void
    {
        $this->actingAs($this->{$peran});
        $semua = array_merge(...array_column(self::widgetPerPeran(), 1));

        foreach ($semua as $widget) {
            $this->assertSame(in_array($widget, $milikPeran, true), $widget::canView(), "{$peran} × ".class_basename($widget));
        }
    }

    public function test_admin_operations_count_today_and_this_village_month(): void
    {
        $this->ronde('2026-11-17', [[$this->rumahWarga, StatusPengangkutan::Diangkut, 4.5], [$this->rumahTetangga, StatusPengangkutan::TidakAdaSampah, null]]);
        $this->ronde('2026-11-03', [[$this->rumahWarga, StatusPengangkutan::Diangkut, 3]]);
        $this->ronde('2026-10-31', [[$this->rumahWarga, StatusPengangkutan::Diangkut, 50]]);
        Pelanggan::factory()->for($this->ksm)->create(['nama' => 'Tanpa Petugas']);
        $this->actingAs($this->admin);

        Livewire::test(OperasionalStatsOverview::class)
            ->assertSeeInOrder(['Diangkut hari ini', '1', 'dari 3 pelanggan aktif'])
            ->assertSee('7,5 kg')
            ->assertSeeInOrder(['Tanpa petugas aktif', '1']);
    }

    public function test_petugas_list_shows_todays_progress_and_links_to_todays_round(): void
    {
        $lain = User::factory()->for($this->ksm)->petugas()->create();
        $bukanTugas = Pelanggan::factory()->for($this->ksm)->create(['petugas_id' => $lain->id]);
        $this->actingAs($this->petugas);

        Livewire::test(DaftarTugasHariIni::class)
            ->assertCanSeeTableRecords([$this->rumahWarga, $this->rumahTetangga], inOrder: true)
            ->assertCanNotSeeTableRecords([$bukanTugas])
            ->assertTableColumnStateNotSet('status_hari_ini', 'diangkut', $this->rumahWarga)
            ->assertActionHasUrl(TestAction::make('catatHariIni')->table(), 'http://makmurjaya.ksmdesa.test/pengangkutans/create');

        $ronde = $this->ronde('2026-11-17', [[$this->rumahWarga, StatusPengangkutan::Diangkut, 2]]);

        Livewire::test(DaftarTugasHariIni::class)
            ->assertTableColumnStateSet('status_hari_ini', 'diangkut', $this->rumahWarga)
            ->assertTableColumnStateNotSet('status_hari_ini', 'diangkut', $this->rumahTetangga)
            ->assertActionHasUrl(TestAction::make('catatHariIni')->table(), "http://makmurjaya.ksmdesa.test/pengangkutans/{$ronde->id}/edit");
    }

    public function test_warga_card_shows_this_months_status_and_their_petugas_contact(): void
    {
        PembayaranIuran::factory()->for($this->rumahWarga)->create(['periode' => '2026-11-01', 'nominal' => 25_000]);
        $this->actingAs($this->warga);

        Livewire::test(WargaStatsOverview::class)
            ->assertSee('Rumah Saya')
            ->assertSee('Lunas')
            ->assertSee('Pak Angkut (081299990000)')
            ->assertDontSee('Rumah Tetangga');
    }

    public function test_warga_history_contains_only_their_own_houses(): void
    {
        $milikSaya = PembayaranIuran::factory()->for($this->rumahWarga)->create(['periode' => '2026-10-01']);
        $milikTetangga = PembayaranIuran::factory()->for($this->rumahTetangga)->create(['periode' => '2026-10-01']);
        $ronde = $this->ronde('2026-11-16', [[$this->rumahWarga, StatusPengangkutan::Diangkut, 3], [$this->rumahTetangga, StatusPengangkutan::Diangkut, 9]]);
        [$angkutSaya, $angkutTetangga] = [$ronde->rincian()->where('pelanggan_id', $this->rumahWarga->id)->sole(), $ronde->rincian()->where('pelanggan_id', $this->rumahTetangga->id)->sole()];
        $this->actingAs($this->warga);

        Livewire::test(RiwayatIuranSaya::class)
            ->assertCanSeeTableRecords([$milikSaya])
            ->assertCanNotSeeTableRecords([$milikTetangga]);

        Livewire::test(RiwayatPengangkutanSaya::class)
            ->assertCanSeeTableRecords([$angkutSaya])
            ->assertCanNotSeeTableRecords([$angkutTetangga])
            ->assertSee('Pak Angkut');
    }

    public function test_dashboard_loads_for_every_role(): void
    {
        foreach (['admin', 'petugas', 'warga'] as $peran) {
            // One session per person, as in real life: AuthenticateSession pins the session to
            // the first user's password hash, and the warga's auto-created password differs.
            $this->flushSession();
            $this->actingAs($this->{$peran})->get('http://makmurjaya.ksmdesa.test/')->assertOk();
        }
    }
}
