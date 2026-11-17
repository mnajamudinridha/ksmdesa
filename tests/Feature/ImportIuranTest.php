<?php

namespace Tests\Feature;

use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportIuranTest extends TestCase
{
    use RefreshDatabase;

    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();

        Ksm::factory()->create(['slug' => 'contoh']);

        // Made-up names: real ledgers hold residents' data and never belong in the repo.
        $this->csv = tempnam(sys_get_temp_dir(), 'iuran');
        file_put_contents($this->csv, implode("\n", [
            'nomor_urut,nama,tarif,iuran_bulanan,aktif,catatan,2026-04,2026-05',
            '1,Warga Satu,Warga biasa,25000,1,,25000,25000',
            '2,Warung Dua,Catering kecil,50000,1,,50000,',
            '3,Warga Tiga,Warga biasa,25000,0,berhenti,,',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function args(array $extra = []): array
    {
        return [
            'ksm' => 'contoh',
            'file' => $this->csv,
            '--tarif' => ['Warga biasa=25000', 'Catering kecil=50000'],
            ...$extra,
        ];
    }

    public function test_dry_run_checks_book_totals_and_writes_nothing(): void
    {
        $this->artisan('app:import-iuran', $this->args(['--dry-run' => true, '--cek' => ['2026-04=75000', '2026-05=25000']]))
            ->expectsOutputToContain('2026-04 cocok dengan buku')
            ->assertSuccessful();

        $this->artisan('app:import-iuran', $this->args(['--dry-run' => true, '--cek' => ['2026-05=50000']]))
            ->expectsOutputToContain('2026-05 BEDA dengan buku')
            ->assertFailed();

        $this->assertDatabaseCount('pelanggan', 0);
    }

    public function test_import_is_safe_to_run_twice(): void
    {
        $this->artisan('app:import-iuran', $this->args())->assertSuccessful();
        $this->artisan('app:import-iuran', $this->args())->assertSuccessful();

        $this->assertSame(3, Pelanggan::count());
        $this->assertSame(3, PembayaranIuran::count());
        $this->assertFalse(Pelanggan::firstWhere('nama', 'Warga Tiga')->aktif);
        $this->assertSame(75_000, (int) PembayaranIuran::query()->whereDate('periode', '2026-04-01')->sum('nominal'));
    }

    public function test_unknown_tariff_aborts_before_writing(): void
    {
        $this->artisan('app:import-iuran', ['ksm' => 'contoh', 'file' => $this->csv])
            ->expectsOutputToContain('tarif "Warga biasa" belum ada')
            ->assertFailed();

        $this->assertDatabaseCount('pelanggan', 0);
    }
}
