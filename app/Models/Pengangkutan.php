<?php

namespace App\Models;

use App\Support\Hari;
use Database\Factories\PengangkutanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One petugas's collection round on one day, with a row per house visited (rincian).
 * ksm_id comes from Filament's tenant association, never from input.
 */
#[Table('pengangkutan')]
#[Fillable(['petugas_id', 'tanggal', 'catatan'])]
class Pengangkutan extends Model
{
    /** @use HasFactory<PengangkutanFactory> */
    use HasFactory;

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

    /** @return HasMany<PengangkutanRincian, $this> */
    public function rincian(): HasMany
    {
        return $this->hasMany(PengangkutanRincian::class);
    }

    /** A petugas may still correct today's and yesterday's round (village time); admins always may. */
    public function bisaDiubahPetugas(): bool
    {
        return $this->tanggal->toDateString() >= Hari::tanggal(1);
    }
}
