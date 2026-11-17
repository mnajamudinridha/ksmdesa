<?php

namespace App\Models;

use App\Enums\JenisMitra;
use Database\Factories\MitraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A partner of the KSM (government, company/CSR, buyer, community…). Income from a partner
 * is a cash-book entry that names it; that sum is the partner's support. A buyer (pengepul)
 * is named on material sales.
 */
#[Table('mitra')]
#[Fillable(['nama', 'jenis', 'kontak', 'no_hp', 'alamat', 'keterangan', 'aktif'])]
class Mitra extends Model
{
    /** @use HasFactory<MitraFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'jenis' => JenisMitra::class,
            'aktif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return HasMany<TransaksiKas, $this> */
    public function transaksiKas(): HasMany
    {
        return $this->hasMany(TransaksiKas::class);
    }

    /**
     * Material sales to this partner (as buyer, "pengepul").
     *
     * @return HasMany<CatatanMaterial, $this>
     */
    public function catatanMaterial(): HasMany
    {
        return $this->hasMany(CatatanMaterial::class);
    }

    /** A partner named on any cash entry or material sale stays — deactivate it instead. */
    public function dipakai(): bool
    {
        return $this->transaksiKas()->exists() || $this->catatanMaterial()->exists();
    }
}
