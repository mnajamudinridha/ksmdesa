<?php

namespace App\Models;

use App\Enums\KelompokMaterial;
use Database\Factories\JenisMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A kind of sorted material the KSM handles, e.g. "Plastik PET" in group Plastik. */
#[Table('jenis_material')]
#[Fillable(['nama', 'kelompok', 'aktif'])]
class JenisMaterial extends Model
{
    /** @use HasFactory<JenisMaterialFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kelompok' => KelompokMaterial::class,
            'aktif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return HasMany<CatatanMaterial, $this> */
    public function catatanMaterial(): HasMany
    {
        return $this->hasMany(CatatanMaterial::class);
    }

    /** A kind with records cannot be deleted — deactivate it instead. */
    public function dipakai(): bool
    {
        return $this->catatanMaterial()->exists();
    }
}
