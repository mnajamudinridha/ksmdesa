<?php

namespace App\Models;

use Database\Factories\WilayahFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One RT (optionally within an RW / dusun) of the village. Customers pick theirs from this
 * list, which is what lets the reports count coverage per RT ("RT terlayani X dari Y").
 */
#[Table('wilayah')]
#[Fillable(['rt', 'rw', 'dusun', 'jumlah_kk'])]
class Wilayah extends Model
{
    /** @use HasFactory<WilayahFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rt' => 'integer',
            'rw' => 'integer',
            'jumlah_kk' => 'integer',
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

    /** "RT 03 / RW 01 · Dusun Makmur" */
    public function label(): string
    {
        return sprintf('RT %02d', $this->rt)
            .($this->rw !== null ? sprintf(' / RW %02d', $this->rw) : '')
            .(filled($this->dusun) ? " · {$this->dusun}" : '');
    }

    /** An RT that still has customers cannot be deleted. */
    public function dipakai(): bool
    {
        return $this->pelanggan()->exists();
    }

    /** RTs without an RW first, then by RW and RT number. */
    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderBy('rw')->orderBy('rt');
    }
}
