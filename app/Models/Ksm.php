<?php

namespace App\Models;

use App\Enums\PeranPengguna;
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
use Illuminate\Support\Facades\Storage;

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
    use HasFactory;

    protected static function booted(): void
    {
        // FileUpload never deletes the files it replaces; the model owns the images' lifecycle.
        static::updated(function (Ksm $ksm): void {
            if ($ksm->wasChanged('logo') && filled($lama = $ksm->getOriginal('logo'))) {
                Storage::disk('public')->delete($lama);
            }

            if ($ksm->wasChanged('latar_login')) {
                $dibuang = array_diff($ksm->getOriginal('latar_login') ?? [], $ksm->latar_login ?? []);
                Storage::disk('public')->delete(array_values($dibuang));
            }
        });

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

        static::deleted(function (Ksm $ksm): void {
            Storage::disk('public')->delete(array_filter([$ksm->logo, ...($ksm->latar_login ?? [])]));
        });
    }

    protected function casts(): array
    {
        return [
            'latar_login' => 'array',
        ];
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

    public function logoUrl(): ?string
    {
        return filled($this->logo) ? Storage::disk('public')->url($this->logo) : null;
    }

    /**
     * One of the login page backgrounds, picked at random, or null. Only paths the upload
     * field can produce are used: the value goes into a CSS url().
     */
    public function latarLoginUrl(): ?string
    {
        $latar = array_values(array_filter(
            $this->latar_login ?? [],
            fn (mixed $path): bool => is_string($path) && preg_match('#^latar-login/[A-Za-z0-9._-]+$#', $path) === 1,
        ));

        return $latar === [] ? null : Storage::disk('public')->url($latar[array_rand($latar)]);
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
}
