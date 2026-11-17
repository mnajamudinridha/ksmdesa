<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ksm_id')->constrained('ksm')->cascadeOnDelete();
            $table->string('nama', 150);
            // App\Enums\JenisMitra
            $table->string('jenis', 20);
            // Contact person and their number (normalised 08…).
            $table->string('kontak', 100)->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->text('alamat')->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique(['ksm_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra');
    }
};
