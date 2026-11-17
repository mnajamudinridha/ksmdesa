<?php

namespace App\Models;

use Database\Factories\KsmFactory;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * A KSM (Kelompok Swadaya Masyarakat) — one per village, served on <slug>.<APP_DOMAIN>.
 * This is the tenant of the `ksm` Filament panel.
 */
#[Table('ksm')]
#[Fillable(['nama', 'slug', 'desa', 'logo'])]
class Ksm extends Model implements HasName
{
    /** @use HasFactory<KsmFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // FileUpload never deletes the file it replaces; the model owns the logo's lifecycle.
        static::updated(function (Ksm $ksm): void {
            if ($ksm->wasChanged('logo') && filled($lama = $ksm->getOriginal('logo'))) {
                Storage::disk('public')->delete($lama);
            }
        });

        static::deleted(function (Ksm $ksm): void {
            if (filled($ksm->logo)) {
                Storage::disk('public')->delete($ksm->logo);
            }
        });
    }

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

    public function logoUrl(): ?string
    {
        return filled($this->logo) ? Storage::disk('public')->url($this->logo) : null;
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
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
