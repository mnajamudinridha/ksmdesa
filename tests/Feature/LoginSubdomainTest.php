<?php

namespace Tests\Feature;

use App\Filament\Ksm\Pages\Auth\Login;
use App\Models\Ksm;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Village users sign in on their own subdomain with a phone number; the same number in
 * another KSM is a different account. Email is for master administrators only.
 */
class LoginSubdomainTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $makmurjaya;

    private Ksm $lain;

    protected function setUp(): void
    {
        parent::setUp();

        // Created first, so "the first KSM alphabetically" is NOT makmurjaya.
        Ksm::factory()->create(['slug' => 'aaawal', 'nama' => 'AAA Awal']);
        $this->makmurjaya = Ksm::factory()->create(['slug' => 'makmurjaya', 'nama' => 'KSM Anugerah Makmur Jaya']);
        $this->lain = Ksm::factory()->create(['slug' => 'beruntungjaya', 'nama' => 'KSM Beruntung Jaya']);

        Filament::setCurrentPanel('ksm');
    }

    /**
     * Simulate the browser on <slug>.ksmdesa.test. Livewire's test broker replaces its server
     * variables on follow-up requests (SubsequentRender adds X-Livewire), dropping a Host header,
     * so the root URL is pointed at the subdomain instead — as a real browser posting to
     * /livewire/update on the same host would be.
     */
    private function masuk(string $slug, string $login, string $password): Testable
    {
        URL::forceRootUrl("http://{$slug}.ksmdesa.test");

        return Livewire::test(Login::class)
            ->fillForm(['login' => $login, 'password' => $password])
            ->call('authenticate');
    }

    public function test_petugas_signs_in_with_any_common_phone_format(): void
    {
        $petugas = User::factory()->for($this->makmurjaya)->petugas()
            ->create(['no_hp' => '081234567890', 'password' => 'rahasia123']);

        $this->masuk('makmurjaya', '+62 812-3456-7890', 'rahasia123')->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($petugas);
    }

    public function test_the_same_number_in_another_ksm_is_a_different_account(): void
    {
        $diSini = User::factory()->for($this->makmurjaya)->warga()->create(['no_hp' => '081299998888', 'password' => 'sandi-makmur']);
        User::factory()->for($this->lain)->warga()->create(['no_hp' => '081299998888', 'password' => 'sandi-lain']);

        $this->masuk('makmurjaya', '081299998888', 'sandi-lain')->assertHasFormErrors(['login']);
        $this->assertGuest();

        $this->masuk('makmurjaya', '081299998888', 'sandi-makmur')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($diSini);
    }

    public function test_user_cannot_sign_in_on_another_ksms_subdomain(): void
    {
        User::factory()->for($this->lain)->admin()->create(['no_hp' => '081277776666', 'password' => 'rahasia123']);

        $this->masuk('makmurjaya', '081277776666', 'rahasia123')->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_sign_in(): void
    {
        User::factory()->for($this->makmurjaya)->petugas()->nonaktif()
            ->create(['no_hp' => '081266665555', 'password' => 'rahasia123']);

        $this->masuk('makmurjaya', '081266665555', 'rahasia123')->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_master_administrator_signs_in_with_email_and_stays_on_this_ksm(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'admin@contoh.id', 'password' => 'rahasia123']);

        $this->masuk('makmurjaya', 'ADMIN@contoh.id', 'rahasia123')
            ->assertHasNoFormErrors()
            ->assertRedirectContains('makmurjaya.ksmdesa.test');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_village_user_cannot_sign_in_with_email(): void
    {
        User::factory()->for($this->makmurjaya)->admin()
            ->create(['email' => 'pengurus@contoh.id', 'password' => 'rahasia123']);

        $this->masuk('makmurjaya', 'pengurus@contoh.id', 'rahasia123')->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_text_that_is_not_a_number_never_matches_an_account_without_phone(): void
    {
        // e.g. an admin migrated from the old pivot, no phone number yet.
        User::factory()->for($this->makmurjaya)->create(['peran' => 'admin', 'no_hp' => null, 'password' => 'rahasia123']);

        $this->masuk('makmurjaya', 'abc', 'rahasia123')->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_login_page_shows_this_ksms_name_and_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logo-ksm/makmur.png', 'png');
        $this->makmurjaya->update(['logo' => 'logo-ksm/makmur.png']);

        $this->get('http://makmurjaya.ksmdesa.test/login')
            ->assertOk()
            ->assertSee('KSM Anugerah Makmur Jaya')
            ->assertSee('logo-ksm/makmur.png');
    }

    public function test_login_page_without_logo_shows_the_ksm_name(): void
    {
        $this->get('http://beruntungjaya.ksmdesa.test/login')
            ->assertOk()
            ->assertSee('KSM Beruntung Jaya')
            ->assertDontSee('logo-ksm/');
    }
}
