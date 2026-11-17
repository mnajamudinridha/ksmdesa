<?php

namespace Tests\Feature;

use App\Filament\Ksm\Pages\Tenancy\EditProfilKsm;
use App\Filament\Resources\Ksms\Pages\EditKsm;
use App\Models\Ksm;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The KSM's look: logo with its name inside the app, a larger logo and an optional
 * background photo on the login page, both editable by the village admin and by master.
 */
class TampilanKsmTest extends TestCase
{
    use RefreshDatabase;

    private const GAYA_LATAR = '.fi-simple-layout{background:';

    public function test_login_page_shows_the_background_and_a_larger_logo(): void
    {
        Ksm::factory()->create(['slug' => 'makmurjaya', 'logo' => 'logo-ksm/logo.png', 'latar_login' => ['latar-login/sawah.jpg']]);
        Ksm::factory()->create(['slug' => 'polos', 'logo' => 'logo-ksm/polos.png']);

        $this->get('http://makmurjaya.ksmdesa.test/login')
            ->assertOk()
            ->assertSee(self::GAYA_LATAR, false)
            ->assertSee('/storage/latar-login/sawah.jpg', false)
            ->assertSee('height: 6rem', false);

        $this->get('http://polos.ksmdesa.test/login')
            ->assertOk()
            ->assertDontSee(self::GAYA_LATAR, false);
    }

    public function test_only_paths_the_upload_produces_reach_the_css(): void
    {
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya', 'latar_login' => ['latar-login/x.jpg");}body{display:none', '../.env']]);

        $this->assertNull($ksm->latarLoginUrl());
        $this->get('http://makmurjaya.ksmdesa.test/login')->assertOk()->assertDontSee(self::GAYA_LATAR, false);
    }

    public function test_inside_the_app_the_logo_carries_the_ksm_name_and_replaces_initials(): void
    {
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya', 'nama' => 'KSM Anugerah Makmur Jaya', 'logo' => 'logo-ksm/logo.png', 'latar_login' => ['latar-login/sawah.jpg']]);
        $admin = User::factory()->for($ksm)->admin()->create();

        $this->actingAs($admin)->get('http://makmurjaya.ksmdesa.test/')
            ->assertOk()
            ->assertSee('class="ksm-brand"', false)
            ->assertSee('height: 2.75rem', false)
            ->assertDontSee(self::GAYA_LATAR, false);

        $this->assertSame($ksm->logoUrl(), $ksm->getFilamentAvatarUrl());
    }

    public function test_village_admin_sets_backgrounds_and_replaced_files_are_removed(): void
    {
        Storage::fake('public');
        $ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->actingAs(User::factory()->for($ksm)->admin()->create());
        Filament::setCurrentPanel('ksm');
        Filament::setTenant($ksm, isQuiet: true);
        Filament::bootCurrentPanel();

        Livewire::test(EditProfilKsm::class)
            ->fillForm(['latar_login' => [UploadedFile::fake()->image('sawah.jpg', 1600, 900), UploadedFile::fake()->image('balai.jpg', 1600, 900)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $lama = $ksm->refresh()->latar_login;
        $this->assertCount(2, $lama);
        Storage::disk('public')->assertExists($lama);

        // FileUpload's fillForm adds to the list, so clear it before replacing.
        Livewire::test(EditProfilKsm::class)
            ->fillForm(['latar_login' => []])
            ->fillForm(['latar_login' => [UploadedFile::fake()->image('baru.jpg', 1600, 900)]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(1, $ksm->refresh()->latar_login);
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($ksm->latar_login);
    }

    public function test_master_edits_logo_and_backgrounds_too(): void
    {
        $ksm = Ksm::factory()->create();
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(EditKsm::class, ['record' => $ksm->getRouteKey()])
            ->assertFormFieldExists('logo')
            ->assertFormFieldExists('latar_login');
    }
}
