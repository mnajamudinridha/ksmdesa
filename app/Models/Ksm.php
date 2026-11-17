<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Models\Concerns\PunyaTampilanLogin;
use Carbon\CarbonInterface;
use Database\Factories\KsmFactory;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
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
#[Fillable(['nama', 'slug', 'desa', 'logo', 'latar_login', 'sandi_default_hash', 'saldo_awal', 'kas_mulai'])]
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
        // Rows go with the database cascade (no model events), so their receipt files are removed here.
        static::deleted(function (Ksm $ksm): void {
            Storage::disk('local')->deleteDirectory("bukti-kas/{$ksm->getKey()}");
        });

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

    /**
     * Same as the column default, so a KSM made in code (not re-read) has an opening balance too —
     * the profile form requires one.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'saldo_awal' => 0,
    ];

    protected function casts(): array
    {
        return [
            'saldo_awal' => 'integer',
            'kas_mulai' => 'date',
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

    /** @return HasMany<KategoriTransaksi, $this> */
    public function kategoriTransaksi(): HasMany
    {
        return $this->hasMany(KategoriTransaksi::class);
    }

    /** @return HasMany<Mitra, $this> */
    public function mitra(): HasMany
    {
        return $this->hasMany(Mitra::class);
    }

    /** @return HasMany<TransaksiKas, $this> */
    public function transaksiKas(): HasMany
    {
        return $this->hasMany(TransaksiKas::class);
    }

    /**
     * Relations whose new rows show that a KSM is using the app ("aktif mencatat").
     *
     * @var list<string>
     */
    public const RELASI_CATATAN = ['pengangkutan', 'pembayaranIuran', 'setoran', 'transaksiKas'];

    /** A KSM counts as "aktif mencatat" when it recorded something within this many days. */
    public const int HARI_AKTIF_MENCATAT = 7;

    public static function batasAktifMencatat(): CarbonInterface
    {
        return now()->subDays(self::HARI_AKTIF_MENCATAT);
    }

    /** KSMs that recorded anything (rounds, fees, deposits, cash entries) since $sejak — an instant, compared with created_at. */
    public function scopeAktifMencatat(Builder $query, CarbonInterface $sejak): Builder
    {
        return $query->where(function (Builder $ada) use ($sejak): void {
            foreach (self::RELASI_CATATAN as $relasi) {
                $ada->orWhereHas($relasi, fn (Builder $catatan) => $catatan->where('created_at', '>=', $sejak));
            }
        });
    }
}
