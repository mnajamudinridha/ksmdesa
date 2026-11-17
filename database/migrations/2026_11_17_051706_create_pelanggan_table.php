<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelanggan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->foreignId('tarif_layanan_id')->nullable()->constrained('tarif_layanan')->nullOnDelete();
            // Row number in the KSM's ledger book, so the screen matches the paper.
            $table->unsignedSmallInteger('nomor_urut')->nullable();
            $table->string('nama');
            // Agreed monthly fee; starts from the tariff but may differ per customer.
            $table->unsignedInteger('iuran_bulanan');
            $table->string('rt', 20)->nullable();
            $table->boolean('aktif')->default(true);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['ksm_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pelanggan');
    }
};
