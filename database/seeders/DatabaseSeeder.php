<?php

namespace Database\Seeders;

use App\Enums\PeranPengguna;
use App\Models\Ksm;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Local development only. Production accounts come from `php artisan app:super-admin`
 * and the panels; production data from `php artisan app:import-iuran`.
 *
 * Every seeded account uses the factory default password: "password".
 *   master  : test@example.com            → http://localhost:8000/admin
 *   per KSM : 0811000000<n>1 admin, …2 petugas, …3 warga → http://<slug>.localhost:8000/login
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::query()->whereNull('ksm_id')->firstWhere('email', 'test@example.com')
            ?? User::factory()->superAdmin()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        $admin->forceFill(['is_super_admin' => true, 'aktif' => true])->save();

        Ksm::query()->orderBy('id')->get()->each(function (Ksm $ksm, int $i): void {
            foreach ([1 => PeranPengguna::Admin, 2 => PeranPengguna::Petugas, 3 => PeranPengguna::Warga] as $n => $peran) {
                $ksm->users()->firstOrCreate(
                    ['no_hp' => sprintf('0811000000%d%d', $i + 1, $n)],
                    ['name' => "{$peran->getLabel()} {$ksm->desa}", 'peran' => $peran, 'password' => 'password'],
                );
            }
        });
    }
}
