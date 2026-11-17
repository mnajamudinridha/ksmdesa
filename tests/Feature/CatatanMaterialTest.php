<?php

namespace Tests\Feature;

use App\Enums\AktivitasMaterial;
use App\Enums\JenisMitra;
use App\Filament\Ksm\Resources\CatatanMaterials\Pages\ManageCatatanMaterials;
use App\Filament\Ksm\Resources\JenisMaterials\Pages\ManageJenisMaterials;
use App\Filament\Ksm\Resources\Mitras\Pages\ManageMitras;
use App\Filament\Shared\PilihanMaster;
use App\Models\CatatanMaterial;
use App\Models\JenisMaterial;
use App\Models\Ksm;
use App\Models\Mitra;
use App\Models\Pelanggan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 5C: the material log and its master list (material kinds) — admins only; a sale names
 * an active buyer ("pengepul") of this KSM and its value.
 */
class CatatanMaterialTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    private JenisMaterial $pet;

    private Mitra $pengepul;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->pet = JenisMaterial::factory()->for($this->ksm)->create(['nama' => 'Plastik PET']);
        $this->pengepul = Mitra::factory()->for($this->ksm)->create(['nama' => 'Bank Sampah Lestari', 'jenis' => JenisMitra::Pengepul]);
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

    /** @param  array<string, mixed>  $data */
    private function catat(array $data): Testable
    {
        return Livewire::test(ManageCatatanMaterials::class)
            ->callAction('create', data: [
                'aktivitas' => AktivitasMaterial::Pilah->value,
                'tanggal' => '2026-11-16',
                'jenis_material_id' => $this->pet->id,
                'berat_kg' => 12.5,
                ...$data,
            ]);
    }

    public function test_admin_records_sorting_for_this_ksm_as_themselves(): void
    {
        $this->catat([])->assertHasNoFormErrors();

        $catatan = CatatanMaterial::query()->sole();
        $this->assertTrue($catatan->ksm->is($this->ksm));
        $this->assertSame([$this->admin->id, AktivitasMaterial::Pilah, '12.5'], [$catatan->dicatat_oleh_id, $catatan->aktivitas, $catatan->berat_kg]);
    }

    public function test_a_sale_needs_its_buyer_and_value(): void
    {
        $this->catat(['aktivitas' => AktivitasMaterial::Jual->value])
            ->assertHasFormErrors(['mitra_id' => 'required', 'nilai_jual' => 'required']);

        $this->catat(['aktivitas' => AktivitasMaterial::Jual->value, 'mitra_id' => $this->pengepul->id, 'nilai_jual' => 30_000])
            ->assertHasNoFormErrors();

        $this->assertSame([$this->pengepul->id, 30_000], [CatatanMaterial::query()->sole()->mitra_id, CatatanMaterial::query()->sole()->nilai_jual]);
    }

    public function test_buyer_and_value_belong_to_sales_and_the_result_to_processing_only(): void
    {
        $pilah = CatatanMaterial::factory()->for($this->ksm)->create(['mitra_id' => $this->pengepul->id, 'nilai_jual' => 99_000, 'hasil_olah' => 'kompos']);
        $olah = CatatanMaterial::factory()->for($this->ksm)->olah()->create(['hasil_olah' => 'kompos 12 kg', 'nilai_jual' => 5_000]);

        $this->assertSame([null, null, null], [$pilah->refresh()->mitra_id, $pilah->nilai_jual, $pilah->hasil_olah]);
        $this->assertSame(['kompos 12 kg', null], [$olah->refresh()->hasil_olah, $olah->nilai_jual]);
    }

    public function test_buyer_options_are_this_ksms_active_pengepul_only(): void
    {
        $swasta = Mitra::factory()->for($this->ksm)->create(['nama' => 'PT Peduli', 'jenis' => JenisMitra::Swasta]);
        $nonaktif = Mitra::factory()->for($this->ksm)->nonaktif()->create(['nama' => 'Pengepul Lama', 'jenis' => JenisMitra::Pengepul]);
        $lain = $this->diKsmLain(fn () => Mitra::factory()->for(Ksm::factory())->create(['nama' => 'Pengepul KSM lain', 'jenis' => JenisMitra::Pengepul]));

        $this->assertSame([$this->pengepul->id => 'Bank Sampah Lestari'], PilihanMaster::pengepul());

        foreach ([$swasta, $nonaktif, $lain] as $tidakSah) {
            $this->catat(['aktivitas' => AktivitasMaterial::Jual->value, 'mitra_id' => $tidakSah->id, 'nilai_jual' => 10_000])
                ->assertHasFormErrors(['mitra_id' => 'in']);
        }
        $this->assertSame(0, CatatanMaterial::query()->count());
    }

    public function test_editing_a_sale_whose_buyer_was_deactivated_keeps_it(): void
    {
        $catatan = CatatanMaterial::factory()->for($this->ksm)->jual(40_000)->create(['jenis_material_id' => $this->pet->id, 'mitra_id' => $this->pengepul->id]);
        $this->pengepul->update(['aktif' => false]);

        Livewire::test(ManageCatatanMaterials::class)
            ->callAction(TestAction::make('edit')->table($catatan), data: ['berat_kg' => 15])
            ->assertHasNoFormErrors();

        $this->assertSame([$this->pengepul->id, '15.0'], [$catatan->refresh()->mitra_id, $catatan->berat_kg]);
    }

    public function test_buyer_filter_still_finds_sales_to_a_past_buyer(): void
    {
        $lama = Mitra::factory()->for($this->ksm)->create(['nama' => 'Pengepul Lama', 'jenis' => JenisMitra::Pengepul]);
        $berubahJenis = Mitra::factory()->for($this->ksm)->create(['nama' => 'CV Daur', 'jenis' => JenisMitra::Pengepul]);
        Mitra::factory()->for($this->ksm)->create(['nama' => 'PT Peduli', 'jenis' => JenisMitra::Swasta]);
        $jualLama = CatatanMaterial::factory()->for($this->ksm)->jual()->create(['mitra_id' => $lama->id]);
        $jualLain = CatatanMaterial::factory()->for($this->ksm)->jual()->create(['mitra_id' => $this->pengepul->id]);
        CatatanMaterial::factory()->for($this->ksm)->jual()->create(['mitra_id' => $berubahJenis->id]);
        $lama->update(['aktif' => false]);
        $berubahJenis->update(['jenis' => JenisMitra::Swasta]);

        $this->assertSame(['Bank Sampah Lestari', 'CV Daur', 'Pengepul Lama (nonaktif)'], array_values(PilihanMaster::semuaPengepul()));

        Livewire::test(ManageCatatanMaterials::class)
            ->filterTable('mitra_id', $lama->id)
            ->assertCanSeeTableRecords([$jualLama])
            ->assertCanNotSeeTableRecords([$jualLain]);
    }

    public function test_kind_options_are_this_ksms_active_kinds_even_outside_its_panel(): void
    {
        JenisMaterial::factory()->for($this->ksm)->nonaktif()->create(['nama' => 'Styrofoam']);
        $this->diKsmLain(fn () => JenisMaterial::factory()->for(Ksm::factory())->create(['nama' => 'Kardus KSM lain']));
        $this->diKsmLain(fn () => Mitra::factory()->for(Ksm::factory())->create(['nama' => 'Pengepul KSM lain', 'jenis' => JenisMitra::Pengepul]));

        // Filament's tenant scope only runs while the ksm panel is current.
        Filament::setCurrentPanel('admin');

        $this->assertSame([$this->pet->id => 'Plastik PET'], PilihanMaster::jenisMaterial());
        $this->assertSame([$this->pengepul->id => 'Bank Sampah Lestari'], PilihanMaster::pengepul());
        $this->assertSame(['Plastik PET', 'Styrofoam'], array_values(PilihanMaster::semuaJenisMaterial()));
        $this->assertSame(['Bank Sampah Lestari'], array_values(PilihanMaster::semuaPengepul()));
    }

    public function test_weight_limits(): void
    {
        $this->catat(['berat_kg' => 0])->assertHasFormErrors(['berat_kg' => 'min']);
        $this->catat(['berat_kg' => 100_001])->assertHasFormErrors(['berat_kg' => 'max']);
        $this->catat(['berat_kg' => 0.1])->assertHasNoFormErrors();
        $this->catat(['tanggal' => '2026-11-18'])->assertHasFormErrors(['tanggal']);

        $this->assertSame(1, CatatanMaterial::query()->count());
    }

    public function test_used_kind_and_buyer_cannot_be_deleted(): void
    {
        $kosong = JenisMaterial::factory()->for($this->ksm)->create(['nama' => 'Kaleng']);
        CatatanMaterial::factory()->for($this->ksm)->jual()->create(['jenis_material_id' => $this->pet->id, 'mitra_id' => $this->pengepul->id]);

        Livewire::test(ManageJenisMaterials::class)
            ->assertActionHidden(TestAction::make('delete')->table($this->pet))
            ->callAction(TestAction::make('delete')->table($kosong));
        Livewire::test(ManageMitras::class)
            ->assertActionHidden(TestAction::make('delete')->table($this->pengepul));

        $this->assertFalse($this->admin->can('delete', $this->pet));
        $this->assertFalse($this->admin->can('delete', $this->pengepul));
        $this->assertModelMissing($kosong);
    }

    public function test_kind_name_is_unique_within_the_ksm(): void
    {
        Livewire::test(ManageJenisMaterials::class)
            ->callAction('create', data: ['nama' => 'Plastik PET', 'kelompok' => 'plastik'])
            ->assertHasFormErrors(['nama' => 'Jenis material dengan nama ini sudah ada.']);

        $this->assertSame(1, JenisMaterial::query()->count());
    }

    public function test_petugas_and_warga_are_forbidden_from_material_and_the_new_analytics_and_reports(): void
    {
        $pengguna = [User::factory()->for($this->ksm)->petugas()->create(), Pelanggan::factory()->for($this->ksm)->create()->akunWarga];
        $halaman = ['catatan-materials', 'jenis-materials', 'analitik/lingkungan', 'analitik/kemitraan', 'laporan/lingkungan', 'laporan/kemitraan', 'laporan/kinerja'];

        foreach ($pengguna as $satu) {
            $this->flushSession();
            $this->actingAs($satu);

            foreach ($halaman as $menu) {
                $this->get("http://makmurjaya.ksmdesa.test/{$menu}")->assertForbidden();
            }
        }
    }

    public function test_admin_opens_the_new_analytics_and_reports(): void
    {
        foreach (['analitik/lingkungan' => 'Analitik Lingkungan', 'analitik/kemitraan' => 'Analitik Kemitraan', 'laporan/lingkungan' => 'Laporan Lingkungan', 'laporan/kemitraan' => 'Laporan Kemitraan', 'laporan/kinerja' => 'Laporan Kinerja Terintegrasi'] as $menu => $judul) {
            $this->get("http://makmurjaya.ksmdesa.test/{$menu}")->assertOk()->assertSee($judul);
        }
    }

    public function test_deleting_a_ksm_removes_its_material_log(): void
    {
        $catatan = CatatanMaterial::factory()->for($this->ksm)->jual()->create(['jenis_material_id' => $this->pet->id, 'mitra_id' => $this->pengepul->id]);

        $this->ksm->delete();

        $this->assertModelMissing($catatan);
        $this->assertModelMissing($this->pet);
        $this->assertModelMissing($this->pengepul);
    }
}
