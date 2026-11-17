<?php

namespace Tests\Feature;

use App\Enums\JenisTransaksi;
use App\Enums\KelompokMaterial;
use App\Models\Ksm;
use App\Support\DataMasterBawaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Starter Data Master (material kinds, cash-book categories) per KSM: added once, only what
 * is missing, never touching the admin's own items, and never a double-counted income source.
 */
class DataMasterBawaanTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_a_ksm_once_and_is_safe_to_run_again(): void
    {
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $jumlahKategori = count(DataMasterBawaan::KATEGORI_TRANSAKSI['masuk']) + count(DataMasterBawaan::KATEGORI_TRANSAKSI['keluar']);

        $this->artisan('app:data-master-bawaan', ['ksm' => ['makmurjaya']])
            ->expectsOutputToContain('20 jenis material, '.$jumlahKategori.' kategori transaksi ditambahkan')
            ->assertSuccessful();
        $this->artisan('app:data-master-bawaan', ['ksm' => ['makmurjaya']])
            ->expectsOutputToContain('0 jenis material, 0 kategori transaksi ditambahkan')
            ->assertSuccessful();

        $this->assertSame(20, $ksm->jenisMaterial()->count());
        $this->assertSame($jumlahKategori, $ksm->kategoriTransaksi()->count());
        $this->assertSame(KelompokMaterial::Kertas, $ksm->jenisMaterial()->firstWhere('nama', 'Kardus / karton')->kelompok);
        $this->assertSame(JenisTransaksi::Keluar, $ksm->kategoriTransaksi()->firstWhere('nama', 'Honor petugas')->jenis);
    }

    public function test_the_admins_own_items_are_left_untouched(): void
    {
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $milikAdmin = $ksm->jenisMaterial()->create(['nama' => 'Botol PET bening', 'kelompok' => KelompokMaterial::Lainnya, 'aktif' => false]);

        DataMasterBawaan::terapkan($ksm);

        $milikAdmin->refresh();
        $this->assertSame(KelompokMaterial::Lainnya, $milikAdmin->kelompok);
        $this->assertFalse($milikAdmin->aktif);
        $this->assertSame(1, $ksm->jenisMaterial()->where('nama', 'Botol PET bening')->count());
    }

    public function test_fees_and_material_sales_get_no_income_category(): void
    {
        // Both are counted as income automatically (RingkasanKas); a category would invite double entries.
        $masuk = implode(' | ', DataMasterBawaan::KATEGORI_TRANSAKSI['masuk']);

        $this->assertStringNotContainsStringIgnoringCase('iuran', $masuk);
        $this->assertStringNotContainsStringIgnoringCase('penjualan', $masuk);
    }

    public function test_only_the_named_ksm_is_filled_and_empty_means_every_ksm(): void
    {
        $makmur = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $lain = Ksm::factory()->create(['slug' => 'lainjaya']);

        $this->artisan('app:data-master-bawaan', ['ksm' => ['makmurjaya']])->assertSuccessful();
        $this->assertSame(0, $lain->jenisMaterial()->count());

        $this->artisan('app:data-master-bawaan')->assertSuccessful();
        $this->assertSame(20, $lain->jenisMaterial()->count());
        $this->assertSame(20, $makmur->jenisMaterial()->count());

        $this->artisan('app:data-master-bawaan', ['ksm' => ['tidakada']])->assertFailed();
    }
}
