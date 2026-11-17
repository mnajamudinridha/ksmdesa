<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

// is_super_admin is deliberately NOT fillable: only `php artisan app:super-admin` grants it.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Mirror the column default so a fresh, unsaved model is not "null" admin.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_super_admin' => false,
    ];

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
        ];
    }

    /** @return BelongsToMany<Ksm, $this> */
    public function ksm(): BelongsToMany
    {
        return $this->belongsToMany(Ksm::class)->withTimestamps();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->is_super_admin,
            'ksm' => $this->is_super_admin || $this->ksm()->exists(),
            default => false,
        };
    }

    /** @return Collection<int, Ksm> */
    public function getTenants(Panel $panel): Collection
    {
        return $this->is_super_admin ? Ksm::query()->orderBy('nama')->get() : $this->ksm;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->is_super_admin || $this->ksm()->whereKey($tenant)->exists();
    }
}
