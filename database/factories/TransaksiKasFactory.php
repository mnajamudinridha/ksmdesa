<?php

namespace Database\Factories;

use App\Enums\JenisTransaksi;
use App\Models\Ksm;
use App\Models\TransaksiKas;
use App\Support\Hari;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransaksiKas>
 */
class TransaksiKasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ksm_id' => Ksm::factory(),
            'tanggal' => Hari::tanggal(),
            'jenis' => JenisTransaksi::Keluar,
            'nominal' => 50_000,
            'uraian' => fake()->sentence(4),
        ];
    }

    public function masuk(): static
    {
        return $this->state(fn (array $attributes) => ['jenis' => JenisTransaksi::Masuk]);
    }
}
