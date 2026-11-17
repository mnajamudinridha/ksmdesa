<?php

namespace Database\Factories;

use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PembayaranIuran>
 */
class PembayaranIuranFactory extends Factory
{
    /**
     * ksm_id is filled from the customer by the model's saving hook.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pelanggan_id' => Pelanggan::factory(),
            'periode' => now()->startOfMonth(),
            'nominal' => 25_000,
        ];
    }
}
