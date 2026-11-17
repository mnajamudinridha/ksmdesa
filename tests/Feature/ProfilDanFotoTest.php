<?php

namespace Tests\Feature;

use App\Filament\Ksm\Pages\Tenancy\EditProfilKsm;
use App\Filament\Shared\ProfilSaya;
use App\Models\Ksm;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * KSM logo (Profil KSM), account photos (Profil saya), and what village users see.
 */
class ProfilDanFotoTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local', ['serve' => true]);

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
    }

    public function test_village_admin_updates_name_and_logo_and_the_old_logo_is_removed(): void
    {
        $this->actingAs(User::factory()->for($this->ksm)->admin()->create());

        Livewire::test(EditProfilKsm::class)
            ->fillForm(['nama' => 'KSM Baru', 'logo' => UploadedFile::fake()->image('logo1.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        $logoPertama = $this->ksm->refresh()->logo;
        $this->assertSame('KSM Baru', $this->ksm->nama);
        Storage::disk('public')->assertExists($logoPertama);

        // As in the UI (FilePond, one file): remove the current logo, then upload the new one.
        Livewire::test(EditProfilKsm::class)
            ->fillForm(['logo' => []])
            ->fillForm(['logo' => UploadedFile::fake()->image('logo2.png')])
            ->call('save')
            ->assertHasNoFormErrors();

        Storage::disk('public')->assertMissing($logoPertama);
        Storage::disk('public')->assertExists($this->ksm->refresh()->logo);
    }

    public function test_logo_must_be_an_image(): void
    {
        $this->actingAs(User::factory()->for($this->ksm)->admin()->create());

        Livewire::test(EditProfilKsm::class)
            ->fillForm(['logo' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php')])
            ->call('save')
            ->assertHasFormErrors(['logo']);
    }

    /** Owner's decision 2026-11-17: master administrators no longer get it either. */
    public function test_tenant_switcher_is_hidden_from_everyone(): void
    {
        $this->actingAs(User::factory()->for($this->ksm)->admin()->create());
        $this->assertFalse(Filament::getPanel('ksm')->hasTenantMenu());

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->assertFalse(Filament::getPanel('ksm')->hasTenantMenu());
    }

    public function test_user_sets_own_photo_but_not_their_name(): void
    {
        $warga = User::factory()->for($this->ksm)->warga()->create(['name' => 'Nama Asli']);
        $this->actingAs($warga);

        Livewire::test(ProfilSaya::class)
            ->fillForm(['avatar' => UploadedFile::fake()->image('wajah.jpg'), 'name' => 'Nama Palsu'])
            ->call('save')
            ->assertHasNoFormErrors();

        $warga->refresh();
        $this->assertSame('Nama Asli', $warga->name);
        Storage::disk('local')->assertExists($warga->avatar);
        $this->assertStringStartsWith('avatar/', $warga->avatar);
    }

    public function test_changing_password_requires_the_current_one(): void
    {
        $warga = User::factory()->for($this->ksm)->warga()->create(['password' => 'lama12345']);
        $this->actingAs($warga);

        Livewire::test(ProfilSaya::class)
            ->fillForm(['password' => 'baru12345', 'passwordConfirmation' => 'baru12345'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);

        Livewire::test(ProfilSaya::class)
            ->fillForm(['password' => 'baru12345', 'passwordConfirmation' => 'baru12345', 'currentPassword' => 'lama12345'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('baru12345', $warga->refresh()->getAuthPassword()));
    }

    public function test_photo_is_only_reachable_through_a_signed_link(): void
    {
        // Real disk on purpose: Storage::fake() replaces temporaryUrl() with an unsigned dummy.
        Storage::forgetDisk('local');
        $path = 'avatar/test-'.uniqid().'.jpg';
        Storage::disk('local')->put($path, 'foto-warga');

        try {
            $user = User::factory()->for($this->ksm)->petugas()->create(['avatar' => $path]);
            $url = $user->getFilamentAvatarUrl();

            $this->assertStringContainsString('signature=', $url);
            $this->get($url)->assertOk();
            $this->get(strtok($url, '?'))->assertStatus(403);
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public function test_initials_avatar_is_drawn_locally(): void
    {
        $tanpaFoto = User::factory()->for($this->ksm)->petugas()->create(['name' => 'Budi Santoso']);

        $inisial = Filament::getUserAvatarUrl($tanpaFoto);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $inisial);
        $this->assertStringContainsString('BS', base64_decode(substr($inisial, strlen('data:image/svg+xml;base64,'))));
        $this->assertStringNotContainsString('ui-avatars', $inisial);
    }

    public function test_replacing_a_photo_deletes_the_old_file(): void
    {
        Storage::disk('local')->put('avatar/lama.jpg', 'jpg');
        $user = User::factory()->for($this->ksm)->warga()->create(['avatar' => 'avatar/lama.jpg']);

        $user->update(['avatar' => 'avatar/baru.jpg']);

        Storage::disk('local')->assertMissing('avatar/lama.jpg');
    }
}
