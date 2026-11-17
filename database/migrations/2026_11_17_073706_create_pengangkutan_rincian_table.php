<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengangkutan_rincian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengangkutan_id')->constrained('pengangkutan')->cascadeOnDelete();
            $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
            $table->string('status', 30)->default('diangkut');
            // Estimated by eye; only set when the waste was actually collected.
            $table->decimal('perkiraan_kg', 6, 1)->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->unique(['pengangkutan_id', 'pelanggan_id']);
            $table->index('pelanggan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengangkutan_rincian');
    }
};
