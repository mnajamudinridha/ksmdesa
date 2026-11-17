<?php

namespace App\Models;

use Database\Factories\PembayaranIuranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One customer's fee payment for one month (`periode` = first day of that month).
 * It counts as paid as soon as it exists. petugas_id / setoran_id only track the cash:
 * collected by a petugas and not yet handed over = "belum disetor" (scopeBelumDisetor).
 * Both are set by code (Terima iuran, Setoran), never from form input — not fillable.
 */
#[Table('pembayaran_iuran')]
#[Fillable(['pelanggan_id', 'periode', 'nominal', 'dibayar_pada', 'catatan'])]
class PembayaranIuran extends Model
{
    /** @use HasFactory<PembayaranIuranFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Keep the denormalised tenant key in step with the customer, whatever the caller sent.
        static::saving(function (PembayaranIuran $pembayaran): void {
            $pembayaran->periode = $pembayaran->periode->startOfMonth();
            $pembayaran->ksm_id = $pembayaran->pelanggan()->value('ksm_id');
        });
    }

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'nominal' => 'integer',
            'dibayar_pada' => 'date',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return BelongsTo<Pelanggan, $this> */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    /**
     * The petugas who took the cash; null = received directly by the committee.
     *
     * @return BelongsTo<User, $this>
     */
    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    /** @return BelongsTo<Setoran, $this> */
    public function setoran(): BelongsTo
    {
        return $this->belongsTo(Setoran::class);
    }

    /** Collected by a petugas and not yet handed to the committee. */
    public function scopeBelumDisetor(Builder $query): Builder
    {
        return $query->whereNotNull('petugas_id')->whereNull('setoran_id');
    }

    /** @return 'langsung'|'belum'|'disetor' */
    public function statusSetor(): string
    {
        return match (true) {
            $this->petugas_id === null => 'langsung',
            $this->setoran_id === null => 'belum',
            default => 'disetor',
        };
    }
}
