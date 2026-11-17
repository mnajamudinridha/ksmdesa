<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Resources\Ksms\Pages\EditKsm;
use App\Filament\Resources\Ksms\RelationManagers\UsersRelationManager;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Ksm;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Master: administrators add other administrators, and manage any KSM's users.
 */
class AdministratorMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();

        $this->master = User::factory()->superAdmin()->create(['email' => 'utama@contoh.id']);
        $this->actingAs($this->master);
        Filament::setCurrentPanel('admin');
    }

    public function test_administrator_adds_another_administrator(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction('create', data: ['name' => 'Admin Dua', 'email' => 'Dua@Contoh.ID', 'password' => 'rahasia123', 'aktif' => true])
            ->assertHasNoFormErrors();

        $baru = User::query()->firstWhere('name', 'Admin Dua');
        $this->assertTrue($baru->is_super_admin);
        $this->assertNull($baru->ksm_id);
        $this->assertSame('dua@contoh.id', $baru->email);
        $this->assertTrue($baru->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_administrator_email_is_unique_regardless_of_case(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction('create', data: ['name' => 'Kembar', 'email' => 'UTAMA@contoh.id', 'password' => 'rahasia123'])
            ->assertHasFormErrors(['email']);
    }

    public function test_list_shows_administrators_only(): void
    {
        $desa = User::factory()->for(Ksm::factory())->admin()->create();

        Livewire::test(ManageUsers::class)
            ->assertCanSeeTableRecords([$this->master])
            ->assertCanNotSeeTableRecords([$desa]);
    }

    public function test_administrator_cannot_deactivate_themselves(): void
    {
        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($this->master), data: ['aktif' => false])
            ->assertHasNoFormErrors()
            ->assertActionHidden(TestAction::make('alihAktif')->table($this->master));

        $this->assertTrue($this->master->refresh()->aktif);
    }

    public function test_master_adds_a_user_to_a_ksm(): void
    {
        $ksm = Ksm::factory()->create();
        $warga = User::factory()->for($ksm)->warga()->create();

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $ksm, 'pageClass' => EditKsm::class])
            // Warga accounts belong to customers (KSM panel → Pelanggan), not to this list.
            ->assertCanNotSeeTableRecords([$warga])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'Warga',
                'no_hp' => '0812 5555 7777',
                'password' => 'rahasia123',
                'peran' => PeranPengguna::Warga->value,
                'aktif' => true,
            ])
            ->assertHasFormErrors(['peran' => 'in']);

        Livewire::test(UsersRelationManager::class, ['ownerRecord' => $ksm, 'pageClass' => EditKsm::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'Petugas Desa',
                'no_hp' => '0812 5555 6666',
                'password' => 'rahasia123',
                'peran' => PeranPengguna::Petugas->value,
                'aktif' => true,
            ])
            ->assertHasNoFormErrors();

        $petugas = User::query()->firstWhere('name', 'Petugas Desa');
        $this->assertTrue($petugas->ksm->is($ksm));
        $this->assertSame('081255556666', $petugas->no_hp);
        $this->assertFalse($petugas->is_super_admin);
    }

    public function test_inactive_administrator_loses_master_access(): void
    {
        $this->assertFalse(User::factory()->superAdmin()->nonaktif()->create()->canAccessPanel(Filament::getPanel('admin')));
    }
}
