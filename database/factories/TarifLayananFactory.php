<?php

namespace Database\Factories;

use App\Models\Ksm;
use App\Models\TarifLayanan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TarifLayanan>
 */
class TarifLayananFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ksm_id' => Ksm::factory(),
            'nama' => fake()->unique()->randomElement(['Warga biasa', 'Catering kecil', 'Catering perusahaan/sekolah', 'Toko', 'Rumah makan']),
            'nominal' => fake()->randomElement([25_000, 50_000, 100_000]),
        ];
    }
}
