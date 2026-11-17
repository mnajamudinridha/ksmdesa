<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Ksm\Resources\Users\Pages\ManageUsers;
use App\Models\Ksm;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The village admin manages the KSM's own users; petugas and warga reach none of the
 * admin screens.
 */
class PenggunaDesaTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->actingAs($this->admin);

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm);
        Filament::bootCurrentPanel();
    }

    /** Created outside the tenant context: Filament's creating observer would move it into $this->ksm. */
    private function diKsmLain(callable $buat): mixed
    {
        Filament::setTenant(null, isQuiet: true);

        try {
            return $buat();
        } finally {
            Filament::setTenant($this->ksm, isQuiet: true);
        }
    }

    /** @return array<string, mixed> */
    private function data(array $ubah = []): array
    {
        return [
            'name' => 'Petugas Satu',
            'no_hp' => '+62 812 1111 2222',
            'password' => 'rahasia123',
            'peran' => PeranPengguna::Petugas->value,
            'aktif' => true,
            ...$ubah,
        ];
    }

    public function test_admin_adds_a_petugas_to_this_ksm_with_a_normalised_number(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction('create', data: $this->data())
            ->assertHasNoFormErrors();

        $petugas = User::query()->firstWhere('name', 'Petugas Satu');
        $this->assertSame('081211112222', $petugas->no_hp);
        $this->assertTrue($petugas->ksm->is($this->ksm));
        $this->assertSame(PeranPengguna::Petugas, $petugas->peran);
        $this->assertFalse($petugas->is_super_admin);
    }

    public function test_number_is_unique_within_the_ksm_whatever_the_format(): void
    {
        User::factory()->for($this->ksm)->warga()->create(['no_hp' => '081211112222']);

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: $this->data(['no_hp' => '62-812-1111-2222']))
            ->assertHasFormErrors(['no_hp']);

        $this->assertSame(1, User::query()->where('no_hp', '081211112222')->count());
    }

    public function test_a_number_used_in_another_ksm_is_allowed_here(): void
    {
        $this->diKsmLain(fn () => User::factory()->for(Ksm::factory())->warga()->create(['no_hp' => '081233334444']));

        Livewire::test(ManageUsers::class)
            ->callAction('create', data: $this->data(['no_hp' => '081233334444']))
            ->assertHasNoFormErrors();
    }

    public function test_invalid_number_and_missing_password_are_rejected(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction('create', data: $this->data(['no_hp' => '12345', 'password' => null]))
            ->assertHasFormErrors(['no_hp', 'password' => 'required']);
    }

    public function test_blank_password_on_edit_keeps_the_old_one(): void
    {
        $petugas = User::factory()->for($this->ksm)->petugas()->create(['password' => 'lama12345']);
        $hashLama = $petugas->getAuthPassword();

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($petugas), data: ['name' => 'Nama Baru', 'password' => ''])
            ->assertHasNoFormErrors();

        $petugas->refresh();
        $this->assertSame('Nama Baru', $petugas->name);
        $this->assertSame($hashLama, $petugas->getAuthPassword());
    }

    public function test_admin_cannot_demote_or_deactivate_themselves(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($this->admin), data: ['peran' => PeranPengguna::Warga->value, 'aktif' => false])
            ->assertHasNoFormErrors()
            ->assertActionHidden(TestAction::make('alihAktif')->table($this->admin));

        $this->admin->refresh();
        $this->assertSame(PeranPengguna::Admin, $this->admin->peran);
        $this->assertTrue($this->admin->aktif);
    }

    public function test_deactivating_another_user_keeps_them_but_blocks_login(): void
    {
        $petugas = User::factory()->for($this->ksm)->petugas()->create();

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('alihAktif')->table($petugas));

        $this->assertFalse($petugas->refresh()->aktif);
        $this->assertFalse($petugas->canAccessPanel(Filament::getPanel('ksm')));
        $this->assertDatabaseHas('users', ['id' => $petugas->id]);
    }

    public function test_list_shows_only_this_ksms_users(): void
    {
        $petugas = User::factory()->for($this->ksm)->petugas()->create();
        $lain = $this->diKsmLain(fn () => User::factory()->for(Ksm::factory())->admin()->create());
        $master = $this->diKsmLain(fn () => User::factory()->superAdmin()->create());

        Livewire::test(ManageUsers::class)
            ->assertCanSeeTableRecords([$this->admin, $petugas])
            ->assertCanNotSeeTableRecords([$lain, $master]);
    }

    public function test_is_super_admin_cannot_be_mass_assigned(): void
    {
        $user = new User(['name' => 'X', 'is_super_admin' => true, 'ksm_id' => 999]);

        $this->assertFalse($user->is_super_admin);
        $this->assertNull($user->ksm_id);
    }
}
