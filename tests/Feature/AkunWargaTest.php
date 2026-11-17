<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Filament\Ksm\Pages\Auth\Login;
use App\Filament\Ksm\Pages\Tenancy\EditProfilKsm;
use App\Filament\Ksm\Resources\Pelanggans\Pages\ManagePelanggans;
use App\Filament\Ksm\Resources\Users\Pages\ManageUsers;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every customer automatically has a warga account; the phone number on it is the login;
 * new accounts use the village's default password and follow it until changed.
 */
class AkunWargaTest extends TestCase
{
    use RefreshDatabase;

    private Ksm $ksm;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ksm = Ksm::factory()->create(['slug' => 'makmurjaya']);
        $this->ksm->update(['sandi_default_hash' => Hash::make('desa12345')]);
        $this->admin = User::factory()->for($this->ksm)->admin()->create();
        $this->actingAs($this->admin);

        Filament::setCurrentPanel('ksm');
        Filament::setTenant($this->ksm, isQuiet: true);
        Filament::bootCurrentPanel();
    }

    private function buatPelanggan(array $data): Pelanggan
    {
        Livewire::test(ManagePelanggans::class)
            ->callAction('create', data: ['iuran_bulanan' => 25_000, 'aktif' => true, ...$data])
            ->assertHasNoFormErrors();

        return Pelanggan::query()->where('nama', $data['nama'])->sole();
    }

    public function test_customer_without_number_gets_an_account_that_cannot_log_in_yet(): void
    {
        $akun = $this->buatPelanggan(['nama' => 'Rumah Tanpa Nomor'])->akunWarga;

        $this->assertSame(PeranPengguna::Warga, $akun->peran);
        $this->assertTrue($akun->ksm->is($this->ksm));
        $this->assertSame('Rumah Tanpa Nomor', $akun->name);
        $this->assertNull($akun->no_hp);
        $this->assertTrue($akun->pakai_sandi_default);
    }

    public function test_customer_with_number_signs_in_with_the_village_default_password(): void
    {
        $akun = $this->buatPelanggan(['nama' => 'Rumah Bernomor', 'no_hp_akun' => '+62 812 0000 1111'])->akunWarga;
        $this->assertSame('081200001111', $akun->no_hp);

        auth()->logout();
        URL::forceRootUrl('http://makmurjaya.ksmdesa.test');

        Livewire::test(Login::class)
            ->fillForm(['login' => '0812-0000-1111', 'password' => 'desa12345'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($akun);
    }

    public function test_a_number_already_in_use_links_to_that_account_instead_of_creating_one(): void
    {
        $pemilik = User::factory()->for($this->ksm)->warga()->create(['no_hp' => '081200002222']);
        $jumlahAkun = User::query()->count();

        $pelanggan = $this->buatPelanggan(['nama' => 'Warung Kedua', 'no_hp_akun' => '0812 0000 2222']);

        $this->assertTrue($pelanggan->akunWarga->is($pemilik));
        $this->assertSame($jumlahAkun, User::query()->count());
    }

    public function test_adding_a_number_later_renumbers_the_customers_own_account(): void
    {
        $pelanggan = $this->buatPelanggan(['nama' => 'Rumah Lama']);
        $akunId = $pelanggan->user_id;

        Livewire::test(ManagePelanggans::class)
            ->callAction(TestAction::make('edit')->table($pelanggan), data: ['no_hp_akun' => '081200003333'])
            ->assertHasNoFormErrors();

        $pelanggan->refresh();
        $this->assertSame($akunId, $pelanggan->user_id);
        $this->assertSame('081200003333', $pelanggan->akunWarga->no_hp);
    }

    public function test_a_new_number_on_a_shared_account_gives_this_customer_its_own_account(): void
    {
        $bersama = User::factory()->for($this->ksm)->warga()->create(['no_hp' => '081200004444']);
        $rumah = $this->buatPelanggan(['nama' => 'Rumah', 'no_hp_akun' => '081200004444']);
        $warung = $this->buatPelanggan(['nama' => 'Warung', 'no_hp_akun' => '081200004444']);

        Livewire::test(ManagePelanggans::class)
            ->callAction(TestAction::make('edit')->table($warung), data: ['no_hp_akun' => '081200005555'])
            ->assertHasNoFormErrors();

        $this->assertTrue($rumah->refresh()->akunWarga->is($bersama));
        $this->assertSame('081200004444', $bersama->refresh()->no_hp);
        $this->assertSame('081200005555', $warung->refresh()->akunWarga->no_hp);
        $this->assertFalse($warung->akunWarga->is($bersama));
    }

    public function test_every_creation_path_gives_an_account(): void
    {
        $pelanggan = Pelanggan::factory()->for($this->ksm)->create();

        $this->assertNotNull($pelanggan->refresh()->user_id);
        $this->assertSame(PeranPengguna::Warga, $pelanggan->akunWarga->peran);
    }

    public function test_backfill_command_is_idempotent(): void
    {
        $tanpaAkun = Pelanggan::factory()->for($this->ksm)->count(2)->create();
        Pelanggan::query()->whereKey($tanpaAkun->modelKeys())->update(['user_id' => null]);

        $this->artisan('app:akun-warga', ['ksm' => 'makmurjaya'])->expectsOutputToContain('2 akun warga dibuat')->assertSuccessful();
        $this->artisan('app:akun-warga', ['ksm' => 'makmurjaya'])->expectsOutputToContain('0 akun warga dibuat')->assertSuccessful();

        $this->assertSame(0, Pelanggan::query()->whereNull('user_id')->count());
    }

    public function test_new_default_moves_accounts_still_on_it_but_not_those_changed_by_their_owner(): void
    {
        $masihDefault = $this->buatPelanggan(['nama' => 'Masih Default'])->akunWarga;
        $sudahGanti = $this->buatPelanggan(['nama' => 'Sudah Ganti'])->akunWarga;
        $sudahGanti->update(['password' => 'milik-sendiri-1']);
        $this->assertFalse($sudahGanti->refresh()->pakai_sandi_default);

        Livewire::test(EditProfilKsm::class)
            ->fillForm(['sandi_default_hash' => 'baru-desa-99'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('baru-desa-99', $masihDefault->refresh()->getAuthPassword()));
        $this->assertTrue(Hash::check('milik-sendiri-1', $sudahGanti->refresh()->getAuthPassword()));
    }

    public function test_accounts_made_before_any_default_get_it_once_set(): void
    {
        $this->ksm->forceFill(['sandi_default_hash' => null])->save();
        $akun = $this->buatPelanggan(['nama' => 'Sebelum Default'])->akunWarga;
        $this->assertFalse(Hash::check('desa12345', $akun->getAuthPassword()));

        Livewire::test(EditProfilKsm::class)
            ->fillForm(['sandi_default_hash' => 'pertama-123'])
            ->call('save');

        $this->assertTrue(Hash::check('pertama-123', $akun->refresh()->getAuthPassword()));
    }

    public function test_blank_default_field_keeps_the_default_and_the_hash_never_reaches_the_form(): void
    {
        $hash = $this->ksm->sandi_default_hash;

        Livewire::test(EditProfilKsm::class)
            ->assertSchemaStateSet(['sandi_default_hash' => null])
            ->assertDontSee($hash)
            ->fillForm(['nama' => 'Nama Baru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($hash, $this->ksm->refresh()->sandi_default_hash);
    }

    public function test_promoting_a_default_password_account_requires_a_new_password(): void
    {
        $akun = $this->buatPelanggan(['nama' => 'Calon Petugas', 'no_hp_akun' => '081200007777'])->akunWarga;

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($akun), data: ['peran' => PeranPengguna::Petugas->value])
            ->assertHasFormErrors(['password' => 'required']);
        $this->assertSame(PeranPengguna::Warga, $akun->refresh()->peran);

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($akun), data: ['peran' => PeranPengguna::Petugas->value, 'password' => 'sandi-petugas-1'])
            ->assertHasNoFormErrors();

        $akun->refresh();
        $this->assertSame(PeranPengguna::Petugas, $akun->peran);
        $this->assertFalse($akun->pakai_sandi_default);
        $this->assertTrue(Hash::check('sandi-petugas-1', $akun->getAuthPassword()));
    }

    public function test_a_non_warga_account_never_keeps_or_follows_the_village_default(): void
    {
        $akun = $this->buatPelanggan(['nama' => 'Dipromosikan Lewat Kode'])->akunWarga;

        $akun->update(['peran' => PeranPengguna::Admin]);
        $this->assertFalse(Hash::check('desa12345', $akun->refresh()->getAuthPassword()));
        $this->assertFalse($akun->pakai_sandi_default);

        // Even a row flagged directly in the database is skipped when the default changes.
        User::query()->whereKey($akun)->update(['pakai_sandi_default' => true]);
        $this->ksm->update(['sandi_default_hash' => Hash::make('baru-desa-77')]);
        $this->assertFalse(Hash::check('baru-desa-77', $akun->refresh()->getAuthPassword()));
    }

    public function test_admin_setting_a_password_stops_it_following_the_default(): void
    {
        $akun = $this->buatPelanggan(['nama' => 'Diatur Admin', 'no_hp_akun' => '081200006666'])->akunWarga;

        Livewire::test(ManageUsers::class)
            ->callAction(TestAction::make('edit')->table($akun), data: ['password' => 'dari-admin-1'])
            ->assertHasNoFormErrors();

        $this->assertFalse($akun->refresh()->pakai_sandi_default);
    }
}
