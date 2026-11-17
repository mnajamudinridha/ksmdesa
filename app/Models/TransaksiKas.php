<?php

namespace App\Models;

use App\Enums\JenisTransaksi;
use Database\Factories\TransaksiKasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One manual cash-book entry: other income or spending. Fees and material sales are not
 * entered here — the cash summary counts them from their own records. ksm_id comes from
 * the tenant and dicatat_oleh_id from the signed-in user, never from input.
 */
#[Table('transaksi_kas')]
#[Fillable(['tanggal', 'jenis', 'kategori_transaksi_id', 'mitra_id', 'nominal', 'uraian', 'bukti'])]
class TransaksiKas extends Model
{
    /** @use HasFactory<TransaksiKasFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Only income comes from a partner — also when an entry is switched to spending.
        static::saving(function (TransaksiKas $transaksi): void {
            if (! $transaksi->jenis->bolehMitra()) {
                $transaksi->mitra_id = null;
            }
        });

        // FileUpload never deletes the receipt it replaces; the model owns the file's lifecycle.
        static::updated(function (TransaksiKas $transaksi): void {
            if ($transaksi->wasChanged('bukti') && filled($lama = $transaksi->getOriginal('bukti'))) {
                Storage::disk('local')->delete($lama);
            }
        });

        static::deleted(function (TransaksiKas $transaksi): void {
            if (filled($transaksi->bukti)) {
                Storage::disk('local')->delete($transaksi->bukti);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jenis' => JenisTransaksi::class,
            'nominal' => 'integer',
        ];
    }

    /** @return BelongsTo<Ksm, $this> */
    public function ksm(): BelongsTo
    {
        return $this->belongsTo(Ksm::class);
    }

    /** @return BelongsTo<KategoriTransaksi, $this> */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriTransaksi::class, 'kategori_transaksi_id');
    }

    /** @return BelongsTo<Mitra, $this> */
    public function mitra(): BelongsTo
    {
        return $this->belongsTo(Mitra::class);
    }

    /** @return BelongsTo<User, $this> */
    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh_id');
    }
}
