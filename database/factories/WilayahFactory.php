<?php

namespace Database\Factories;

use App\Models\Ksm;
use App\Models\Wilayah;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wilayah>
 */
class WilayahFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ksm_id' => Ksm::factory(),
            'rt' => fake()->unique()->numberBetween(1, 200),
            'rw' => fake()->numberBetween(1, 10),
        ];
    }
}
