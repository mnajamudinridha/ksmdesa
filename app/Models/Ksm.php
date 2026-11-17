<?php

namespace App\Models;

use Database\Factories\KsmFactory;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A KSM (Kelompok Swadaya Masyarakat) — one per village, served on <slug>.<APP_DOMAIN>.
 * This is the tenant of the `ksm` Filament panel.
 */
#[Table('ksm')]
#[Fillable(['nama', 'slug', 'desa'])]
class Ksm extends Model implements HasName
{
    /** @use HasFactory<KsmFactory> */
    use HasFactory;

    public function getFilamentName(): string
    {
        return $this->nama;
    }

    /** Public URL of this KSM's panel, e.g. https://makmurjaya.ksmdesa.my.id */
    public function url(): string
    {
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return "{$scheme}://{$this->slug}.".config('app.domain');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** @return HasMany<TarifLayanan, $this> */
    public function tarifLayanan(): HasMany
    {
        return $this->hasMany(TarifLayanan::class);
    }

    /** @return HasMany<Pelanggan, $this> */
    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class);
    }

    /** @return HasMany<PembayaranIuran, $this> */
    public function pembayaranIuran(): HasMany
    {
        return $this->hasMany(PembayaranIuran::class);
    }
}
