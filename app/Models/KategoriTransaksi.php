<?php

namespace App\Models;

use App\Enums\JenisTransaksi;
use Database\Factories\KategoriTransaksiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Category of a manual cash-book entry, for income or for spending. Fees and material sales
 * are counted automatically from their own records, so they are never categories here.
 */
#[Table('kategori_transaksi')]
#[Fillable(['jenis', 'nama', 'aktif'])]
class KategoriTransaksi extends Model
{
    /** @use HasFactory<KategoriTransaksiFactory> */
    use HasFactory;

    /**
     * Names of the automatic income sources (lowercase): a category with one of these names
     * would make the report count that income twice.
     *
     * @var list<string>
     */
    public const array NAMA_SISTEM = ['iuran', 'penjualan material'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisTransaksi::class,
            'aktif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return HasMany<TransaksiKas, $this> */
    public function transaksiKas(): HasMany
    {
        return $this->hasMany(TransaksiKas::class);
    }

    /** A category with transactions keeps its kind and cannot be deleted — deactivate it instead. */
    public function dipakai(): bool
    {
        return $this->transaksiKas()->exists();
    }
}
