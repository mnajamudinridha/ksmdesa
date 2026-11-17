<?php

namespace Tests\Feature;

use App\Filament\Ksm\Pages\Dashboard as DasborKsm;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as DasborMaster;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Dashboard grid rule (docs/README.md, "Grid dasbor"): on every dashboard a role sees, no row is
 * left half empty — half-width widgets come in pairs — and every group of stat cards fills whole
 * rows of its columns.
 */
class GridDasborTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));
        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
    }

    /** @return array<string, array{0: string, 1: int, 2: bool}> role, warga houses, fee data */
    public static function pengguna(): array
    {
        return [
            'admin tanpa data' => ['admin', 0, false],
            'admin dengan data iuran' => ['admin', 0, true],
            'petugas' => ['petugas', 0, false],
            'warga satu rumah' => ['warga', 1, false],
            'warga dua rumah' => ['warga', 2, false],
            'warga tiga rumah' => ['warga', 3, false],
            'warga belum terhubung' => ['warga', 0, false],
        ];
    }

    #[DataProvider('pengguna')]
    public function test_village_dashboard_has_no_half_empty_row(string $peran, int $rumah, bool $adaIuran): void
    {
        $pengguna = $this->penggunaDesa($peran, $rumah);

        if ($adaIuran) {
            PembayaranIuran::factory()->for(Pelanggan::factory()->for($this->ksm))->create(['periode' => '2026-11-01']);
        }

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
        $this->actingAs($pengguna);

        $this->assertBarisPenuh((new DasborKsm)->getWidgets());
    }

    public function test_master_dashboard_has_no_half_empty_row(): void
    {
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->assertBarisPenuh((new DasborMaster)->getWidgets());
    }

    private function penggunaDesa(string $peran, int $rumah): User
    {
        if ($peran !== 'warga') {
            return User::factory()->for($this->ksm)->{$peran}()->create();
        }

        if ($rumah === 0) {
            return User::factory()->for($this->ksm)->warga()->create();
        }

        $warga = Pelanggan::factory()->for($this->ksm)->create()->akunWarga;
        Pelanggan::factory()->for($this->ksm)->count($rumah - 1)->create(['user_id' => $warga->id]);

        return $warga;
    }

    /** @param  array<int, class-string<Widget>>  $widgets */
    private function assertBarisPenuh(array $widgets): void
    {
        $terlihat = array_values(array_filter($widgets, fn (string $widget): bool => $widget::canView()));
        $separuhTerbuka = null;

        foreach ($terlihat as $widget) {
            if ((new $widget)->getColumnSpan() !== 'full') {
                $separuhTerbuka = $separuhTerbuka === null ? $widget : null;

                continue;
            }

            $this->assertNull($separuhTerbuka, class_basename((string) $separuhTerbuka).' sendirian di barisnya');

            if (is_subclass_of($widget, StatsOverviewWidget::class)) {
                $this->assertKartuMengisiBaris($widget);
            }
        }

        $this->assertNull($separuhTerbuka, class_basename((string) $separuhTerbuka).' sendirian di baris terakhir');
    }

    /** @param  class-string<StatsOverviewWidget>  $widget */
    private function assertKartuMengisiBaris(string $widget): void
    {
        $komponen = Livewire::test($widget)->instance();
        $kartu = count((fn (): array => $this->getCachedStats())->call($komponen));
        $kolom = (fn (): int|array|null => $this->getColumns())->call($komponen);
        $kolomLebar = is_array($kolom) ? $kolom['@xl'] : $kolom;

        $this->assertSame(0, $kartu % $kolomLebar, class_basename($widget)." menaruh {$kartu} kartu dalam {$kolomLebar} kolom");
    }
}
