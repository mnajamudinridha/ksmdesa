<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            // masuk | keluar (App\Enums\JenisTransaksi)
            $table->string('jenis', 10);
            $table->string('nama', 100);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['ksm_id', 'jenis', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_transaksi');
    }
};
