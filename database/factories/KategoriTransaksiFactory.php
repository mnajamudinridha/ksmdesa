<?php

namespace Database\Factories;

use App\Enums\JenisTransaksi;
use App\Models\KategoriTransaksi;
use App\Models\Ksm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KategoriTransaksi>
 */
class KategoriTransaksiFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ksm_id' => Ksm::factory(),
            'jenis' => JenisTransaksi::Keluar,
            'nama' => fake()->unique()->words(2, true),
            'aktif' => true,
        ];
    }

    public function masuk(): static
    {
        return $this->state(fn (array $attributes) => ['jenis' => JenisTransaksi::Masuk]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => ['aktif' => false]);
    }
}
