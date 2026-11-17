<?php

namespace Tests\Feature;

use App\Enums\JenisTransaksi;
use App\Filament\Ksm\Pages\Tenancy\EditProfilKsm;
use App\Filament\Ksm\Resources\KategoriTransaksis\Pages\ManageKategoriTransaksis;
use App\Filament\Ksm\Resources\TransaksiKas\Pages\ManageTransaksiKas;
use App\Filament\Ksm\Widgets\KelengkapanDataStats;
use App\Filament\Shared\PilihanMaster;
use App\Models\KategoriTransaksi;
use App\Models\Ksm;
use App\Models\Mitra;
use App\Models\Pelanggan;
use App\Models\TransaksiKas;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\BacaNilaiStat;
use Tests\TestCase;

/**
 * Phase 5B: "Buku Kas" entries, their categories and receipts, and the cash book start in
 * Profil KSM — admins only.
 */
class BukuKasTest extends TestCase
{
    use BacaNilaiStat;
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    private KategoriTransaksi $bbm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-17 09:00', 'Asia/Makassar'));

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->bbm = KategoriTransaksi::factory()->for($this->ksm)->create(['nama' => 'BBM']);
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
        return Livewire::test(ManageTransaksiKas::class)
            ->callAction('create', data: [
                'jenis' => JenisTransaksi::Keluar->value,
                'tanggal' => '2026-11-16',
                'nominal' => 75_000,
                'kategori_transaksi_id' => $this->bbm->id,
                'uraian' => 'Bensin motor angkut',
                ...$data,
            ]);
    }

    public function test_spending_offers_only_this_ksms_active_spending_categories(): void
    {
        $nonaktif = KategoriTransaksi::factory()->for($this->ksm)->nonaktif()->create(['nama' => 'Lama']);
        $masuk = KategoriTransaksi::factory()->for($this->ksm)->masuk()->create(['nama' => 'Hibah']);
        $lain = $this->diKsmLain(fn () => KategoriTransaksi::factory()->for(Ksm::factory())->create(['nama' => 'BBM KSM lain']));

        $this->assertSame([$this->bbm->id => 'BBM'], PilihanMaster::kategori(JenisTransaksi::Keluar));

        foreach ([$masuk, $nonaktif, $lain] as $tidakSah) {
            $this->catat(['kategori_transaksi_id' => $tidakSah->id])->assertHasFormErrors(['kategori_transaksi_id' => 'in']);
        }
        $this->assertSame(0, TransaksiKas::query()->count());
    }

    public function test_category_and_partner_options_stay_within_this_ksm_even_outside_its_panel(): void
    {
        $mitra = Mitra::factory()->for($this->ksm)->create(['nama' => 'PT Peduli']);
        $this->diKsmLain(function (): void {
            $lain = Ksm::factory()->create();
            KategoriTransaksi::factory()->for($lain)->create(['nama' => 'BBM KSM lain']);
            Mitra::factory()->for($lain)->create(['nama' => 'Mitra KSM lain']);
        });

        // Filament's tenant scope only runs while the ksm panel is current.
        Filament::setCurrentPanel('admin');

        $this->assertSame([$this->bbm->id => 'BBM'], PilihanMaster::kategori(JenisTransaksi::Keluar));
        $this->assertSame([$mitra->id => 'PT Peduli'], PilihanMaster::mitra());
        $this->assertSame([$this->bbm->id => 'BBM (Pengeluaran)'], PilihanMaster::semuaKategori());
    }

    public function test_admin_records_spending_for_this_ksm_as_themselves(): void
    {
        $this->catat([])->assertHasNoFormErrors();

        $transaksi = TransaksiKas::query()->sole();
        $this->assertTrue($transaksi->ksm->is($this->ksm));
        $this->assertSame($this->admin->id, $transaksi->dicatat_oleh_id);
        $this->assertSame([JenisTransaksi::Keluar, 75_000, '2026-11-16'], [$transaksi->jenis, $transaksi->nominal, $transaksi->tanggal->toDateString()]);
    }

    public function test_rejects_a_future_date_and_a_zero_amount(): void
    {
        $this->catat(['tanggal' => '2026-11-18'])->assertHasFormErrors(['tanggal']);
        $this->catat(['nominal' => 0])->assertHasFormErrors(['nominal' => 'min']);
        $this->catat(['tanggal' => '2026-11-17', 'nominal' => 1])->assertHasNoFormErrors();

        $this->assertSame(1, TransaksiKas::query()->count());
    }

    public function test_editing_an_entry_whose_category_was_deactivated_keeps_it(): void
    {
        $transaksi = TransaksiKas::factory()->for($this->ksm)->create(['kategori_transaksi_id' => $this->bbm->id]);
        $this->bbm->update(['aktif' => false]);

        Livewire::test(ManageTransaksiKas::class)
            ->callAction(TestAction::make('edit')->table($transaksi), data: ['uraian' => 'Diperbaiki'])
            ->assertHasNoFormErrors();

        $this->assertSame([$this->bbm->id, 'Diperbaiki'], [$transaksi->refresh()->kategori_transaksi_id, $transaksi->uraian]);
    }

    public function test_only_income_keeps_a_partner(): void
    {
        $mitra = Mitra::factory()->for($this->ksm)->create();
        $hibah = KategoriTransaksi::factory()->for($this->ksm)->masuk()->create(['nama' => 'Hibah']);

        $keluar = TransaksiKas::factory()->for($this->ksm)->create(['mitra_id' => $mitra->id]);
        $this->catat(['jenis' => JenisTransaksi::Masuk->value, 'kategori_transaksi_id' => $hibah->id, 'mitra_id' => $mitra->id])->assertHasNoFormErrors();

        $this->assertNull($keluar->refresh()->mitra_id);
        $this->assertSame($mitra->id, TransaksiKas::query()->where('jenis', JenisTransaksi::Masuk)->sole()->mitra_id);
    }

    public function test_receipt_is_stored_privately_and_removed_with_its_entry_or_when_replaced(): void
    {
        Storage::fake('local', ['serve' => true]);

        $this->catat(['bukti' => UploadedFile::fake()->image('nota.jpg')])->assertHasNoFormErrors();

        $transaksi = TransaksiKas::query()->sole();
        $lama = $transaksi->bukti;
        $this->assertStringStartsWith("bukti-kas/{$this->ksm->id}/", $lama);
        Storage::disk('local')->assertExists($lama);

        Storage::disk('local')->put('bukti-kas/baru.pdf', '%PDF-1.4');
        $transaksi->update(['bukti' => 'bukti-kas/baru.pdf']);
        Storage::disk('local')->assertMissing($lama);

        Livewire::test(ManageTransaksiKas::class)->callAction(TestAction::make('delete')->table($transaksi));
        Storage::disk('local')->assertMissing('bukti-kas/baru.pdf');
        $this->assertModelMissing($transaksi);
    }

    public function test_receipt_is_downloaded_through_the_row_action_only_when_there_is_one(): void
    {
        Storage::fake('local', ['serve' => true]);
        Storage::disk('local')->put("bukti-kas/{$this->ksm->id}/nota.pdf", '%PDF-1.4 bukti');
        $berbukti = TransaksiKas::factory()->for($this->ksm)->create(['tanggal' => '2026-11-16', 'bukti' => "bukti-kas/{$this->ksm->id}/nota.pdf"]);
        $tanpaBukti = TransaksiKas::factory()->for($this->ksm)->create();

        Livewire::test(ManageTransaksiKas::class)
            ->assertActionHidden(TestAction::make('unduhBukti')->table($tanpaBukti))
            ->callAction(TestAction::make('unduhBukti')->table($berbukti))
            ->assertFileDownloaded("bukti-kas-2026-11-16-{$berbukti->id}.pdf", '%PDF-1.4 bukti');
    }

    public function test_a_tampered_receipt_path_is_refused(): void
    {
        Storage::fake('local', ['serve' => true]);
        Storage::disk('local')->put('bukti-kas/999/rahasia.pdf', '%PDF-1.4 milik KSM lain');

        $this->catat(['bukti' => ['bukti-kas/999/rahasia.pdf']])->assertHasFormErrors(['bukti']);

        $this->assertSame(0, TransaksiKas::query()->count());
    }

    public function test_receipt_must_be_an_image_or_pdf(): void
    {
        Storage::fake('local', ['serve' => true]);

        $this->catat(['bukti' => UploadedFile::fake()->createWithContent('nota.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')])
            ->assertHasFormErrors(['bukti']);
        $this->catat(['bukti' => UploadedFile::fake()->createWithContent('nota.php', '<?php echo 1;')])
            ->assertHasFormErrors(['bukti']);

        $this->assertSame(0, TransaksiKas::query()->count());
    }

    public function test_category_names_of_automatic_income_are_refused_in_any_case(): void
    {
        foreach (['Iuran', ' PENJUALAN MATERIAL '] as $nama) {
            Livewire::test(ManageKategoriTransaksis::class)
                ->callAction('create', data: ['jenis' => JenisTransaksi::Masuk->value, 'nama' => $nama, 'aktif' => true])
                ->assertHasFormErrors(['nama' => '"Iuran" dan "Penjualan material" sudah dihitung otomatis — pakai nama lain.']);
        }

        $this->assertSame(['BBM'], KategoriTransaksi::query()->pluck('nama')->all());
    }

    public function test_category_name_is_unique_per_kind_only(): void
    {
        Livewire::test(ManageKategoriTransaksis::class)
            ->callAction('create', data: ['jenis' => JenisTransaksi::Masuk->value, 'nama' => 'BBM', 'aktif' => true])
            ->assertHasNoFormErrors()
            ->callAction('create', data: ['jenis' => JenisTransaksi::Keluar->value, 'nama' => 'BBM', 'aktif' => true])
            ->assertHasFormErrors(['nama' => 'Kategori dengan nama ini sudah ada untuk jenis yang sama.']);

        $this->assertSame(2, KategoriTransaksi::query()->count());
    }

    public function test_used_category_keeps_its_kind_and_cannot_be_deleted(): void
    {
        $kosong = KategoriTransaksi::factory()->for($this->ksm)->create(['nama' => 'Peralatan']);
        TransaksiKas::factory()->for($this->ksm)->create(['kategori_transaksi_id' => $this->bbm->id]);

        Livewire::test(ManageKategoriTransaksis::class)
            ->callAction(TestAction::make('edit')->table($this->bbm), data: ['jenis' => JenisTransaksi::Masuk->value, 'nama' => 'BBM & oli'])
            ->assertHasNoFormErrors()
            ->assertActionHidden(TestAction::make('delete')->table($this->bbm))
            ->callAction(TestAction::make('delete')->table($kosong));

        $this->bbm->refresh();
        $this->assertSame([JenisTransaksi::Keluar, 'BBM & oli'], [$this->bbm->jenis, $this->bbm->nama]);
        $this->assertFalse($this->admin->can('delete', $this->bbm));
        $this->assertModelMissing($kosong);
    }

    public function test_profile_sets_the_cash_book_start_but_not_in_the_future(): void
    {
        Livewire::test(EditProfilKsm::class)
            ->fillForm(['saldo_awal' => 1_250_000, 'kas_mulai' => '2026-11-18'])
            ->call('save')
            ->assertHasFormErrors(['kas_mulai']);

        Livewire::test(EditProfilKsm::class)
            ->fillForm(['saldo_awal' => 1_250_000, 'kas_mulai' => '2026-11-01'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->ksm->refresh();
        $this->assertSame([1_250_000, '2026-11-01'], [$this->ksm->saldo_awal, $this->ksm->kas_mulai->toDateString()]);
    }

    public function test_dashboard_counts_this_months_entries_without_receipt(): void
    {
        TransaksiKas::factory()->for($this->ksm)->create(['tanggal' => '2026-11-03']);
        TransaksiKas::factory()->for($this->ksm)->create(['tanggal' => '2026-11-04', 'bukti' => 'bukti-kas/ada.jpg']);
        TransaksiKas::factory()->for($this->ksm)->create(['tanggal' => '2026-10-30']);

        $this->assertSame('1', $this->nilaiStat(Livewire::test(KelengkapanDataStats::class))['Transaksi tanpa bukti']);
    }

    public function test_petugas_and_warga_are_forbidden_from_the_cash_book_and_its_master_lists(): void
    {
        $pengguna = [User::factory()->for($this->ksm)->petugas()->create(), Pelanggan::factory()->for($this->ksm)->create()->akunWarga];

        foreach ($pengguna as $satu) {
            $this->flushSession();
            $this->actingAs($satu);

            foreach (['transaksi-kas', 'kategori-transaksis', 'mitras'] as $menu) {
                $this->get("http://makmurjaya.ksmdesa.test/{$menu}")->assertForbidden();
            }
        }
    }

    public function test_deleting_a_ksm_removes_its_cash_book_and_its_receipt_files_only(): void
    {
        Storage::fake('local');
        $lain = $this->diKsmLain(fn () => Ksm::factory()->create());
        Storage::disk('local')->put("bukti-kas/{$this->ksm->id}/nota.jpg", 'foto');
        Storage::disk('local')->put("bukti-kas/{$lain->id}/nota.jpg", 'foto KSM lain');
        $mitra = Mitra::factory()->for($this->ksm)->create();
        $transaksi = TransaksiKas::factory()->for($this->ksm)->masuk()->create([
            'kategori_transaksi_id' => KategoriTransaksi::factory()->for($this->ksm)->masuk()->create()->id,
            'mitra_id' => $mitra->id,
            'bukti' => "bukti-kas/{$this->ksm->id}/nota.jpg",
        ]);

        $this->ksm->delete();

        $this->assertModelMissing($transaksi);
        $this->assertModelMissing($mitra);
        $this->assertModelMissing($this->bbm);
        Storage::disk('local')->assertMissing("bukti-kas/{$this->ksm->id}/nota.jpg");
        Storage::disk('local')->assertExists("bukti-kas/{$lain->id}/nota.jpg");
    }
}
