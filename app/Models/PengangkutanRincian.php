<?php

namespace App\Models;

use App\Enums\StatusPengangkutan;
use Database\Factories\PengangkutanRincianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What happened at one house in one collection round.
 */
#[Table('pengangkutan_rincian')]
#[Fillable(['pelanggan_id', 'status', 'perkiraan_kg', 'catatan'])]
class PengangkutanRincian extends Model
{
    /** @use HasFactory<PengangkutanRincianFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // A weight only means something for waste that was collected — also when a row is
        // switched from "diangkut" to another status after the weight was typed.
        static::saving(function (PengangkutanRincian $rincian): void {
            if (! $rincian->status->butuhBerat()) {
                $rincian->perkiraan_kg = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => StatusPengangkutan::class,
            'perkiraan_kg' => 'decimal:1',
        ];
    }

    /** @return BelongsTo<Pengangkutan, $this> */
    public function pengangkutan(): BelongsTo
    {
        return $this->belongsTo(Pengangkutan::class);
    }

    /** @return BelongsTo<Pelanggan, $this> */
    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }
}
