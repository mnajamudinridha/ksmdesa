<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->string('nama', 100);
            // App\Enums\KelompokMaterial — plastik, kertas, logam, kaca, organik, lainnya
            $table->string('kelompok', 20);
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['ksm_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_material');
    }
};
