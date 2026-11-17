<?php

namespace App\Console\Commands;

use App\Models\Ksm;
use App\Models\Pelanggan;
use App\Support\AkunWarga;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Give every customer of a KSM that has none a warga account (e.g. customers imported
 * before accounts existed). Safe to run again: customers with an account are skipped.
 */
#[Signature('app:akun-warga {ksm : Slug of the KSM, e.g. makmurjaya}')]
#[Description('Create missing warga accounts for a KSM\'s customers')]
class AkunWargaCommand extends Command
{
    public function handle(): int
    {
        $ksm = Ksm::query()->firstWhere('slug', $this->argument('ksm'));

        if ($ksm === null) {
            $this->components->error('KSM tidak ditemukan: '.$this->argument('ksm'));

            return self::FAILURE;
        }

        $dibuat = 0;
        Pelanggan::query()->whereBelongsTo($ksm)->whereNull('user_id')->orderBy('id')->each(function (Pelanggan $pelanggan) use ($ksm, &$dibuat): void {
            $pelanggan->akunWarga()->associate(AkunWarga::untukNomor($ksm, null, $pelanggan->nama));
            $pelanggan->save();
            $dibuat++;
        });

        $this->components->info("{$dibuat} akun warga dibuat untuk {$ksm->nama}.".
            ($ksm->sandi_default_hash === null ? ' Atur sandi default di Profil KSM agar akun bisa dipakai.' : ''));

        return self::SUCCESS;
    }
}
