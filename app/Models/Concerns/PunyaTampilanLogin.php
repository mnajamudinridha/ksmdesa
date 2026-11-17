<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * A logo and login-page background photos on the public disk — for a KSM and for the
 * master panel (PengaturanMaster). FileUpload never deletes the files it replaces, so the
 * model owns their lifecycle.
 *
 * @property string|null $logo
 * @property array<int, string>|null $latar_login
 */
trait PunyaTampilanLogin
{
    protected static function bootPunyaTampilanLogin(): void
    {
        static::updated(function (self $model): void {
            if ($model->wasChanged('logo') && filled($lama = $model->getOriginal('logo'))) {
                Storage::disk('public')->delete($lama);
            }

            if ($model->wasChanged('latar_login')) {
                $dibuang = array_diff($model->getOriginal('latar_login') ?? [], $model->latar_login ?? []);
                Storage::disk('public')->delete(array_values($dibuang));
            }
        });

        static::deleted(function (self $model): void {
            Storage::disk('public')->delete(array_filter([$model->logo, ...($model->latar_login ?? [])]));
        });
    }

    public function initializePunyaTampilanLogin(): void
    {
        $this->mergeCasts(['latar_login' => 'array']);
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
}
