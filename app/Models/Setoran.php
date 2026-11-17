<?php

namespace App\Models;

use Database\Factories\SetoranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cash a petugas handed to the committee: the fee payments they collected, received by an
 * admin on a day. The total is always the sum of its payments (never stored), so an admin
 * correcting a payment keeps it right. ksm_id comes from the tenant, diterima_oleh_id from
 * the signed-in admin — neither from input.
 */
#[Table('setoran')]
#[Fillable(['petugas_id', 'tanggal', 'catatan'])]
class Setoran extends Model
{
    /** @use HasFactory<SetoranFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Cancelling a deposit hands its payments back to "belum disetor" — never deletes them.
        // (The FK is nullOnDelete too; this keeps it explicit and inside the same delete.)
        static::deleting(function (Setoran $setoran): void {
            $setoran->pembayaranIuran()->update(['setoran_id' => null]);
        });
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return BelongsTo<User, $this> */
    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh_id');
    }

    /** @return HasMany<PembayaranIuran, $this> */
    public function pembayaranIuran(): HasMany
    {
        return $this->hasMany(PembayaranIuran::class);
    }
}
