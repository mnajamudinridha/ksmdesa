<?php

namespace Tests\Feature;

use App\Models\Ksm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each KSM lives on its own subdomain (<slug>.ksmdesa.test in tests); the master
 * site keeps the apex. These pin down who can reach which host.
 */
class KsmTenancyTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $makmurjaya;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makmurjaya = Ksm::factory()->create([
            'nama' => 'KSM Anugerah Makmur Jaya',
            'slug' => 'makmurjaya',
            'desa' => 'Makmur Jaya',
        ]);
    }

    public function test_master_home_stays_public_on_the_apex(): void
    {
        $this->get('http://ksmdesa.test/')->assertOk();
    }

    public function test_guest_on_a_ksm_subdomain_is_sent_to_that_subdomains_login(): void
    {
        $this->get('http://makmurjaya.ksmdesa.test/')
            ->assertRedirect('http://makmurjaya.ksmdesa.test/login');

        $this->get('http://makmurjaya.ksmdesa.test/login')->assertOk();
    }

    public function test_ksm_login_does_not_answer_on_the_master_host(): void
    {
        $this->get('http://ksmdesa.test/login')->assertNotFound();
    }

    public function test_unregistered_subdomain_shows_no_login_page(): void
    {
        $this->get('http://tidakada.ksmdesa.test/')->assertNotFound();
        $this->get('http://tidakada.ksmdesa.test/login')->assertNotFound();
    }

    public function test_member_sees_their_ksm_dashboard(): void
    {
        $pengurus = User::factory()->for($this->makmurjaya)->admin()->create();

        $this->actingAs($pengurus)
            ->get('http://makmurjaya.ksmdesa.test/')
            ->assertOk()
            ->assertSee('KSM Anugerah Makmur Jaya');
    }

    public function test_member_of_one_ksm_cannot_open_another(): void
    {
        $lain = Ksm::factory()->create(['slug' => 'beruntungjaya']);
        $pengurus = User::factory()->for($lain)->admin()->create();

        $this->actingAs($pengurus)
            ->get('http://makmurjaya.ksmdesa.test/')
            ->assertNotFound();
    }

    public function test_unknown_subdomain_is_not_found(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('http://tidakada.ksmdesa.test/')
            ->assertNotFound();
    }

    public function test_super_admin_can_open_any_ksm(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('http://makmurjaya.ksmdesa.test/')
            ->assertOk();
    }

    // One panel per test: Filament keeps panel state in the app instance, which a real
    // request never shares with another (each request is a fresh PHP process).
    public function test_super_admin_can_open_the_master_panel(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('http://ksmdesa.test/admin')
            ->assertOk();
    }

    public function test_inactive_user_is_refused_even_with_a_session(): void
    {
        $this->actingAs(User::factory()->for($this->makmurjaya)->admin()->nonaktif()->create())
            ->get('http://makmurjaya.ksmdesa.test/')
            ->assertForbidden();
    }

    public function test_ksm_member_cannot_use_the_master_panel(): void
    {
        $pengurus = User::factory()->for($this->makmurjaya)->admin()->create();

        $this->actingAs($pengurus)->get('http://ksmdesa.test/admin')->assertForbidden();
    }

    public function test_user_without_any_ksm_is_refused_by_the_ksm_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('http://makmurjaya.ksmdesa.test/')
            ->assertForbidden();
    }
}
