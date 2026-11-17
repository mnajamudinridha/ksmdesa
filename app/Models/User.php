<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PeranPengguna;
use App\Support\KsmDariHost;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A village user belongs to exactly one KSM and has a role there; a master
 * administrator (is_super_admin) belongs to none.
 *
 * is_super_admin and ksm_id are deliberately NOT fillable: ksm_id comes from the
 * relationship / Filament's tenant association, is_super_admin only from
 * `php artisan app:super-admin` or the master Administrator page.
 */
#[Fillable(['name', 'avatar', 'email', 'no_hp', 'password', 'peran', 'aktif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar, HasDefaultTenant, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Mirror the column defaults so a fresh, unsaved model is not "null".
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_super_admin' => false,
        'aktif' => true,
    ];

    protected static function booted(): void
    {
        // FileUpload never deletes the photo it replaces; the model owns the file's lifecycle.
        static::updated(function (User $user): void {
            if ($user->wasChanged('avatar') && filled($lama = $user->getOriginal('avatar'))) {
                Storage::disk('local')->delete($lama);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'aktif' => 'boolean',
            'peran' => PeranPengguna::class,
        ];
    }

    /** Private photo → short-lived signed URL; null lets the panel draw initials (InisialAvatar). */
    public function getFilamentAvatarUrl(): ?string
    {
        return filled($this->avatar) ? Storage::disk('local')->temporaryUrl($this->avatar, now()->addHour()) : null;
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** Master administrators act as admin in every KSM. */
    public function isAdminKsm(): bool
    {
        return $this->is_super_admin || $this->peran === PeranPengguna::Admin;
    }

    public function isPetugas(): bool
    {
        return ! $this->is_super_admin && $this->peran === PeranPengguna::Petugas;
    }

    public function isWarga(): bool
    {
        return ! $this->is_super_admin && $this->peran === PeranPengguna::Warga;
    }

    /** Checked by Filament before a login succeeds and again on every request. */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->aktif) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->is_super_admin,
            'ksm' => $this->is_super_admin || ($this->ksm_id !== null && $this->peran !== null),
            default => false,
        };
    }

    /** @return Collection<int, Ksm> */
    public function getTenants(Panel $panel): Collection
    {
        if ($this->is_super_admin) {
            return Ksm::query()->orderBy('nama')->get();
        }

        return collect(array_filter([$this->ksm]));
    }

    /** The only check standing between a user and another KSM's URL. */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->is_super_admin || ($this->ksm_id !== null && $this->ksm()->is($tenant));
    }

    /**
     * After login Filament redirects to this tenant. For a master administrator that must be
     * the KSM of the subdomain they signed in on — any other KSM is a different host without
     * their session, so they would land on a login page again.
     */
    public function getDefaultTenant(Panel $panel): ?Model
    {
        if ($this->is_super_admin) {
            return KsmDariHost::dariRequest() ?? Ksm::query()->orderBy('nama')->first();
        }

        return $this->ksm;
    }
}
