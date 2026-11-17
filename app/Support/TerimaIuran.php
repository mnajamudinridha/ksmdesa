<?php

namespace App\Support;

use App\Models\Pelanggan;
use App\Models\PembayaranIuran;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A petugas records cash taken at a customer: one payment per month, all or nothing.
 * The months were already checked against BulanIuran::pilihan() (CheckboxList "in" rule);
 * the (pelanggan_id, periode) unique index stops a month recorded meanwhile by someone else.
 */
final class TerimaIuran
{
    /**
     * @param  list<string>  $bulan  Y-m-d first days of the months paid
     * @return int number of months recorded
     */
    public static function catat(Pelanggan $pelanggan, User $petugas, array $bulan, int $nominal, string $dibayarPada, ?string $catatan = null): int
    {
        return DB::transaction(function () use ($pelanggan, $petugas, $bulan, $nominal, $dibayarPada, $catatan): int {
            foreach ($bulan as $periode) {
                $pembayaran = new PembayaranIuran([
                    'pelanggan_id' => $pelanggan->getKey(),
                    'periode' => $periode,
                    'nominal' => $nominal,
                    'dibayar_pada' => $dibayarPada,
                    'catatan' => $catatan,
                ]);
                // Not fillable: who holds the cash is never taken from input.
                $pembayaran->forceFill(['petugas_id' => $petugas->getKey()]);
                $pembayaran->save();
            }

            return count($bulan);
        });
    }
}
