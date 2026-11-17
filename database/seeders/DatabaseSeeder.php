<?php

namespace Database\Seeders;

use App\Models\Ksm;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Local development only. Production accounts come from `php artisan app:super-admin`
 * and the master panel; production data from `php artisan app:import-iuran`.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Password is the factory default: "password".
        $admin = User::query()->firstWhere('email', 'test@example.com')
            ?? User::factory()->superAdmin()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $admin->forceFill(['is_super_admin' => true])->save();
        $admin->ksm()->syncWithoutDetaching(Ksm::query()->pluck('id'));
    }
}
