<?php

namespace Database\Factories;

use App\Models\Ksm;
use App\Models\Setoran;
use App\Models\User;
use App\Support\Hari;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setoran>
 */
class SetoranFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ksm_id' => Ksm::factory(),
            'petugas_id' => fn (array $attributes) => User::factory()->petugas()->state(['ksm_id' => $attributes['ksm_id']]),
            'tanggal' => Hari::tanggal(),
        ];
    }
}
