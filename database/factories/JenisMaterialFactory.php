<?php

namespace Database\Factories;

use App\Enums\KelompokMaterial;
use App\Models\JenisMaterial;
use App\Models\Ksm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JenisMaterial>
 */
class JenisMaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ksm_id' => Ksm::factory(),
            'nama' => fake()->unique()->words(2, true),
            'kelompok' => KelompokMaterial::Plastik,
            'aktif' => true,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['aktif' => false]);
    }
}
