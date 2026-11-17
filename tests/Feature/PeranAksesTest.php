<?php

namespace Tests\Feature;

use App\Models\Ksm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Phase 1 safeguard: petugas and warga accounts can sign in, but none of the admin
 * screens (users, customers, payments, tariffs, ledger, KSM profile) open for them.
 */
class PeranAksesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, int}> path, status for petugas/warga */
    public static function halamanAdmin(): array
    {
        return [
            'pengguna' => ['/users', 403],
            'pelanggan' => ['/pelanggans', 403],
            'pembayaran' => ['/pembayaran-iurans', 403],
            'tarif' => ['/tarif-layanans', 403],
            'rekap' => ['/rekap-iuran', 403],
            // Filament denies the tenant profile page with 404 rather than 403.
            'profil ksm' => ['/profil-ksm', 404],
        ];
    }

    #[DataProvider('halamanAdmin')]
    public function test_petugas_and_warga_are_refused(string $path, int $status): void
    {
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);

        foreach (['petugas', 'warga'] as $peran) {
            $this->actingAs(User::factory()->for($ksm)->{$peran}()->create())
                ->get("http://makmurjaya.ksmdesa.test{$path}")
                ->assertStatus($status);
        }
    }

    #[DataProvider('halamanAdmin')]
    public function test_village_admin_is_allowed(string $path, int $status): void
    {
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);

        $this->actingAs(User::factory()->for($ksm)->admin()->create())
            ->get("http://makmurjaya.ksmdesa.test{$path}")
            ->assertOk();
    }

    public function test_every_role_has_a_dashboard_and_own_profile(): void
    {
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya', 'nama' => 'KSM Makmur']);

        foreach (['admin', 'petugas', 'warga'] as $peran) {
            $user = User::factory()->for($ksm)->{$peran}()->create();

            $this->actingAs($user)->get('http://makmurjaya.ksmdesa.test/')->assertOk()->assertSee('KSM Makmur');
            $this->actingAs($user)->get('http://makmurjaya.ksmdesa.test/profil-saya')->assertOk();
        }
    }
}
