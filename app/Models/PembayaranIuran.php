<?php

namespace App\Models;

use Database\Factories\PembayaranIuranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One customer's fee payment for one month (`periode` = first day of that month).
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
}
