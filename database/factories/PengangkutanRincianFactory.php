<?php

namespace Database\Factories;

use App\Enums\StatusPengangkutan;
use App\Models\Pelanggan;
use App\Models\Pengangkutan;
use App\Models\PengangkutanRincian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PengangkutanRincian>
 */
class PengangkutanRincianFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pengangkutan_id' => Pengangkutan::factory(),
            'pelanggan_id' => Pelanggan::factory(),
            'status' => StatusPengangkutan::Diangkut,
            'perkiraan_kg' => fake()->randomFloat(1, 1, 15),
        ];
    }
}
