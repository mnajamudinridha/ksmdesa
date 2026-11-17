<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif_layanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->string('nama');
            // Rupiah, whole numbers.
            $table->unsignedInteger('nominal');
            $table->timestamps();

            $table->unique(['ksm_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_layanan');
    }
};
