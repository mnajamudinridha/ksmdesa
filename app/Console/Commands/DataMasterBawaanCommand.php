<?php

namespace App\Console\Commands;

use App\Models\Ksm;
use App\Support\DataMasterBawaan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Fill a KSM's Data Master with the starter material kinds and cash-book categories
 * (App\Support\DataMasterBawaan). Safe to run again: existing names are skipped.
 */
#[Signature('app:data-master-bawaan {ksm?* : Slug(s) of the KSM, e.g. makmurjaya; empty = every KSM}')]
#[Description('Add the default material kinds and transaction categories a KSM is missing')]
class DataMasterBawaanCommand extends Command
{
    public function handle(): int
    {
        $slug = $this->argument('ksm');
        $daftar = Ksm::query()->when($slug !== [], fn ($query) => $query->whereIn('slug', $slug))->orderBy('nama')->get();

        $tidakAda = array_diff($slug, $daftar->pluck('slug')->all());

        if ($tidakAda !== []) {
            $this->components->error('KSM tidak ditemukan: '.implode(', ', $tidakAda));

            return self::FAILURE;
        }

        foreach ($daftar as $ksm) {
            $hasil = DataMasterBawaan::terapkan($ksm);
            $this->components->info("{$ksm->nama}: {$hasil['material']} jenis material, {$hasil['kategori']} kategori transaksi ditambahkan.");
        }

        return self::SUCCESS;
    }
}
