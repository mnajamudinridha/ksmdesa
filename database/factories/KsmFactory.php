<?php

namespace Database\Factories;

use App\Models\Ksm;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Ksm>
 */
class KsmFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $desa = fake()->unique()->city();

        return [
            'nama' => 'KSM '.$desa,
            'slug' => Str::slug($desa, ''),
            'desa' => $desa,
        ];
    }
}
