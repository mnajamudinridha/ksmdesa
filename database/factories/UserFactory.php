<?php

namespace Database\Factories;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Master administrator: belongs to no KSM, may use /admin and enter every KSM.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_super_admin' => true,
            'ksm_id' => null,
            'peran' => null,
        ]);
    }

    /** Village user with a role; combine with ->for($ksm). */
    public function peran(PeranPengguna $peran): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => $peran,
            'no_hp' => '08'.fake()->unique()->numerify('##########'),
        ]);
    }

    public function admin(): static
    {
        return $this->peran(PeranPengguna::Admin);
    }

    public function petugas(): static
    {
        return $this->peran(PeranPengguna::Petugas);
    }

    public function warga(): static
    {
        return $this->peran(PeranPengguna::Warga);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['aktif' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
