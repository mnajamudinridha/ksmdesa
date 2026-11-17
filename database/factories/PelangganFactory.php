<?php

namespace Database\Factories;

use App\Models\Ksm;
use App\Models\Pelanggan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pelanggan>
 */
class PelangganFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ksm_id' => Ksm::factory(),
            'nama' => fake()->name(),
            'iuran_bulanan' => 25_000,
            'aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['aktif' => false]);
    }
}
