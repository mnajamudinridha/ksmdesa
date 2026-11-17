<?php

namespace App\Models;

use App\Enums\AktivitasMaterial;
use Database\Factories\CatatanMaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One material activity of the KSM: sorting, a sale to a buyer, or processing (e.g. compost).
 * A sale's value is cash income on its date — RingkasanKas reads it from here, it is never
 * copied into the cash book, so editing or deleting the record changes the cash at once.
 */
#[Table('catatan_material')]
#[Fillable(['tanggal', 'aktivitas', 'jenis_material_id', 'berat_kg', 'mitra_id', 'nilai_jual', 'hasil_olah', 'catatan'])]
class CatatanMaterial extends Model
{
    /** @use HasFactory<CatatanMaterialFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Buyer and value belong to sales only, the result to processing only — also when a
        // record is switched to another activity after they were filled in.
        static::saving(function (CatatanMaterial $catatan): void {
            if (! $catatan->aktivitas->butuhPembeli()) {
                $catatan->mitra_id = null;
                $catatan->nilai_jual = null;
            }

            if (! $catatan->aktivitas->butuhHasilOlah()) {
                $catatan->hasil_olah = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'aktivitas' => AktivitasMaterial::class,
            'berat_kg' => 'decimal:1',
            'nilai_jual' => 'integer',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return BelongsTo<JenisMaterial, $this> */
    public function jenisMaterial(): BelongsTo
    {
        return $this->belongsTo(JenisMaterial::class);
    }

    /** @return BelongsTo<Mitra, $this> */
    public function pengepul(): BelongsTo
    {
        return $this->belongsTo(Mitra::class, 'mitra_id');
    }

    /** @return BelongsTo<User, $this> */
    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh_id');
    }
}
