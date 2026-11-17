<?php

namespace Tests\Feature;

use App\Filament\Pages\TampilanMaster;
use App\Models\PengaturanMaster;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The master panel has its own logo and login backgrounds, set on its "Tampilan" page.
 */
class TampilanMasterTest extends TestCase
{
    use RefreshDatabase;

    private const GAYA_LATAR = '.fi-simple-layout{background:';

    public function test_master_sets_its_logo_and_backgrounds_and_replaced_files_are_removed(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->superAdmin()->create());
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();

        Livewire::test(TampilanMaster::class)
            ->fillForm([
                'logo' => UploadedFile::fake()->image('logo.png', 500, 500),
                'latar_login' => [UploadedFile::fake()->image('a.jpg', 1600, 900)],
            ])
            ->call('simpan')
            ->assertHasNoFormErrors();

        $pengaturan = PengaturanMaster::sekarang();
        $this->assertStringStartsWith('logo-master/', $pengaturan->logo);
        $lama = $pengaturan->latar_login;
        Storage::disk('public')->assertExists([$pengaturan->logo, ...$lama]);

        Livewire::test(TampilanMaster::class)
            ->fillForm(['latar_login' => []])
            ->fillForm(['latar_login' => [UploadedFile::fake()->image('b.jpg', 1600, 900)]])
            ->call('simpan')
            ->assertHasNoFormErrors();

        Storage::disk('public')->assertMissing($lama);
        $this->assertSame(1, PengaturanMaster::query()->count());
    }

    public function test_master_login_shows_its_background_and_larger_logo(): void
    {
        $this->get('http://ksmdesa.test/admin/login')
            ->assertOk()
            ->assertDontSee(self::GAYA_LATAR, false)
            ->assertSee('<link rel="icon" href="http://ksmdesa.test/favicon.svg"', false);
        $this->assertSame(0, PengaturanMaster::query()->count(), 'reading the look never creates the row');

        PengaturanMaster::query()->create(['logo' => 'logo-master/logo.png', 'latar_login' => ['latar-login/balai.jpg']]);

        $this->get('http://ksmdesa.test/admin/login')
            ->assertOk()
            ->assertSee(self::GAYA_LATAR, false)
            ->assertSee('/storage/latar-login/balai.jpg', false)
            ->assertSee('height: 6rem', false)
            ->assertSee('<link rel="icon" href="http://ksmdesa.test/storage/logo-master/logo.png"', false);
    }

    public function test_inside_the_master_panel_the_logo_carries_the_panel_name(): void
    {
        PengaturanMaster::query()->create(['logo' => 'logo-master/logo.png', 'latar_login' => ['latar-login/balai.jpg']]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('http://ksmdesa.test/admin')
            ->assertOk()
            ->assertSee('class="ksm-brand"', false)
            ->assertSee('KSM Desa — Master')
            ->assertDontSee(self::GAYA_LATAR, false);
    }
}
