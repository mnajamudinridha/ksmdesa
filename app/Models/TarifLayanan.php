<?php

namespace App\Models;

use Database\Factories\TarifLayananFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Waste-pickup tariff tier, e.g. "Warga biasa" Rp25.000 / month.
 */
#[Table('tarif_layanan')]
#[Fillable(['nama', 'nominal'])]
class TarifLayanan extends Model
{
    /** @use HasFactory<TarifLayananFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return HasMany<Pelanggan, $this> */
    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class);
    }
}
