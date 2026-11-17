<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use App\Support\AkunWarga;
use Database\Factories\PelangganFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A household or business whose waste the KSM collects for a monthly fee.
 * Every customer has a warga account (user_id) — created/linked only by App\Support\AkunWarga,
 * which is why user_id is not fillable. The phone number lives on that account.
 */
#[Table('pelanggan')]
#[Fillable([
    'tarif_layanan_id', 'petugas_id', 'nomor_urut', 'nama', 'iuran_bulanan', 'alamat', 'rt', 'rw',
    'patokan', 'latitude', 'longitude', 'aktif', 'catatan', 'catatan_pengangkutan',
])]
class Pelanggan extends Model
{
    /** @use HasFactory<PelangganFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Every path that creates customers (forms, CSV import, seeders) ends up with an account.
        static::created(function (Pelanggan $pelanggan): void {
            if ($pelanggan->user_id === null && $pelanggan->ksm !== null) {
                $pelanggan->akunWarga()->associate(AkunWarga::untukNomor($pelanggan->ksm, null, $pelanggan->nama));
                $pelanggan->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'nomor_urut' => 'integer',
            'iuran_bulanan' => 'integer',
            'aktif' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return BelongsTo<TarifLayanan, $this> */
    public function tarifLayanan(): BelongsTo
    {
        return $this->belongsTo(TarifLayanan::class);
    }

    /** @return BelongsTo<User, $this> */
    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    /** @return BelongsTo<User, $this> */
    public function akunWarga(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<PembayaranIuran, $this> */
    public function pembayaranIuran(): HasMany
    {
        return $this->hasMany(PembayaranIuran::class);
    }

    public function punyaPetugasAktif(): bool
    {
        return $this->petugas !== null && $this->petugas->aktif && $this->petugas->peran === PeranPengguna::Petugas;
    }

    /** Customers without an active collector (none assigned, or the assigned one left/changed role). */
    public function scopeTanpaPetugasAktif(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('petugas_id')
            ->orWhereDoesntHave('petugas', fn (Builder $p) => $p->where('aktif', true)->where('peran', PeranPengguna::Petugas)));
    }

    /**
     * Google Maps link: exact coordinates when known, otherwise the address. The scheme is fixed
     * https and coordinates are numeric, so stored data cannot inject a javascript: URL.
     */
    public function mapsUrl(): ?string
    {
        $query = match (true) {
            $this->latitude !== null && $this->longitude !== null => "{$this->latitude},{$this->longitude}",
            filled($this->alamat) => trim($this->alamat.', '.($this->ksm?->desa ?? '')),
            default => null,
        };

        return $query === null ? null : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query);
    }
}
