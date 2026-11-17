<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The single place that creates and links warga accounts for customers.
 *
 * Every customer has an account. Phone numbers are unique per KSM, so a number that
 * already belongs to someone in the KSM means "this customer is that person's".
 * New accounts start on the KSM's default password and follow it while they keep it.
 */
final class AkunWarga
{
    /** Existing account of this number in the KSM, or a new warga account. */
    public static function untukNomor(Ksm $ksm, ?string $nomor, string $nama): User
    {
        $nomor = NomorHp::normalisasi($nomor);

        if ($nomor !== null && ($pemilik = self::pemilik($ksm, $nomor)) !== null) {
            return $pemilik;
        }

        $akun = new User([
            'name' => $nama,
            'no_hp' => $nomor,
            'peran' => PeranPengguna::Warga,
            'aktif' => true,
        ]);
        $akun->forceFill([
            // Without a default yet: a random hash nobody knows. The account gets the default
            // as soon as the admin sets one, because it still "uses the default".
            'password' => $ksm->sandi_default_hash ?? Hash::make(Str::random(40)),
            'pakai_sandi_default' => true,
        ]);
        $akun->ksm()->associate($ksm);
        $akun->save();

        return $akun;
    }

    /**
     * Apply a phone number typed on an existing customer.
     * Blank or unchanged: nothing. Someone else's number: link to them. Own exclusive
     * warga account: renumber it. Otherwise: a new account for this number.
     */
    public static function sinkronkanNomor(Pelanggan $pelanggan, ?string $nomor): void
    {
        $nomor = NomorHp::normalisasi($nomor);
        $akun = $pelanggan->akunWarga;

        if ($nomor === null || $akun?->no_hp === $nomor) {
            return;
        }

        if (($pemilik = self::pemilik($pelanggan->ksm, $nomor)) !== null) {
            $pelanggan->akunWarga()->associate($pemilik);

            return;
        }

        $akunEksklusif = $akun !== null
            && $akun->peran === PeranPengguna::Warga
            && $akun->pelangganMilik()->whereKeyNot($pelanggan->getKey())->doesntExist();

        if ($akunEksklusif) {
            $akun->update(['no_hp' => $nomor]);

            return;
        }

        $pelanggan->akunWarga()->associate(self::untukNomor($pelanggan->ksm, $nomor, $pelanggan->nama));
    }

    /** Who already uses this (normalised) number in the KSM, for the live typo warning too. */
    public static function pemilik(Ksm $ksm, string $nomor): ?User
    {
        return User::query()->whereBelongsTo($ksm)->where('no_hp', $nomor)->first();
    }
}
