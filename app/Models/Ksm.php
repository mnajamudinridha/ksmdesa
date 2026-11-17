<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Models\Concerns\PunyaTampilanLogin;
use Database\Factories\KsmFactory;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

/**
 * A KSM (Kelompok Swadaya Masyarakat) — one per village, served on <slug>.<APP_DOMAIN>.
 * This is the tenant of the `ksm` Filament panel.
 */
#[Table('ksm')]
#[Fillable(['nama', 'slug', 'desa', 'logo', 'latar_login', 'sandi_default_hash'])]
// The hash never reaches form state / the browser.
#[Hidden(['sandi_default_hash'])]
class Ksm extends Model implements HasAvatar, HasName
{
    /** @use HasFactory<KsmFactory> */
    use HasFactory, PunyaTampilanLogin;

    protected static function booted(): void
    {
        // A new default password follows every account that still uses the default
        // (auto-created and never changed). Query builder: the value is already a hash.
        static::updated(function (Ksm $ksm): void {
            if ($ksm->wasChanged('sandi_default_hash') && filled($ksm->sandi_default_hash)) {
                User::query()
                    ->whereBelongsTo($ksm)
                    ->where('peran', PeranPengguna::Warga)
                    ->where('pakai_sandi_default', true)
                    ->toBase()
                    ->update(['password' => $ksm->sandi_default_hash]);
            }
        });
    }

    /** The KSM switcher shows the logo instead of initials. */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logoUrl();
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

    /** Is this the village default password? Every resident knows it, so admins/petugas may not use it. */
    public function cocokSandiDefault(?string $sandi): bool
    {
        return filled($sandi) && filled($this->sandi_default_hash) && Hash::check($sandi, $this->sandi_default_hash);
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

    /** @return HasMany<Pengangkutan, $this> */
    public function pengangkutan(): HasMany
    {
        return $this->hasMany(Pengangkutan::class);
    }

    /** @return HasMany<Setoran, $this> */
    public function setoran(): HasMany
    {
        return $this->hasMany(Setoran::class);
    }

    /** @return HasMany<Wilayah, $this> */
    public function wilayah(): HasMany
    {
        return $this->hasMany(Wilayah::class);
    }
}
