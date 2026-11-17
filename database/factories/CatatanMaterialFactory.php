<?php

namespace Database\Factories;

use App\Enums\AktivitasMaterial;
use App\Models\CatatanMaterial;
use App\Models\Ksm;
use App\Support\Hari;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatatanMaterial>
 */
class CatatanMaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ksm_id' => Ksm::factory(),
            'tanggal' => Hari::tanggal(),
            'aktivitas' => AktivitasMaterial::Pilah,
            'berat_kg' => 10,
        ];
    }

    public function jual(int $nilai = 50_000): static
    {
        return $this->state(fn (array $attributes) => ['aktivitas' => AktivitasMaterial::Jual, 'nilai_jual' => $nilai]);
    }

    public function olah(): static
    {
        return $this->state(fn (array $attributes) => ['aktivitas' => AktivitasMaterial::Olah]);
    }
}
