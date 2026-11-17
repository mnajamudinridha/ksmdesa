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

    /** @return array<string, array{string, int, int}> path, status for petugas, status for warga */
    public static function halamanAdmin(): array
    {
        return [
            'pengguna' => ['/users', 403, 403],
            // Phase 2: a petugas sees the customers assigned to them (read-only).
            'pelanggan' => ['/pelanggans', 200, 403],
            'pembayaran' => ['/pembayaran-iurans', 403, 403],
            'tarif' => ['/tarif-layanans', 403, 403],
            'rekap' => ['/rekap-iuran', 403, 403],
            // Filament denies the tenant profile page with 404 rather than 403.
            'profil ksm' => ['/profil-ksm', 404, 404],
        ];
    }

    #[DataProvider('halamanAdmin')]
    public function test_petugas_and_warga_only_reach_what_their_role_allows(string $path, int $petugas, int $warga): void
    {
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);

        foreach (['petugas' => $petugas, 'warga' => $warga] as $peran => $status) {
            $this->actingAs(User::factory()->for($ksm)->{$peran}()->create())
                ->get("http://makmurjaya.ksmdesa.test{$path}")
                ->assertStatus($status);
        }
    }

    #[DataProvider('halamanAdmin')]
    public function test_village_admin_is_allowed(string $path, int $petugas, int $warga): void
    {
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);

        $this->actingAs(User::factory()->for($ksm)->admin()->create())
            ->get("http://makmurjaya.ksmdesa.test{$path}")
            ->assertOk();
    }

    /**
     * Regression (500 in production, 2026-11-17): "Profil saya" is not a tenant page; with the
     * full layout the KSM switcher — shown only to master administrators — needed a tenant.
     */
    public function test_master_administrator_can_open_own_profile_on_a_ksm(): void
    {
        Ksm::factory()->create(['slug' => 'makmurjaya']);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('http://makmurjaya.ksmdesa.test/profil-saya')->assertOk();
    }

    public function test_master_administrator_can_open_own_profile_on_master(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('http://ksmdesa.test/admin/profil-saya')
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
