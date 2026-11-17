<?php

namespace App\Support;

use App\Enums\JenisTransaksi;
use App\Enums\KelompokMaterial;
use App\Models\Ksm;

/**
 * Starter lists for a KSM's Data Master: material kinds and cash-book categories, so an admin
 * does not have to type them in. Applied by `php artisan app:data-master-bawaan`.
 *
 * Only adds what is missing by name — an item the admin already has (also renamed or
 * deactivated ones under that name) is left untouched, so running it again is safe.
 *
 * Deliberately NO income category for fees ("Iuran") or material sales: RingkasanKas already
 * counts fee payments and material sales as income on their own; a cash-book entry for them
 * would be counted twice.
 */
final class DataMasterBawaan
{
    /** @var array<string, KelompokMaterial> */
    public const JENIS_MATERIAL = [
        'Botol PET bening' => KelompokMaterial::Plastik,
        'Botol PET berwarna' => KelompokMaterial::Plastik,
        'Gelas plastik (cup)' => KelompokMaterial::Plastik,
        'Plastik keras (HDPE/PP)' => KelompokMaterial::Plastik,
        'Kantong & plastik lembaran' => KelompokMaterial::Plastik,
        'Plastik campur' => KelompokMaterial::Plastik,
        'Kardus / karton' => KelompokMaterial::Kertas,
        'Kertas HVS & arsip' => KelompokMaterial::Kertas,
        'Koran & majalah' => KelompokMaterial::Kertas,
        'Kertas campur (dupleks)' => KelompokMaterial::Kertas,
        'Kaleng aluminium' => KelompokMaterial::Logam,
        'Besi & kaleng besi' => KelompokMaterial::Logam,
        'Tembaga & kuningan' => KelompokMaterial::Logam,
        'Botol kaca' => KelompokMaterial::Kaca,
        'Pecahan kaca (beling)' => KelompokMaterial::Kaca,
        'Sisa makanan & dapur' => KelompokMaterial::Organik,
        'Daun & ranting' => KelompokMaterial::Organik,
        'Minyak jelantah' => KelompokMaterial::Lainnya,
        'Elektronik bekas' => KelompokMaterial::Lainnya,
        'Residu (tidak terolah)' => KelompokMaterial::Lainnya,
    ];

    /** @var array<string, list<string>> jenis value => names */
    public const KATEGORI_TRANSAKSI = [
        'masuk' => [
            'Bantuan pemerintah / Dana Desa',
            'Hibah & dukungan mitra (CSR)',
            'Sumbangan warga',
            'Pendapatan jasa lain',
            'Pendapatan lain-lain',
        ],
        'keluar' => [
            'Honor petugas',
            'BBM & transportasi',
            'Perawatan kendaraan & alat',
            'Pembelian peralatan',
            'Alat pelindung diri (APD)',
            'Biaya TPA / pembuangan residu',
            'Listrik & air',
            'Administrasi & ATK',
            'Konsumsi rapat & kegiatan',
            'Pelatihan & sosialisasi',
            'Pengeluaran lain-lain',
        ],
    ];

    /** @return array{material: int, kategori: int} how many items were added */
    public static function terapkan(Ksm $ksm): array
    {
        $material = 0;

        foreach (self::JENIS_MATERIAL as $nama => $kelompok) {
            $jenis = $ksm->jenisMaterial()->firstOrCreate(['nama' => $nama], ['kelompok' => $kelompok, 'aktif' => true]);
            $material += (int) $jenis->wasRecentlyCreated;
        }

        $kategori = 0;

        foreach (self::KATEGORI_TRANSAKSI as $jenis => $daftar) {
            foreach ($daftar as $nama) {
                $baru = $ksm->kategoriTransaksi()->firstOrCreate(['jenis' => JenisTransaksi::from($jenis), 'nama' => $nama], ['aktif' => true]);
                $kategori += (int) $baru->wasRecentlyCreated;
            }
        }

        return ['material' => $material, 'kategori' => $kategori];
    }
}
