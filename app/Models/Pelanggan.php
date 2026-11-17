<?php

namespace App\Models;

use Database\Factories\PelangganFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A household or business whose waste the KSM collects for a monthly fee.
 */
#[Table('pelanggan')]
#[Fillable(['tarif_layanan_id', 'nomor_urut', 'nama', 'iuran_bulanan', 'rt', 'aktif', 'catatan'])]
class Pelanggan extends Model
{
    /** @use HasFactory<PelangganFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'nomor_urut' => 'integer',
            'iuran_bulanan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return BelongsTo<TarifLayanan, $this> */
    public function tarifLayanan(): BelongsTo
    {
        return $this->belongsTo(TarifLayanan::class);
    }

    /** @return HasMany<PembayaranIuran, $this> */
    public function pembayaranIuran(): HasMany
    {
        return $this->hasMany(PembayaranIuran::class);
    }
}
